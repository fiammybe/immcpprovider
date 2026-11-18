<?php

declare(strict_types=1);

namespace ImMcpProvider\Ipf;

use ImMcpProvider\Module;
use ImMcpProvider\Security\AccessPolicy;
use icms;
use icms_db_criteria_Compo;
use icms_db_criteria_Item;
use icms_ipf_Handler;
use icms_ipf_Object;
use icms_module_Object;
use Mcp\Exception\ToolCallException;
use mod_mcpapi_Context;

final class IpfGateway
{
    private const MAX_LIMIT = 100;

    private const EXCLUDED_MODULES = ['system'];

    private const SENSITIVE_FIELDS = ['pass', 'pass_expired', 'salt', 'enc_type', 'actkey', 'token'];

    public function __construct(
        private readonly AccessPolicy $policy,
        private readonly HandlerLocator $locator = new HandlerLocator()
    ) {
    }

    /**
     * @return array{
     *     count: int,
     *     modules: array<int, array<string, mixed>>
     * }
     */
    public function listModules(): array
    {
        $criteria = new icms_db_criteria_Compo();
        $criteria->add(new icms_db_criteria_Item('isactive', 1));

        $modules = [];

        foreach (icms::handler('icms_module')->getObjects($criteria) as $module) {
            if (! $this->isExposed($module)) {
                continue;
            }

            $dirname = (string) $module->getVar('dirname');

            $modules[] = [
                'dirname' => $dirname,
                'name' => (string) $module->getVar('name'),
                'version' => $module->getVar('version'),
                'handlers' => $this->declaredHandlers($module),
                'writable' => $this->policy->canAdministerModule($module),
                'context' => $this->filterContext($dirname, mod_mcpapi_Context::SCOPE_MODULE, null, null),
            ];
        }

        return [
            'count' => count($modules),
            'modules' => $modules,
        ];
    }

