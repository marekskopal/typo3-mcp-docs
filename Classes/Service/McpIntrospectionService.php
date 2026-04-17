<?php

declare(strict_types=1);

namespace MarekSkopal\MsMcpDocs\Service;

use MarekSkopal\MsMcpDocs\Dto\McpTool;
use stdClass;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use const JSON_THROW_ON_ERROR;

final readonly class McpIntrospectionService
{
    private const string CacheIdentifier = 'mcp_tools';
    private const int CacheLifetime = 86400;

    public function __construct(
        private RequestFactory $requestFactory,
        private FrontendInterface $cache,
        private McpResponseParser $responseParser,
    ) {
    }

    /**
     * @param list<string>|null $filterTools
     * @return list<McpTool>
     */
    public function getTools(string $serverUrl, string $authToken, ?array $filterTools = null): array
    {
        $cacheKey = self::CacheIdentifier . '_' . md5($serverUrl);

        /** @var list<McpTool>|false $cached */
        $cached = $this->cache->get($cacheKey);
        if ($cached !== false) {
            return self::filterTools($cached, $filterTools);
        }

        $tools = $this->fetchTools($serverUrl, $authToken);

        $this->cache->set($cacheKey, $tools, [], self::CacheLifetime);

        return self::filterTools($tools, $filterTools);
    }

    /** @return list<McpTool> */
    private function fetchTools(string $serverUrl, string $authToken): array
    {
        $sessionResponse = $this->requestFactory->request($serverUrl, 'POST', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json, text/event-stream',
                'Authorization' => 'Bearer ' . $authToken,
            ],
            'body' => json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-03-26',
                    'capabilities' => new stdClass(),
                    'clientInfo' => [
                        'name' => 'typo3-mcp-docs',
                        'version' => '1.0.0',
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        $sessionId = $sessionResponse->getHeaderLine('mcp-session-id');
        if ($sessionId === '') {
            return $this->responseParser->parseToolsFromResponse((string) $sessionResponse->getBody());
        }

        // Send initialized notification
        $this->requestFactory->request($serverUrl, 'POST', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json, text/event-stream',
                'Authorization' => 'Bearer ' . $authToken,
                'Mcp-Session-Id' => $sessionId,
            ],
            'body' => json_encode([
                'jsonrpc' => '2.0',
                'method' => 'notifications/initialized',
            ], JSON_THROW_ON_ERROR),
        ]);

        // Fetch tools list
        $toolsResponse = $this->requestFactory->request($serverUrl, 'POST', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json, text/event-stream',
                'Authorization' => 'Bearer ' . $authToken,
                'Mcp-Session-Id' => $sessionId,
            ],
            'body' => json_encode([
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'tools/list',
            ], JSON_THROW_ON_ERROR),
        ]);

        // Terminate session
        $this->requestFactory->request($serverUrl, 'DELETE', [
            'headers' => [
                'Authorization' => 'Bearer ' . $authToken,
                'Mcp-Session-Id' => $sessionId,
            ],
        ]);

        return $this->responseParser->parseToolsFromResponse((string) $toolsResponse->getBody());
    }

    /**
     * @param list<McpTool> $tools
     * @param list<string>|null $filterTools
     * @return list<McpTool>
     */
    public static function filterTools(array $tools, ?array $filterTools): array
    {
        if ($filterTools === null || $filterTools === []) {
            return $tools;
        }

        return array_values(array_filter(
            $tools,
            static fn(McpTool $tool): bool => in_array($tool->name, $filterTools, true),
        ));
    }
}
