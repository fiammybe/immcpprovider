<?php

declare(strict_types=1);

namespace ImMcpProvider\Tool;

final class ToolCatalog
{
    private const ID_SCHEMA = [
        'type' => ['string', 'integer', 'object'],
        'description' => 'Object identifier. Use an object of key fields for composite keys.',
    ];

    /** @return array<int, ToolDefinition> */
    public function all(): array
    {
        return [
            new ToolDefinition(
                name: 'list_modules',
                description: 'List the ImpressCMS modules you can access, with their IPF handlers.',
                inputSchema: ['type' => 'object', 'properties' => new \stdClass()],
                method: 'listModules',
                readOnly: true
            ),
            new ToolDefinition(
                name: 'describe_handler',
                description: 'Describe a module handler: its key, identifier and fields.',
                inputSchema: $this->schema(
                    ['module' => ['type' => 'string'], 'handler' => ['type' => 'string']],
                    ['module', 'handler']
                ),
                method: 'describeHandler',
                readOnly: true
            ),
            new ToolDefinition(
                name: 'list_objects',
                description: 'List objects from a module handler. Filters are equality matches on field names.',
                inputSchema: $this->schema(
                    [
                        'module' => ['type' => 'string'],
                        'handler' => ['type' => 'string'],
                        'filters' => ['type' => 'object'],
                        'fields' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'start' => ['type' => 'integer', 'minimum' => 0],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                        'sort' => ['type' => 'string'],
                        'order' => ['type' => 'string', 'enum' => ['ASC', 'DESC']],
                    ],
                    ['module', 'handler']
                ),
                method: 'listObjects',
                readOnly: true
            ),
            new ToolDefinition(
                name: 'get_object',
                description: 'Fetch one object by identifier.',
                inputSchema: $this->schema(
                    [
                        'module' => ['type' => 'string'],
                        'handler' => ['type' => 'string'],
                        'id' => self::ID_SCHEMA,
                        'fields' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    ['module', 'handler', 'id']
                ),
                method: 'getObject',
                readOnly: true
            ),
            new ToolDefinition(
                name: 'create_object',
                description: 'Create an object. Requires administrator rights on the module. This changes data.',
                inputSchema: $this->schema(
                    [
                        'module' => ['type' => 'string'],
                        'handler' => ['type' => 'string'],
                        'data' => ['type' => 'object'],
                    ],
                    ['module', 'handler', 'data']
                ),
                method: 'createObject',
                readOnly: false,
                destructive: false
            ),
            new ToolDefinition(
                name: 'update_object',
                description: 'Update an object. Requires administrator rights on the module. This changes data.',
                inputSchema: $this->schema(
                    [
                        'module' => ['type' => 'string'],
                        'handler' => ['type' => 'string'],
                        'id' => self::ID_SCHEMA,
                        'data' => ['type' => 'object'],
                    ],
                    ['module', 'handler', 'id', 'data']
                ),
                method: 'updateObject',
                readOnly: false,
                destructive: false,
                idempotent: true
            ),
            new ToolDefinition(
                name: 'delete_object',
                description: 'Delete an object. Requires administrator rights on the module. This permanently removes data.',
                inputSchema: $this->schema(
                    [
                        'module' => ['type' => 'string'],
                        'handler' => ['type' => 'string'],
                        'id' => self::ID_SCHEMA,
                    ],
                    ['module', 'handler', 'id']
                ),
                method: 'deleteObject',
                readOnly: false,
                destructive: true
            ),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $properties
     * @param array<int, string> $required
     *
     * @return array<string, mixed>
     */
    private function schema(array $properties, array $required): array
    {
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ];
    }
}
