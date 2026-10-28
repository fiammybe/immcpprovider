<?php
declare(strict_types=1);

class MCPProvider
{
    public function handle(array $payload): mixed
    {
        $method = $payload['method'] ?? null;
        $params = $payload['params'] ?? [];

        if (!is_string($method) || $method === '') {
            throw new InvalidArgumentException('Missing MCP method name.');
        }
        if (!is_array($params)) {
            throw new InvalidArgumentException('MCP params must be an array.');
        }

        return match ($method) {
            'listModules' => $this->listModules($params),
            'describeHandler' => $this->describeHandler(
                $this->requireStringParam($params, 'module'),
                $this->requireStringParam($params, 'handler')
            ),
            'listObjects' => $this->listObjects(
                $this->requireStringParam($params, 'module'),
                $this->requireStringParam($params, 'handler'),
                $params
            ),
            'getObject' => $this->getObject(
                $this->requireStringParam($params, 'module'),
                $this->requireStringParam($params, 'handler'),
                $params['id'] ?? null,
                $params
            ),
            'createObject' => $this->createObject(
                $this->requireStringParam($params, 'module'),
                $this->requireStringParam($params, 'handler'),
                $this->requireArrayParam($params, 'data'),
                $params
            ),
            'updateObject' => $this->updateObject(
                $this->requireStringParam($params, 'module'),
                $this->requireStringParam($params, 'handler'),
                $params['id'] ?? null,
                $this->requireArrayParam($params, 'data'),
                $params
            ),
            'deleteObject' => $this->deleteObject(
                $this->requireStringParam($params, 'module'),
                $this->requireStringParam($params, 'handler'),
                $params['id'] ?? null
            ),
            'listArticles' => $this->listArticles(),
            'getArticle' => $this->getArticle((int) ($params['id'] ?? 0)),
            'updateArticle' => $this->updateArticle((int) ($params['id'] ?? 0), $this->requireArrayParam($params, 'data')),
            default => throw new RuntimeException("Unknown MCP method: {$method}")
        };
    }

    private function listModules(array $params): array
    {
        $criteria = new icms_db_criteria_Compo();
        $activeOnly = (bool) ($params['activeOnly'] ?? true);
        if ($activeOnly) {
            $criteria->add(new icms_db_criteria_Item('isactive', 1));
        }

        $moduleHandler = icms::handler('icms_module');
        $modules = $moduleHandler->getObjects($criteria);
        $result = [];

        foreach ($modules as $module) {
            if (method_exists($module, 'registerClassPath')) {
                $module->registerClassPath();
            }

            $handlers = $this->normalizeHandlerNames($module->getInfo('object_items'));
            $result[] = [
                'dirname' => (string) $module->getVar('dirname'),
                'name' => (string) $module->getVar('name'),
                'version' => $module->getVar('version'),
                'isactive' => (bool) $module->getVar('isactive'),
                'ipf' => (bool) $module->getVar('ipf'),
                'handlers' => $handlers,
            ];
        }

        return [
            'count' => count($result),
            'modules' => $result,
        ];
    }

    private function describeHandler(string $moduleName, string $handlerName): array
    {
        $handler = $this->resolveHandler($moduleName, $handlerName);
        return $this->describeResolvedHandler($moduleName, $handlerName, $handler);
    }

    private function listObjects(string $moduleName, string $handlerName, array $params): array
    {
        $handler = $this->resolveHandler($moduleName, $handlerName);
        $criteria = $this->buildCriteria($handler, $params);
        $objects = $handler->getObjects($criteria);
        $fields = $this->normalizeRequestedFields($params['fields'] ?? null);

        return [
            'descriptor' => $this->describeResolvedHandler($moduleName, $handlerName, $handler),
            'count' => count($objects),
            'objects' => array_map(
                fn($object) => $this->serializeObject($object, $fields),
                $objects
            ),
        ];
    }

