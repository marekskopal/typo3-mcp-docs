<?php

declare(strict_types=1);

namespace MarekSkopal\MsMcpDocs\Form\Element;

use MarekSkopal\MsMcpDocs\Repository\OAuthTokenRepository;
use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class OAuthStatusElement extends AbstractFormElement
{
    /** @return array{html: string} */
    public function render(): array
    {
        $result = $this->initializeResultArray();

        /** @var array{uid?: int|string} $databaseRow */
        $databaseRow = $this->data['databaseRow'] ?? [];
        $contentElementUid = (int) ($databaseRow['uid'] ?? 0);

        if ($contentElementUid === 0) {
            $result['html'] = '<div class="alert alert-info">Save the content element first, then authorize OAuth.</div>';
            return $result;
        }

        $oAuthTokenRepository = GeneralUtility::makeInstance(OAuthTokenRepository::class);
        $tokenRow = $oAuthTokenRepository->findByContentElementUid($contentElementUid);

        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $initiateUrl = (string) $uriBuilder->buildUriFromRoute('msmcpdocs_oauth_initiate', [
            'contentElementUid' => $contentElementUid,
        ]);

        if ($tokenRow !== null && $tokenRow['expires_at'] > time()) {
            $expiresAt = date('Y-m-d H:i', $tokenRow['expires_at']);
            $result['html'] = '<div class="alert alert-success" style="margin-bottom:10px">'
                . '<strong>Authorized</strong> — token expires: ' . htmlspecialchars($expiresAt)
                . '</div>'
                . '<a href="' . htmlspecialchars($initiateUrl) . '" class="btn btn-default btn-sm" target="_blank">Re-authorize</a>';
        } elseif ($tokenRow !== null) {
            $result['html'] = '<div class="alert alert-warning" style="margin-bottom:10px">'
                . '<strong>Token expired</strong> — re-authorization required'
                . '</div>'
                . '<a href="' . htmlspecialchars($initiateUrl) . '" class="btn btn-warning btn-sm" target="_blank">Re-authorize</a>';
        } else {
            $result['html'] = '<div class="alert alert-info" style="margin-bottom:10px">'
                . 'Not authorized — click the button below to authorize with the MCP server.'
                . '</div>'
                . '<a href="' . htmlspecialchars($initiateUrl) . '" class="btn btn-primary btn-sm" target="_blank">Authorize with OAuth</a>';
        }

        return $result;
    }
}
