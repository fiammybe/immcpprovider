<?php

defined('ICMS_ROOT_PATH') or die('ICMS root path not defined');

class mod_mcpapi_ContextHandler extends icms_ipf_Handler
{
    public function __construct(&$db)
    {
        parent::__construct($db, 'context', 'context_id', 'description', '', 'mcpapi');
    }

    /**
     * Replaces every `source = module` entry for `$dirname` with the newly pushed set.
     * `source = admin` entries for the same module are never touched. Has no effect, and
     * never throws, when $entries is empty or malformed - a module's own install/update
     * must never fail because of a bad push.
     *
     * @param array<int, array<string, mixed>> $entries
     */
    public function registerForModule(string $dirname, array $entries): void
    {
        $dirname = trim($dirname);

        if ($dirname === '') {
            return;
        }

        $this->deleteForModule($dirname, mod_mcpapi_Context::SOURCE_MODULE);

        foreach ($entries as $entry) {
            $this->insertEntry($dirname, $entry);
        }
    }

    /** Removes every `source = module` entry for `$dirname`. `source = admin` entries are left in place. */
    public function deleteForModule(string $dirname, string $source = mod_mcpapi_Context::SOURCE_MODULE): void
    {
        $dirname = trim($dirname);

        if ($dirname === '') {
            return;
        }

        $criteria = new icms_db_criteria_Compo();
        $criteria->add(new icms_db_criteria_Item('dirname', $dirname));
        $criteria->add(new icms_db_criteria_Item('source', $source));

        $this->deleteAll($criteria);
    }

    /** @return array<int, mod_mcpapi_Context> */
    public function getForModule(string $dirname): array
    {
        $criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('dirname', $dirname));

        return $this->getObjects($criteria);
    }

    /** @param array<string, mixed> $entry */
    private function insertEntry(string $dirname, mixed $entry): void
    {
        if (! is_array($entry)) {
            return;
        }

        $scope = (string) ($entry['scope'] ?? mod_mcpapi_Context::SCOPE_MODULE);

        if (! in_array($scope, [mod_mcpapi_Context::SCOPE_MODULE, mod_mcpapi_Context::SCOPE_HANDLER, mod_mcpapi_Context::SCOPE_FIELD], true)) {
            return;
        }

        $description = trim((string) ($entry['description'] ?? ''));
        $relationModule = trim((string) ($entry['relation_module'] ?? ''));
        $relationHandler = trim((string) ($entry['relation_handler'] ?? ''));

        if ($description === '' && ($relationModule === '' || $relationHandler === '')) {
            return;
        }

        $object = $this->create();
        $object->setVar('dirname', $dirname);
        $object->setVar('scope', $scope);
        $object->setVar('handler', (string) ($entry['handler'] ?? ''));
        $object->setVar('field', (string) ($entry['field'] ?? ''));
        $object->setVar('description', $description);
        $object->setVar('relation_module', $relationModule);
        $object->setVar('relation_handler', $relationHandler);
        $object->setVar('source', mod_mcpapi_Context::SOURCE_MODULE);

        $this->insert($object, true);
    }
}