    private function getObject(string $moduleName, string $handlerName, mixed $id, array $params): array
    {
        $handler = $this->resolveHandler($moduleName, $handlerName);
        $object = $this->fetchObject($handler, $id);
        $fields = $this->normalizeRequestedFields($params['fields'] ?? null);

        return [
            'descriptor' => $this->describeResolvedHandler($moduleName, $handlerName, $handler),
            'object' => $this->serializeObject($object, $fields),
        ];
    }

    private function createObject(string $moduleName, string $handlerName, array $data, array $params): array
    {
        $handler = $this->resolveHandler($moduleName, $handlerName);
        $object = $handler->create();
        $fieldResult = $this->applyDataToObject($object, $data);

        if (!$handler->insert($object)) {
            throw new RuntimeException('Failed to create object.');
        }

        return [
            'status' => 'created',
            'applied_fields' => $fieldResult['applied'],
            'ignored_fields' => $fieldResult['ignored'],
            'object' => $this->serializeObject($object, $this->normalizeRequestedFields($params['fields'] ?? null)),
        ];
    }

    private function updateObject(string $moduleName, string $handlerName, mixed $id, array $data, array $params): array
    {
        $handler = $this->resolveHandler($moduleName, $handlerName);
        $object = $this->fetchObject($handler, $id);
        $fieldResult = $this->applyDataToObject($object, $data);

        if (!$handler->insert($object)) {
            throw new RuntimeException('Failed to update object.');
        }

        return [
            'status' => 'updated',
            'applied_fields' => $fieldResult['applied'],
            'ignored_fields' => $fieldResult['ignored'],
            'object' => $this->serializeObject($object, $this->normalizeRequestedFields($params['fields'] ?? null)),
        ];
    }

    private function deleteObject(string $moduleName, string $handlerName, mixed $id): array
    {
        $handler = $this->resolveHandler($moduleName, $handlerName);
        $object = $this->fetchObject($handler, $id);
        $serialized = $this->serializeObject($object);

        if (!$handler->delete($object)) {
            throw new RuntimeException('Failed to delete object.');
        }

        return [
            'status' => 'deleted',
            'object' => $serialized,
        ];
    }

    private function listArticles(): array
    {
        $result = $this->listObjects('news', 'article', ['fields' => ['article_id', 'title', 'summary']]);
        return array_map(static fn(array $article) => [
            'id' => $article['article_id'] ?? null,
            'title' => $article['title'] ?? null,
            'summary' => $article['summary'] ?? null,
        ], $result['objects']);
    }

    private function getArticle(int $id): array
    {
        $result = $this->getObject('news', 'article', $id, ['fields' => ['article_id', 'title', 'body', 'summary']]);
        $article = $result['object'];

        return [
            'id' => $article['article_id'] ?? null,
            'title' => $article['title'] ?? null,
            'body' => $article['body'] ?? null,
            'summary' => $article['summary'] ?? null,
        ];
    }

    private function updateArticle(int $id, array $data): array
    {
        $this->updateObject('news', 'article', $id, $data, []);
        return ['status' => 'ok'];
    }

    private function resolveHandler(string $moduleName, string $handlerName): object
    {
        if (!preg_match('/\A[a-zA-Z0-9_-]+\z/D', $handlerName)) {
            throw new InvalidArgumentException('Invalid handler name.');
        }
        $module = $this->resolveModule($moduleName);
        if (method_exists($module, 'registerClassPath')) {
            $module->registerClassPath();
        }

        $handler = icms_getModuleHandler($handlerName, $moduleName, null, true);
        if (!is_object($handler)) {
            throw new RuntimeException("Handler '{$handlerName}' is not available for module '{$moduleName}'.");
        }
        foreach (['create', 'get', 'getObjects', 'insert', 'delete'] as $method) {
            if (!method_exists($handler, $method)) {
                throw new RuntimeException("Handler '{$handlerName}' in module '{$moduleName}' is not IPF-compatible.");
            }
        }

        return $handler;
    }

