<?php

declare(strict_types=1);

namespace MarekSkopal\MsMcpDocs\Dto;

final readonly class McpTool
{
    /** @param list<McpToolParameter> $parameters */
    public function __construct(
        public string $name,
        public string $description,
        /** @var list<McpToolParameter> */
        public array $parameters,
    ) {
    }

    public function getGroup(): string
    {
        $parts = explode('_', $this->name, 2);
        return $parts[0];
    }
}