    /** @return array<string, mixed> */
    public function describeHandler(string $moduleName, string $handlerName): array
    {
        $module = $this->resolveModule($moduleName);
        $handler = $this->resolveHandler($module, $moduleName, $handlerName);

        return $this->describeResolvedHandler($module, $moduleName, $handlerName, $handler);
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<int, string>|null $fields
     *
     * @return array<string, mixed>
     */
    public function listObjects(
        string $moduleName,
        string $handlerName,
        array $filters,
        ?array $fields,
        int $start,
        int $limit,
        ?string $sort,
        ?string $order
    ): array {
        $module = $this->resolveModule($moduleName);
        $handler = $this->resolveHandler($module, $moduleName, $handlerName);
        $vars = $this->readableVars($handler);
        $criteria = $this->buildCriteria($vars, $filters, $start, $limit, $sort, $order);
        $selectedFields = $this->normalizeRequestedFields($fields, $vars);

        if (! $this->policy->restrictCriteriaToViewable($handler, $module, $criteria)) {
            return [
                'count' => 0,
                'objects' => [],
            ];
        }

        $objects = $handler->getObjects($criteria);

        return [
            'count' => count($objects),
            'objects' => array_map(
                fn (icms_ipf_Object $object) => $this->serializeObject($object, $selectedFields),
                $objects
            ),
        ];
    }

    /**
     * @param array<int, string>|null $fields
     *
     * @return array<string, mixed>
     */
    public function getObject(string $moduleName, string $handlerName, mixed $id, ?array $fields): array
    {
        $module = $this->resolveModule($moduleName);
        $handler = $this->resolveHandler($module, $moduleName, $handlerName);
        $selectedFields = $this->normalizeRequestedFields($fields, $this->readableVars($handler));
        $object = $this->fetchViewableObject($handler, $module, $id);

        return ['object' => $this->serializeObject($object, $selectedFields)];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function createObject(string $moduleName, string $handlerName, array $data): array
    {
        $module = $this->resolveWritableModule($moduleName);
        $handler = $this->resolveHandler($module, $moduleName, $handlerName);
        $object = $handler->create();
        $fieldResult = $this->applyDataToObject($handler, $object, $data);

        $this->persist($handler, $object, 'create');

        return [
            'status' => 'created',
            'applied_fields' => $fieldResult['applied'],
            'ignored_fields' => $fieldResult['ignored'],
            'object' => $this->serializeObject($object, null),
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function updateObject(string $moduleName, string $handlerName, mixed $id, array $data): array
    {
        $module = $this->resolveWritableModule($moduleName);
        $handler = $this->resolveHandler($module, $moduleName, $handlerName);
        $object = $this->fetchObject($handler, $id);
        $fieldResult = $this->applyDataToObject($handler, $object, $data);

        $this->persist($handler, $object, 'update');

        return [
            'status' => 'updated',
            'applied_fields' => $fieldResult['applied'],
            'ignored_fields' => $fieldResult['ignored'],
            'object' => $this->serializeObject($object, null),
        ];
    }

    /** @return array<string, mixed> */
    public function deleteObject(string $moduleName, string $handlerName, mixed $id): array
    {
        $module = $this->resolveWritableModule($moduleName);
        $handler = $this->resolveHandler($module, $moduleName, $handlerName);
        $object = $this->fetchObject($handler, $id);
        $serialized = $this->serializeObject($object, null);

        if (! $handler->delete($object, true)) {
            throw new ToolCallException('Failed to delete object.');
        }

        return [
            'status' => 'deleted',
            'object' => $serialized,
        ];
    }

    private function isExposed(icms_module_Object $module): bool
    {
        $dirname = (string) $module->getVar('dirname');

        if (in_array($dirname, self::EXCLUDED_MODULES, true)) {
            return false;
        }

        if ($dirname === basename(dirname(__DIR__, 2))) {
            return false;
        }

        return $this->policy->canReadModule($module);
    }

    private function resolveModule(string $moduleName): icms_module_Object
    {
        $module = icms::handler('icms_module')->getByDirname($moduleName);

        if (! is_object($module)) {
            throw new ToolCallException("Module '{$moduleName}' is not available.");
        }

        if (! (bool) $module->getVar('isactive')) {
            throw new ToolCallException("Module '{$moduleName}' is not available.");
        }

        if (! $this->isExposed($module)) {
            throw new ToolCallException("Module '{$moduleName}' is not available.");
        }

        return $module;
    }

    private function resolveWritableModule(string $moduleName): icms_module_Object
    {
        $module = $this->resolveModule($moduleName);

        if (! $this->policy->canAdministerModule($module)) {
            throw new ToolCallException("You do not have permission to change data in module '{$moduleName}'.");
        }

        return $module;
    }

    private function resolveHandler(icms_module_Object $module, string $moduleName, string $handlerName): icms_ipf_Handler
    {
        if (! in_array($handlerName, $this->declaredHandlers($module), true)) {
            throw new ToolCallException("Handler '{$handlerName}' is not available for module '{$moduleName}'.");
        }

        $module->registerClassPath();

        $handler = $this->locator->locate($moduleName, $handlerName);

        return $handler;
    }

    /** @return array<int, string> */
    private function declaredHandlers(icms_module_Object $module): array
    {
        $objectItems = $module->getInfo('object_items');

        if (! is_array($objectItems)) {
            return [];
        }

        $handlers = array_filter($objectItems, static fn (mixed $item): bool => is_string($item) && $item !== '');
        $handlers = array_values(array_unique($handlers));

        sort($handlers);

        return $handlers;
    }

    /**
     * Stored context entries (from a module push and/or an mcpapi administrator) matching
     * the given scope, handler and field. Both sources are returned side by side; neither
     * overrides the other.
     *
     * @return array<int, array<string, mixed>>
     */
    private function filterContext(string $dirname, string $scope, ?string $handlerName, ?string $field): array
    {
        $entries = [];

        foreach (Module::handler('context')->getForModule($dirname) as $entry) {
            if ((string) $entry->getVar('scope', 'n') !== $scope) {
                continue;
            }

            if ($handlerName !== null && (string) $entry->getVar('handler', 'n') !== $handlerName) {
                continue;
            }

            if ($field !== null && (string) $entry->getVar('field', 'n') !== $field) {
                continue;
            }

            $entries[] = $entry->toMcpArray();
        }

        return $entries;
    }

    /** @return array<string, mixed> */
    private function describeResolvedHandler(
        icms_module_Object $module,
        string $moduleName,
        string $handlerName,
        icms_ipf_Handler $handler
    ): array {
        $canWrite = $this->policy->canAdministerModule($module);
        $fields = [];

        foreach ($this->readableVars($handler) as $name => $definition) {
            $fields[$name] = [
                'data_type' => $definition['data_type'] ?? null,
                'required' => (bool) ($definition['required'] ?? false),
                'persistent' => (bool) ($definition['persistent'] ?? true),
                'writable' => $canWrite && $this->isWritableField($handler, $name, $definition),
                'caption' => $definition['form_caption'] ?? '',
                'context' => $this->filterContext($moduleName, mod_mcpapi_Context::SCOPE_FIELD, $handlerName, $name),
            ];
        }

        return [
            'module' => $moduleName,
            'handler' => $handlerName,
            'key' => $handler->keyName,
            'identifier' => $handler->identifierName,
            'writable' => $canWrite,
            'context' => $this->filterContext($moduleName, mod_mcpapi_Context::SCOPE_HANDLER, $handlerName, null),
            'fields' => $fields,
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function readableVars(icms_ipf_Handler $handler): array
    {
        $vars = [];

        foreach ($handler->create()->getVars() as $name => $definition) {
            if (in_array($name, self::SENSITIVE_FIELDS, true)) {
                continue;
            }

            $vars[$name] = is_array($definition) ? $definition : [];
        }

        return $vars;
    }

    /** @param array<string, mixed> $definition */
    private function isWritableField(icms_ipf_Handler $handler, string $field, array $definition): bool
    {
        if ((bool) ($definition['readonly'] ?? false)) {
            return false;
        }

        if (! (bool) ($definition['persistent'] ?? true)) {
            return false;
        }

        return ! in_array($field, $this->keyFields($handler), true);
    }

    /** @return array<int, string> */
    private function keyFields(icms_ipf_Handler $handler): array
    {
        return is_array($handler->keyName) ? $handler->keyName : [$handler->keyName];
    }

    /**
     * @param array<string, array<string, mixed>> $vars
     * @param array<string, mixed> $filters
     */
    private function buildCriteria(
        array $vars,
        array $filters,
        int $start,
        int $limit,
        ?string $sort,
        ?string $order
    ): icms_db_criteria_Compo {
        $criteria = new icms_db_criteria_Compo();

        foreach ($filters as $field => $value) {
            if (! $this->isFilterableField($vars, (string) $field)) {
                throw new ToolCallException("Cannot filter on unknown field '{$field}'.");
            }

            if (! is_scalar($value)) {
                throw new ToolCallException("Filter '{$field}' must be a scalar value.");
            }

            $criteria->add(new SafeCriteriaItem((string) $field, $value));
        }

        $criteria->setStart(max(0, $start));
        $criteria->setLimit(min(self::MAX_LIMIT, max(1, $limit)));

        if ($sort !== null && $sort !== '') {
            if (! $this->isFilterableField($vars, $sort)) {
                throw new ToolCallException("Cannot sort on unknown field '{$sort}'.");
            }

            $criteria->setSort($sort);
            $criteria->setOrder(strtoupper((string) $order) === 'DESC' ? 'DESC' : 'ASC');
        }

        return $criteria;
    }

    /** @param array<string, array<string, mixed>> $vars */
    private function isFilterableField(array $vars, string $field): bool
    {
        if (! array_key_exists($field, $vars)) {
            return false;
        }

        return (bool) ($vars[$field]['persistent'] ?? true);
    }

    /**
     * @param array<int, string>|null $fields
     * @param array<string, array<string, mixed>> $vars
     *
     * @return array<int, string>
     */
    private function normalizeRequestedFields(?array $fields, array $vars): array
    {
        if ($fields === null) {
            return array_keys($vars);
        }

        foreach ($fields as $field) {
            if (! array_key_exists($field, $vars)) {
                throw new ToolCallException("Unknown field '{$field}'.");
            }
        }

        return array_values(array_unique($fields));
    }

    private function fetchObject(icms_ipf_Handler $handler, mixed $id): icms_ipf_Object
    {
        $object = $handler->get($this->normalizeIdentifier($handler, $id));

        if (! $object instanceof icms_ipf_Object || $object->isNew()) {
            throw new ToolCallException('Object not found.');
        }

        return $object;
    }

    private function fetchViewableObject(icms_ipf_Handler $handler, icms_module_Object $module, mixed $id): icms_ipf_Object
    {
        $object = $this->fetchObject($handler, $id);

        if (! $this->policy->canViewObject($handler, $module, $object)) {
            throw new ToolCallException('Object not found.');
        }

        return $object;
    }

    private function normalizeIdentifier(icms_ipf_Handler $handler, mixed $id): mixed
    {
        $keyName = $handler->keyName;

        if (is_array($keyName)) {
            return $this->resolveCompositeIdentifier($keyName, $id);
        }

        if (is_array($id)) {
            return $this->resolveIdentifierFromArray($keyName, $id);
        }

        if ($id === null || $id === '') {
            throw new ToolCallException('Missing object identifier.');
        }

        return $id;
    }

    /**
     * @param array<int, string> $keyName
     *
     * @return array<int, mixed>
     */
    private function resolveCompositeIdentifier(array $keyName, mixed $id): array
    {
        if (! is_array($id)) {
            throw new ToolCallException('Composite identifiers must be provided as an object.');
        }

        $resolved = [];

        foreach ($keyName as $keyField) {
            if (! array_key_exists($keyField, $id)) {
                throw new ToolCallException("Missing identifier field '{$keyField}'.");
            }

            $resolved[] = $id[$keyField];
        }

        return $resolved;
    }

    /** @param array<string, mixed> $id */
    private function resolveIdentifierFromArray(string $keyName, array $id): mixed
    {
        if (! array_key_exists($keyName, $id)) {
            throw new ToolCallException('Missing object identifier.');
        }

        return $id[$keyName];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{
     *     applied: array<int, string>,
     *     ignored: array<int, string>
     * }
     */
    private function applyDataToObject(icms_ipf_Handler $handler, icms_ipf_Object $object, array $data): array
    {
        $vars = $this->readableVars($handler);
        $applied = [];
        $ignored = [];

        foreach ($data as $field => $value) {
            $field = (string) $field;

            if (! array_key_exists($field, $vars)) {
                $ignored[] = $field;

                continue;
            }

            if (! $this->isWritableField($handler, $field, $vars[$field])) {
                $ignored[] = $field;

                continue;
            }

            $object->setVar($field, $value);
            $applied[] = $field;
        }

        if ($applied === []) {
            throw new ToolCallException('No writable fields were provided.');
        }

        return [
            'applied' => $applied,
            'ignored' => $ignored,
        ];
    }

    private function persist(icms_ipf_Handler $handler, icms_ipf_Object $object, string $action): void
    {
        if ($handler->insert($object, true)) {
            return;
        }

        $errors = $object->getErrors();
        $detail = is_array($errors) && $errors !== [] ? ': ' . strip_tags(implode('; ', $errors)) : '.';

        throw new ToolCallException("Failed to {$action} object{$detail}");
    }

    /**
     * @param array<int, string>|null $fields
     *
     * @return array<string, mixed>
     */
    private function serializeObject(icms_ipf_Object $object, ?array $fields): array
    {
        $vars = array_keys(array_diff_key($object->getVars(), array_flip(self::SENSITIVE_FIELDS)));
        $serialized = [];

        foreach ($fields ?? $vars as $field) {
            $serialized[$field] = $object->getVar($field, 'n');
        }

        return $serialized;
    }
}