    private function resolveModule(string $moduleName): object
    {
        if (!preg_match('/\A[a-zA-Z0-9_-]+\z/D', $moduleName)) {
            throw new InvalidArgumentException('Invalid module name.');
        }
        $module = icms::handler('icms_module')->getByDirname($moduleName);
        if (!is_object($module)) {
            throw new RuntimeException("Module '{$moduleName}' is not installed.");
        }
        if (!(bool) $module->getVar('isactive')) {
            throw new RuntimeException("Module '{$moduleName}' is installed but not active.");
        }

        return $module;
    }

    private function describeResolvedHandler(string $moduleName, string $handlerName, object $handler): array
    {
        $sample = $handler->create();
        $declared = $this->normalizeHandlerNames($this->resolveModule($moduleName)->getInfo('object_items'));

        return [
            'module' => $moduleName,
            'handler' => $handlerName,
            'declared_in_module_info' => in_array($handlerName, $declared, true),
            'handler_class' => get_class($handler),
            'object_class' => get_class($sample),
            'key' => property_exists($handler, 'keyName') ? $handler->keyName : null,
            'identifier' => property_exists($handler, 'identifierName') ? $handler->identifierName : null,
            'summary' => property_exists($handler, 'summaryName') ? $handler->summaryName : null,
            'fields' => $this->describeFields($sample),
        ];
    }

    private function buildCriteria(object $handler, array $params): icms_db_criteria_Compo
    {
        $criteria = new icms_db_criteria_Compo();
        $fields = $this->getObjectVars($handler->create());
        $filters = $params['filters'] ?? [];

        if (!is_array($filters)) {
            throw new InvalidArgumentException('filters must be an array.');
        }
        foreach ($filters as $field => $value) {
            $this->validateCriteriaField($field, $fields);
            if (!is_scalar($value) && $value !== null) {
                throw new InvalidArgumentException("Filter '{$field}' must be a scalar value.");
            }
            require_once __DIR__ . '/MCPSafeCriteriaItem.php';
            $criteria->add(new MCPSafeCriteriaItem($field, $value));
        }

        $criteria->setStart(max(0, (int) ($params['start'] ?? 0)));
        $criteria->setLimit(max(0, (int) ($params['limit'] ?? 25)));

        if (isset($params['sort']) && $params['sort'] !== '') {
            $this->validateCriteriaField($params['sort'], $fields);
            $criteria->setSort($params['sort']);
        }
        if (!empty($params['order']) && is_string($params['order'])) {
            $criteria->setOrder(strtoupper($params['order']) === 'DESC' ? 'DESC' : 'ASC');
        }

        return $criteria;
    }

    private function validateCriteriaField(mixed $field, array $fields): void
    {
        if (!is_string($field) || !preg_match('/\A[a-zA-Z_][a-zA-Z0-9_]*\z/D', $field)
            || !array_key_exists($field, $fields)
            || (is_array($fields[$field]) && !($fields[$field]['persistent'] ?? true))) {
            throw new InvalidArgumentException('Unknown or invalid criteria field.');
        }
    }

    private function fetchObject(object $handler, mixed $id): object
    {
        $object = $handler->get($this->normalizeIdentifier($handler, $id));
        if (!is_object($object) || (method_exists($object, 'isNew') && $object->isNew())) {
            throw new RuntimeException('Object not found.');
        }

        return $object;
    }

    private function normalizeIdentifier(object $handler, mixed $id): mixed
    {
        $keyName = property_exists($handler, 'keyName') ? $handler->keyName : null;

        if (is_array($keyName)) {
            if (!is_array($id)) {
                throw new InvalidArgumentException('Composite identifiers must be provided as an array.');
            }

            $resolved = [];
            foreach ($keyName as $keyField) {
                if (!array_key_exists($keyField, $id)) {
                    throw new InvalidArgumentException("Missing identifier field '{$keyField}'.");
                }
                $resolved[] = $id[$keyField];
            }
            return $resolved;
        }

        if (is_array($id)) {
            if ($keyName !== null && array_key_exists($keyName, $id)) {
                return $id[$keyName];
            }
            throw new InvalidArgumentException('Missing object identifier.');
        }
        if ($id === null || $id === '') {
            throw new InvalidArgumentException('Missing object identifier.');
        }

        return $id;
    }

