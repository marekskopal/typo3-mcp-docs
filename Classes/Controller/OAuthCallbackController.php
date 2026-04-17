<?php

declare(strict_types=1);

namespace MarekSkopal\MsMcpDocs\Controller;

use Doctrine\DBAL\ParameterType;
use MarekSkopal\MsMcpDocs\Dto\OAuthServerMetadata;
use MarekSkopal\MsMcpDocs\Repository\OAuthTokenRepositoryInterface;
use MarekSkopal\MsMcpDocs\Service\OAuthTokenService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use SimpleXMLElement;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;

final readonly class OAuthCallbackController
{
    public function __construct(
        private OAuthTokenService $oAuthTokenService,
        private OAuthTokenRepositoryInterface $oAuthTokenRepository,
        private UriBuilder $uriBuilder,
        private ConnectionPool $connectionPool,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    public function initiateAction(ServerRequestInterface $request): ResponseInterface
    {
        /** @var array<string, string> $queryParams */
        $queryParams = $request->getQueryParams();
        $contentElementUid = (int) ($queryParams['contentElementUid'] ?? 0);

        if ($contentElementUid === 0) {
            return $this->htmlResponse($this->renderMessage('Error', 'Missing content element UID.'), 400);
        }

        $serverUrl = $this->getServerUrlFromContentElement($contentElementUid);
        if ($serverUrl === null) {
            return $this->htmlResponse($this->renderMessage('Error', 'MCP server URL not configured for this content element.'), 400);
        }

        try {
            $metadata = $this->oAuthTokenService->discoverOAuthMetadata($serverUrl);

            $callbackUrl = (string) $this->uriBuilder->buildUriFromRoute('msmcpdocs_oauth_callback');

            $clientData = $this->oAuthTokenService->registerClient($metadata, $callbackUrl);
            $clientId = $clientData['client_id'];

            $pkce = $this->oAuthTokenService->generatePkceChallenge();
            $state = bin2hex(random_bytes(32));

            $this->oAuthTokenService->storePkceState($state, [
                'code_verifier' => $pkce->codeVerifier,
                'client_id' => $clientId,
                'content_element_uid' => $contentElementUid,
                'server_url' => $serverUrl,
                'metadata' => [
                    'issuer' => $metadata->issuer,
                    'authorizationEndpoint' => $metadata->authorizationEndpoint,
                    'tokenEndpoint' => $metadata->tokenEndpoint,
                    'registrationEndpoint' => $metadata->registrationEndpoint,
                ],
            ]);

            $authorizationUrl = $this->oAuthTokenService->buildAuthorizationUrl(
                metadata: $metadata,
                clientId: $clientId,
                redirectUri: $callbackUrl,
                codeChallenge: $pkce->codeChallenge,
                state: $state,
            );

            return $this->responseFactory->createResponse(302)
                ->withHeader('Location', $authorizationUrl);
        } catch (\Throwable $e) {
            return $this->htmlResponse($this->renderMessage('OAuth Error', 'Failed to initiate OAuth flow: ' . $e->getMessage()), 500);
        }
    }

    public function callbackAction(ServerRequestInterface $request): ResponseInterface
    {
        /** @var array<string, string> $queryParams */
        $queryParams = $request->getQueryParams();

        $code = $queryParams['code'] ?? '';
        $state = $queryParams['state'] ?? '';
        $error = $queryParams['error'] ?? '';

        if ($error !== '') {
            $errorDescription = $queryParams['error_description'] ?? $error;
            return $this->htmlResponse($this->renderMessage('Authorization Failed', htmlspecialchars($errorDescription)), 400);
        }

        if ($code === '' || $state === '') {
            return $this->htmlResponse($this->renderMessage('Error', 'Missing authorization code or state parameter.'), 400);
        }

        $stateData = $this->oAuthTokenService->loadPkceState($state);
        if ($stateData === null) {
            return $this->htmlResponse($this->renderMessage('Error', 'Invalid or expired OAuth state. Please try again.'), 400);
        }

        try {
            $metadata = new OAuthServerMetadata(
                issuer: $stateData['metadata']['issuer'],
                authorizationEndpoint: $stateData['metadata']['authorizationEndpoint'],
                tokenEndpoint: $stateData['metadata']['tokenEndpoint'],
                registrationEndpoint: $stateData['metadata']['registrationEndpoint'],
            );

            $callbackUrl = (string) $this->uriBuilder->buildUriFromRoute('msmcpdocs_oauth_callback');

            $tokenResponse = $this->oAuthTokenService->exchangeCodeForTokens(
                metadata: $metadata,
                code: $code,
                codeVerifier: $stateData['code_verifier'],
                clientId: $stateData['client_id'],
                redirectUri: $callbackUrl,
            );

            $this->oAuthTokenRepository->save(
                contentElementUid: $stateData['content_element_uid'],
                serverUrl: $stateData['server_url'],
                clientId: $stateData['client_id'],
                accessToken: $tokenResponse->accessToken,
                refreshToken: $tokenResponse->refreshToken,
                expiresAt: time() + $tokenResponse->expiresIn,
            );

            $this->oAuthTokenService->deletePkceState($state);

            return $this->htmlResponse($this->renderMessage(
                'Authorization Successful',
                'OAuth authorization completed successfully. You can close this window and return to the TYPO3 backend.',
            ));
        } catch (\Throwable $e) {
            return $this->htmlResponse(
                $this->renderMessage('Token Exchange Failed', 'Failed to obtain tokens: ' . htmlspecialchars($e->getMessage())),
                500,
            );
        }
    }

    private function getServerUrlFromContentElement(int $contentElementUid): ?string
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');

        /** @var array{pi_flexform: string}|false $row */
        $row = $queryBuilder
            ->select('pi_flexform')
            ->from('tt_content')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($contentElementUid, ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchAssociative();

        if ($row === false || $row['pi_flexform'] === '') {
            return null;
        }

        $xml = simplexml_load_string($row['pi_flexform']);
        if ($xml === false) {
            return null;
        }

        $sheets = $xml->data->sheet ?? null;
        if ($sheets === null) {
            return null;
        }

        /** @var SimpleXMLElement $sheet */
        foreach ($sheets as $sheet) {
            $fields = $sheet->language->field ?? null;
            if ($fields === null) {
                continue;
            }

            /** @var SimpleXMLElement $field */
            foreach ($fields as $field) {
                if ((string) $field['index'] === 'settings.mcpServerUrl') {
                    $value = trim((string) $field->value);
                    return $value !== '' ? $value : null;
                }
            }
        }

        return null;
    }

    private function htmlResponse(string $html, int $statusCode = 200): ResponseInterface
    {
        return $this->responseFactory->createResponse($statusCode)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withBody($this->streamFactory->createStream($html));
    }

    private function renderMessage(string $title, string $message): string
    {
        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . htmlspecialchars($title) . '</title>'
            . '<style>body{font-family:sans-serif;padding:40px;text-align:center}h1{color:#333}p{color:#666;max-width:500px;margin:20px auto}</style>'
            . '</head><body><h1>' . htmlspecialchars($title) . '</h1><p>' . $message . '</p></body></html>';
    }
}
