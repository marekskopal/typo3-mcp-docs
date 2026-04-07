<?php

declare(strict_types=1);

namespace MarekSkopal\MsMcpDocs\Service;

use MarekSkopal\MsMcpDocs\Dto\McpTool;
use MarekSkopal\MsMcpDocs\Dto\McpToolParameter;

final readonly class McpResponseParser
{
    /** @return list<McpTool> */
    public function parseToolsFromResponse(string $body): array
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
    public function extractJsonFromSse(string $body): ?array
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

    /** @param array{name: string, description?: string, inputSchema?: array<string, mixed>} $toolData */
    public function parseToolData(array $toolData): McpTool
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
}