    private function applyDataToObject(object $object, array $data): array
    {
        $vars = $this->getObjectVars($object);
        $keyFields = $this->normalizeKeyFields($object->handler ?? null);
        $applied = [];
        $ignored = [];

        foreach ($data as $field => $value) {
            if (!is_string($field) || $field === '') {
                continue;
            }
            if (!array_key_exists($field, $vars)) {
                $ignored[] = $field;
                continue;
            }

            $fieldInfo = is_array($vars[$field]) ? $vars[$field] : [];
            $isReadonly = (bool) ($fieldInfo['readonly'] ?? false);
            $isPersistent = (bool) ($fieldInfo['persistent'] ?? true);
            if ($isReadonly || !$isPersistent || in_array($field, $keyFields, true)) {
                $ignored[] = $field;
                continue;
            }

            $object->setVar($field, $value);
            $applied[] = $field;
        }

        if ($applied === []) {
            throw new InvalidArgumentException('No writable fields were provided.');
        }

        return ['applied' => $applied, 'ignored' => $ignored];
    }

    private function serializeObject(object $object, ?array $fields = null): array
    {
        $vars = $this->getObjectVars($object);
        $availableFields = array_keys($vars);
        $selectedFields = $fields ?? $availableFields;
        $serialized = [];

        foreach ($selectedFields as $field) {
            if (!in_array($field, $availableFields, true)) {
                throw new InvalidArgumentException("Unknown field '{$field}'.");
            }
            $serialized[$field] = $object->getVar($field, 'n');
        }

        return $serialized;
    }

    private function describeFields(object $object): array
    {
        $fields = [];
        foreach ($this->getObjectVars($object) as $name => $definition) {
            $definition = is_array($definition) ? $definition : [];
            $fields[$name] = [
                'data_type' => $definition['data_type'] ?? null,
                'required' => (bool) ($definition['required'] ?? false),
                'persistent' => (bool) ($definition['persistent'] ?? true),
                'readonly' => (bool) ($definition['readonly'] ?? false),
                'caption' => $definition['form_caption'] ?? '',
            ];
        }

        return $fields;
    }

    private function getObjectVars(object $object): array
    {
        if (method_exists($object, 'getVars')) {
            return (array) $object->getVars();
        }
        if (property_exists($object, 'vars') && is_array($object->vars)) {
            return $object->vars;
        }

        throw new RuntimeException('Handler object does not expose inspectable fields.');
    }

    private function normalizeKeyFields(?object $handler): array
    {
        if ($handler === null || !property_exists($handler, 'keyName')) {
            return [];
        }

        return is_array($handler->keyName) ? $handler->keyName : [$handler->keyName];
    }

    private function normalizeHandlerNames(mixed $objectItems): array
    {
        if (!is_array($objectItems)) {
            return [];
        }

        $handlers = [];
        foreach ($objectItems as $item) {
            if (is_string($item) && $item !== '') {
                $handlers[] = $item;
            }
        }

        sort($handlers);
        return array_values(array_unique($handlers));
    }

    private function normalizeRequestedFields(mixed $fields): ?array
    {
        if ($fields === null) {
            return null;
        }
        if (!is_array($fields)) {
            throw new InvalidArgumentException('fields must be an array.');
        }

        $normalized = [];
        foreach ($fields as $field) {
            if (!is_string($field) || $field === '') {
                throw new InvalidArgumentException('fields must contain only non-empty strings.');
            }
            $normalized[] = $field;
        }

        return array_values(array_unique($normalized));
    }

    private function requireStringParam(array $params, string $name): string
    {
        $value = $params[$name] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("Missing or invalid '{$name}' parameter.");
        }

        return trim($value);
    }

    private function requireArrayParam(array $params, string $name): array
    {
        $value = $params[$name] ?? null;
        if (!is_array($value)) {
            throw new InvalidArgumentException("Missing or invalid '{$name}' parameter.");
        }

        return $value;
    }
}
