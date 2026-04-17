<?php

declare(strict_types=1);

namespace MarekSkopal\MsMcpDocs\Controller;

use MarekSkopal\MsMcpDocs\Service\McpIntrospectionService;
use MarekSkopal\MsMcpDocs\Service\OAuthTokenService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class McpDocsController extends ActionController
{
    public function __construct(
        private readonly McpIntrospectionService $mcpIntrospectionService,
        private readonly OAuthTokenService $oAuthTokenService,
    ) {
    }

    public function listAction(): ResponseInterface
    {
        /**
         * @var array{
         *     mcpServerUrl?: string,
         *     mcpApiKey?: string,
         *     authType?: string,
         *     description?: string,
         *     displayMode?: string,
         *     filterTools?: string,
         *  } $settings
         */
        $settings = $this->settings;

        $serverUrl = trim($settings['mcpServerUrl'] ?? '');
        $authType = $settings['authType'] ?? 'bearer';

        if ($serverUrl === '') {
            $this->view->assign('tools', []);
            $this->view->assign('error', LocalizationUtility::translate('plugin.mcpdocs.error.missing_configuration', 'MsMcpDocs'));
            return $this->htmlResponse();
        }

        $authToken = $this->resolveAuthToken($serverUrl, $authType, $settings);
        if ($authToken === null) {
            $this->view->assign('tools', []);
            $errorKey = $authType === 'oauth'
                ? 'plugin.mcpdocs.error.oauth_not_authorized'
                : 'plugin.mcpdocs.error.missing_configuration';
            $this->view->assign('error', LocalizationUtility::translate($errorKey, 'MsMcpDocs'));
            return $this->htmlResponse();
        }

        $filterTools = null;
        $filterToolsSetting = trim($settings['filterTools'] ?? '');
        if ($filterToolsSetting !== '') {
            $filterTools = array_map('trim', explode(',', $filterToolsSetting));
        }

        try {
            $tools = $this->mcpIntrospectionService->getTools($serverUrl, $authToken, $filterTools);
        } catch (\Throwable $e) {
            $this->view->assign('tools', []);
            $this->view->assign('error', sprintf(
                LocalizationUtility::translate('plugin.mcpdocs.error.fetch_failed', 'MsMcpDocs') ?? 'Failed to fetch MCP tools: %s',
                $e->getMessage(),
            ));
            return $this->htmlResponse();
        }

        $displayMode = $settings['displayMode'] ?? 'full';

        // Group tools by prefix (e.g. list_portfolios -> list, get_portfolio_summary -> get)
        $groups = [];
        foreach ($tools as $tool) {
            $group = $tool->getGroup();
            $groups[$group][] = $tool;
        }

        $description = $settings['description'] ?? '';

        $this->view->assign('tools', $tools);
        $this->view->assign('groups', $groups);
        $this->view->assign('displayMode', $displayMode);
        $this->view->assign('description', $description);

        return $this->htmlResponse();
    }

    /** @param array{mcpApiKey?: string} $settings */
    private function resolveAuthToken(string $serverUrl, string $authType, array $settings): ?string
    {
        if ($authType === 'oauth') {
            $contentObject = $this->request->getAttribute('currentContentObject');
            $uid = $contentObject instanceof ContentObjectRenderer
                ? ($contentObject->data['uid'] ?? 0)
                : 0;
            $contentElementUid = is_int($uid) ? $uid : (is_string($uid) ? (int) $uid : 0);
            if ($contentElementUid === 0) {
                return null;
            }

            return $this->oAuthTokenService->getValidAccessToken($contentElementUid, $serverUrl);
        }

        // Bearer token (default / backward compatible)
        $apiKey = trim($settings['mcpApiKey'] ?? '');
        return $apiKey !== '' ? $apiKey : null;
    }
}
