<?php

defined('ICMS_ROOT_PATH') or die('ICMS root path not defined');

abstract class mod_mcpapi_IdentifiedHandler extends icms_ipf_Handler
{
    public function getByIdentifier(string $identifier): ?icms_ipf_Object
    {
        $criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('identifier', $identifier));
        $criteria->setLimit(1);

        $objects = $this->getObjects($criteria);

        return $objects[0] ?? null;
    }

    public function revokeByIdentifier(string $identifier): void
    {
        $object = $this->getByIdentifier($identifier);

        if ($object === null) {
            return;
        }

        $object->setVar('revoked', 1);

        $this->insert($object, true);
    }

    public function isRevoked(string $identifier): bool
    {
        $object = $this->getByIdentifier($identifier);

        if ($object === null) {
            return true;
        }

        return (bool) $object->getVar('revoked', 'n');
    }

    public function deleteExpired(int $now): void
    {
        $this->deleteAll(new icms_db_criteria_Item('expires', $now, '<'));
    }
}
