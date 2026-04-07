<?php

declare(strict_types=1);

namespace MarekSkopal\MsMcpDocs\Service;

use MarekSkopal\MsMcpDocs\Dto\McpTool;
use MarekSkopal\MsMcpDocs\Dto\McpToolParameter;
use stdClass;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use const JSON_THROW_ON_ERROR;

final readonly class McpIntrospectionService
{
    private const string CacheIdentifier = 'mcp_tools';
    private const int CacheLifetime = 86400;

    public function __construct(private RequestFactory $requestFactory, private FrontendInterface $cache,)
    {
    }

    /**
     * @param list<string>|null $filterTools
     * @return list<McpTool>
     */
    public function getTools(string $serverUrl, string $apiKey, ?array $filterTools = null): array
    {
        $cacheKey = self::CacheIdentifier . '_' . md5($serverUrl);

        /** @var list<McpTool>|false $cached */
        $cached = $this->cache->get($cacheKey);
        if ($cached !== false) {
            return $this->filterTools($cached, $filterTools);
        }

        $tools = $this->fetchTools($serverUrl, $apiKey);

        $this->cache->set($cacheKey, $tools, [], self::CacheLifetime);

        return $this->filterTools($tools, $filterTools);
    }

    /** @return list<McpTool> */
    private function fetchTools(string $serverUrl, string $apiKey): array
    {
        $sessionResponse = $this->requestFactory->request($serverUrl, 'POST', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json, text/event-stream',
                'Authorization' => 'Bearer ' . $apiKey,
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
            return $this->parseToolsFromSseResponse((string) $sessionResponse->getBody());
        }

        // Send initialized notification
        $this->requestFactory->request($serverUrl, 'POST', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json, text/event-stream',
                'Authorization' => 'Bearer ' . $apiKey,
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
                'Authorization' => 'Bearer ' . $apiKey,
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
                'Authorization' => 'Bearer ' . $apiKey,
                'Mcp-Session-Id' => $sessionId,
            ],
        ]);

        return $this->parseToolsFromSseResponse((string) $toolsResponse->getBody());
    }

    /** @return list<McpTool> */
    private function parseToolsFromSseResponse(string $body): array
    {
        $jsonData = $this->extractJsonFromSse($body);
        if ($jsonData === null) {
            return [];
        }

        /** @var array{result?: array{tools?: list<array{name: string, description?: string, inputSchema?: array<string, mixed>}>}} $jsonData */
        $toolsData = $jsonData['result']['tools'] ?? [];

        $tools = [];
        foreach ($toolsData as $toolData) {
            $tools[] = $this->parseToolData($toolData);
        }

        return $tools;
    }

    /** @return array<string, mixed>|null */
    private function extractJsonFromSse(string $body): ?array
    {
        // Try direct JSON first
        $decoded = $this->decodeJson($body);
        if ($decoded !== null) {
            return $decoded;
        }

        // Parse SSE format: look for "data: {json}" lines
        $lines = explode("\n", $body);
        foreach ($lines as $line) {
            $line = trim($line);
            if (!str_starts_with($line, 'data: ')) {
                continue;
            }
            $data = substr($line, 6);
            $decoded = $this->decodeJson($data);
            if ($decoded !== null && isset($decoded['result'])) {
                return $decoded;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function decodeJson(string $json): ?array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return null;
        }

        /** @var array<string, mixed> */
        return $decoded;
    }

    /** @param array{name: string, description?: string, inputSchema?: array<string, mixed>} $toolData */
    private function parseToolData(array $toolData): McpTool
    {
        $parameters = [];
        $schema = $toolData['inputSchema'] ?? [];
        /** @var array<string, array<string, mixed>> $properties */
        $properties = $schema['properties'] ?? [];
        /** @var list<string> $required */
        $required = $schema['required'] ?? [];

        foreach ($properties as $paramName => $paramData) {
            $rawType = $paramData['type'] ?? 'string';
            if (is_array($rawType)) {
                /** @var list<string> $rawType */
                $type = implode('|', $rawType);
            } else {
                $type = is_string($rawType) ? $rawType : 'string';
            }

            $enumValue = null;
            $rawEnum = $paramData['enum'] ?? null;
            if (is_array($rawEnum)) {
                /** @var list<string> $rawEnum */
                $enumValue = implode(', ', $rawEnum);
            }

            $default = null;
            if (array_key_exists('default', $paramData)) {
                $defaultValue = $paramData['default'];
                if (is_bool($defaultValue)) {
                    $default = $defaultValue ? 'true' : 'false';
                } elseif (is_scalar($defaultValue)) {
                    $default = (string) $defaultValue;
                }
            }

            $description = isset($paramData['description']) && is_string($paramData['description'])
                ? $paramData['description']
                : '';

            $parameters[] = new McpToolParameter(
                name: $paramName,
                type: $type,
                description: $description,
                required: in_array($paramName, $required, true),
                default: $default,
                enum: $enumValue,
            );
        }

        return new McpTool(name: $toolData['name'], description: $toolData['description'] ?? '', parameters: $parameters);
    }

    /**
     * @param list<McpTool> $tools
     * @param list<string>|null $filterTools
     * @return list<McpTool>
     */
    private function filterTools(array $tools, ?array $filterTools): array
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
