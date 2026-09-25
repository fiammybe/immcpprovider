<?php

declare(strict_types=1);

namespace ImMcpProvider\Tool;

use ImMcpProvider\Ipf\IpfGateway;

final class IpfTools
{
    public const DEFAULT_LIMIT = 25;

    public function __construct(private readonly IpfGateway $gateway)
    {
    }

    /** @return array<string, mixed> */
    public function listModules(): array
    {
        return $this->gateway->listModules();
    }

    /** @return array<string, mixed> */
    public function describeHandler(string $module, string $handler): array
    {
        return $this->gateway->describeHandler($module, $handler);
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<int, string>|null $fields
     *
     * @return array<string, mixed>
     */
    public function listObjects(
        string $module,
        string $handler,
        array $filters = [],
        ?array $fields = null,
        int $start = 0,
        int $limit = self::DEFAULT_LIMIT,
        ?string $sort = null,
        ?string $order = null
    ): array {
        return $this->gateway->listObjects($module, $handler, $filters, $fields, $start, $limit, $sort, $order);
    }

    /**
     * @param array<int, string>|null $fields
     *
     * @return array<string, mixed>
     */
    public function getObject(string $module, string $handler, mixed $id, ?array $fields = null): array
    {
        return $this->gateway->getObject($module, $handler, $id, $fields);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function createObject(string $module, string $handler, array $data): array
    {
        return $this->gateway->createObject($module, $handler, $data);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function updateObject(string $module, string $handler, mixed $id, array $data): array
    {
        return $this->gateway->updateObject($module, $handler, $id, $data);
    }

    /** @return array<string, mixed> */
    public function deleteObject(string $module, string $handler, mixed $id): array
    {
        return $this->gateway->deleteObject($module, $handler, $id);
    }
}
