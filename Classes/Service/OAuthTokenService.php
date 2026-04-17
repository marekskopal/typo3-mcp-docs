<?php

declare(strict_types=1);

namespace MarekSkopal\MsMcpDocs\Service;

use MarekSkopal\MsMcpDocs\Dto\OAuthServerMetadata;
use MarekSkopal\MsMcpDocs\Dto\OAuthTokenResponse;
use MarekSkopal\MsMcpDocs\Dto\PkceChallengePair;
use MarekSkopal\MsMcpDocs\Repository\OAuthTokenRepositoryInterface;
use RuntimeException;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use const JSON_THROW_ON_ERROR;

final readonly class OAuthTokenService
{
    private const int PkceStateTtl = 600;

    public function __construct(
        private RequestFactory $requestFactory,
        private FrontendInterface $cache,
        private OAuthTokenRepositoryInterface $oAuthTokenRepository,
    ) {
    }

    public function discoverOAuthMetadata(string $serverUrl): OAuthServerMetadata
    {
        $parsed = parse_url($serverUrl);
        if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
            throw new RuntimeException('Invalid MCP server URL');
        }

        $origin = $parsed['scheme'] . '://' . $parsed['host'];
        if (isset($parsed['port'])) {
            $origin .= ':' . $parsed['port'];
        }

        $path = $parsed['path'] ?? '';

        // Step 1: Discover protected resource metadata
        $resourceMetadataUrl = $origin . '/.well-known/oauth-protected-resource' . $path;
        $resourceResponse = $this->requestFactory->request($resourceMetadataUrl, 'GET', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        /** @var array{authorization_servers?: list<string>} $resourceMetadata */
        $resourceMetadata = json_decode((string) $resourceResponse->getBody(), true, 16, JSON_THROW_ON_ERROR);

        $authorizationServers = $resourceMetadata['authorization_servers'] ?? [];
        if ($authorizationServers === []) {
            throw new RuntimeException('No authorization servers found in resource metadata');
        }

        // Step 2: Discover authorization server metadata
        $authServerUrl = $authorizationServers[0];

        $authServerParsed = parse_url($authServerUrl);
        if ($authServerParsed === false || !isset($authServerParsed['scheme'], $authServerParsed['host'])) {
            throw new RuntimeException('Invalid authorization server URL');
        }

        $authServerOrigin = $authServerParsed['scheme'] . '://' . $authServerParsed['host'];
        if (isset($authServerParsed['port'])) {
            $authServerOrigin .= ':' . $authServerParsed['port'];
        }

        $authServerPath = $authServerParsed['path'] ?? '';

        $authMetadataUrl = $authServerOrigin . '/.well-known/oauth-authorization-server' . $authServerPath;
        $authResponse = $this->requestFactory->request($authMetadataUrl, 'GET', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        /** @var array{issuer?: string, authorization_endpoint?: string, token_endpoint?: string, registration_endpoint?: string} $authMetadata */
        $authMetadata = json_decode((string) $authResponse->getBody(), true, 16, JSON_THROW_ON_ERROR);

        $issuer = $authMetadata['issuer'] ?? '';
        $authorizationEndpoint = $authMetadata['authorization_endpoint'] ?? '';
        $tokenEndpoint = $authMetadata['token_endpoint'] ?? '';
        $registrationEndpoint = $authMetadata['registration_endpoint'] ?? '';

        if ($authorizationEndpoint === '' || $tokenEndpoint === '' || $registrationEndpoint === '') {
            throw new RuntimeException('Authorization server metadata is missing required endpoints');
        }

        return new OAuthServerMetadata(
            issuer: $issuer,
            authorizationEndpoint: $authorizationEndpoint,
            tokenEndpoint: $tokenEndpoint,
            registrationEndpoint: $registrationEndpoint,
        );
    }

    /** @return array{client_id: string, client_name: string} */
    public function registerClient(OAuthServerMetadata $metadata, string $redirectUri): array
    {
        $response = $this->requestFactory->request($metadata->registrationEndpoint, 'POST', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => json_encode([
                'client_name' => 'TYPO3 MCP Docs',
                'redirect_uris' => [$redirectUri],
            ], JSON_THROW_ON_ERROR),
        ]);

        /** @var array{client_id: string, client_name: string} $clientData */
        $clientData = json_decode((string) $response->getBody(), true, 16, JSON_THROW_ON_ERROR);

        if ($clientData['client_id'] === '') {
            throw new RuntimeException('Client registration failed: no client_id returned');
        }

        return $clientData;
    }

    public function generatePkceChallenge(): PkceChallengePair
    {
        $codeVerifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        return new PkceChallengePair(codeVerifier: $codeVerifier, codeChallenge: $codeChallenge);
    }

    public function buildAuthorizationUrl(
        OAuthServerMetadata $metadata,
        string $clientId,
        string $redirectUri,
        string $codeChallenge,
        string $state,
    ): string {
        return $metadata->authorizationEndpoint . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);
    }

    /** @param array{code_verifier: string, client_id: string, content_element_uid: int, server_url: string, metadata: array{issuer: string, authorizationEndpoint: string, tokenEndpoint: string, registrationEndpoint: string}} $stateData */
    public function storePkceState(string $state, array $stateData): void
    {
        $this->cache->set('oauth_state_' . $state, $stateData, [], self::PkceStateTtl);
    }

    /** @return array{code_verifier: string, client_id: string, content_element_uid: int, server_url: string, metadata: array{issuer: string, authorizationEndpoint: string, tokenEndpoint: string, registrationEndpoint: string}}|null */
    public function loadPkceState(string $state): ?array
    {
        /** @var array{code_verifier: string, client_id: string, content_element_uid: int, server_url: string, metadata: array{issuer: string, authorizationEndpoint: string, tokenEndpoint: string, registrationEndpoint: string}}|false $data */
        $data = $this->cache->get('oauth_state_' . $state);

        return $data !== false ? $data : null;
    }

    public function deletePkceState(string $state): void
    {
        $this->cache->remove('oauth_state_' . $state);
    }

    public function exchangeCodeForTokens(
        OAuthServerMetadata $metadata,
        string $code,
        string $codeVerifier,
        string $clientId,
        string $redirectUri,
    ): OAuthTokenResponse {
        $response = $this->requestFactory->request($metadata->tokenEndpoint, 'POST', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ],
            'body' => http_build_query([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'code_verifier' => $codeVerifier,
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
            ]),
        ]);

        return $this->parseTokenResponse((string) $response->getBody());
    }

    public function refreshAccessToken(OAuthServerMetadata $metadata, string $refreshToken, string $clientId): OAuthTokenResponse
    {
        $response = $this->requestFactory->request($metadata->tokenEndpoint, 'POST', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ],
            'body' => http_build_query([
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $clientId,
            ]),
        ]);

        return $this->parseTokenResponse((string) $response->getBody());
    }

    public function getValidAccessToken(int $contentElementUid, string $serverUrl): ?string
    {
        $tokenRow = $this->oAuthTokenRepository->findByContentElementUid($contentElementUid);
        if ($tokenRow === null) {
            return null;
        }

        // Token is still valid (with 60s buffer)
        if ($tokenRow['expires_at'] > time() + 60) {
            return $tokenRow['access_token'];
        }

        // Try to refresh
        try {
            $metadata = $this->discoverOAuthMetadata($serverUrl);
            $newTokens = $this->refreshAccessToken($metadata, $tokenRow['refresh_token'], $tokenRow['client_id']);

            $this->oAuthTokenRepository->save(
                contentElementUid: $contentElementUid,
                serverUrl: $serverUrl,
                clientId: $tokenRow['client_id'],
                accessToken: $newTokens->accessToken,
                refreshToken: $newTokens->refreshToken,
                expiresAt: time() + $newTokens->expiresIn,
            );

            return $newTokens->accessToken;
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseTokenResponse(string $body): OAuthTokenResponse
    {
        /** @var array{access_token?: string, token_type?: string, expires_in?: int, refresh_token?: string, error?: string, error_description?: string} $data */
        $data = json_decode($body, true, 16, JSON_THROW_ON_ERROR);

        if (isset($data['error'])) {
            throw new RuntimeException('OAuth error: ' . ($data['error_description'] ?? $data['error']));
        }

        if (!isset($data['access_token'])) {
            throw new RuntimeException('Token response missing access_token');
        }

        return new OAuthTokenResponse(
            accessToken: $data['access_token'],
            tokenType: $data['token_type'] ?? 'Bearer',
            expiresIn: $data['expires_in'] ?? 3600,
            refreshToken: $data['refresh_token'] ?? '',
        );
    }
}
