<?php

defined('ICMS_ROOT_PATH') or die('ICMS root path not defined');

class mod_mcpapi_SkillHandler extends icms_ipf_Handler
{
    public function __construct(&$db)
    {
        parent::__construct($db, 'skill', 'skill_id', 'name', 'title', 'mcpapi');
    }

    public function getByName(string $name): ?icms_ipf_Object
    {
        $criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('name', $name));
        $criteria->setLimit(1);

        $objects = $this->getObjects($criteria);

        return $objects[0] ?? null;
    }

    /** @return array<int, mod_mcpapi_Skill> */
    public function getUnscoped(): array
    {
        $criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('dirname', ''));

        return $this->getObjects($criteria);
    }

    /** @return array<int, mod_mcpapi_Skill> */
    public function getForModule(string $dirname): array
    {
        $criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('dirname', $dirname));

        return $this->getObjects($criteria);
    }
}
