<?php

declare(strict_types=1);

namespace ImMcpProvider\Tool;

final readonly class ToolDefinition
{
    /** @param array<string, mixed> $inputSchema */
    public function __construct(
        public string $name,
        public string $description,
        public array $inputSchema,
        public string $method,
        public bool $readOnly,
        public ?bool $destructive = null,
        public ?bool $idempotent = null
    ) {
    }
}
