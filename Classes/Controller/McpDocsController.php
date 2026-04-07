<?php

declare(strict_types=1);

namespace MarekSkopal\MsMcpDocs\Controller;

use MarekSkopal\MsMcpDocs\Service\McpIntrospectionService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class McpDocsController extends ActionController
{
    public function __construct(private readonly McpIntrospectionService $mcpIntrospectionService)
    {
    }

    public function listAction(): ResponseInterface
    {
        /**
         * @var array{
         *     mcpServerUrl?: string,
         *     mcpApiKey?: string,
         *     description?: string,
         *     displayMode?: string,
         *     filterTools?: string,
         *  } $settings
         */
        $settings = $this->settings;

        $serverUrl = trim($settings['mcpServerUrl'] ?? '');
        $apiKey = trim($settings['mcpApiKey'] ?? '');

        if ($serverUrl === '' || $apiKey === '') {
            $this->view->assign('tools', []);
            $this->view->assign('error', 'MCP server URL and API key must be configured.');
            return $this->htmlResponse();
        }

        $filterTools = null;
        $filterToolsSetting = trim($settings['filterTools'] ?? '');
        if ($filterToolsSetting !== '') {
            $filterTools = array_map('trim', explode(',', $filterToolsSetting));
        }

        try {
            $tools = $this->mcpIntrospectionService->getTools($serverUrl, $apiKey, $filterTools);
        } catch (\Throwable $e) {
            $this->view->assign('tools', []);
            $this->view->assign('error', 'Failed to fetch MCP tools: ' . $e->getMessage());
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
}
