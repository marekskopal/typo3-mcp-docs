<?php

declare(strict_types=1);

namespace MarekSkopal\MsMcpDocs\Dto;

final readonly class McpToolParameter
{
    public function __construct(
        public string $name,
        public string $type,
        public string $description,
        public bool $required,
        public ?string $default = null,
        public ?string $enum = null,
    ) {
    }
}
