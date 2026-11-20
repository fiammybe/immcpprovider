<?php

declare(strict_types=1);

namespace ImMcpProvider\Tool;

use ImMcpProvider\Module;
use ImMcpProvider\Security\AccessPolicy;
use icms;
use icms_db_criteria_Compo;
use mod_mcpapi_Skill;

final class SkillTools
{
    public function __construct(private readonly AccessPolicy $policy = new AccessPolicy())
    {
    }

    /**
     * Skills visible to the current caller: every skill with no module (site-wide),
     * and module-scoped skills only for modules the caller can read.
     *
     * @return array<int, mod_mcpapi_Skill>
     */
    public function visibleSkills(): array
    {
        $handler = Module::handler('skill');
        $skills = [];

        foreach ($handler->getObjects(new icms_db_criteria_Compo()) as $skill) {
            if (! $skill instanceof mod_mcpapi_Skill) {
                continue;
            }

            if ($skill->isModuleScoped() && ! $this->canReadModule((string) $skill->getVar('dirname', 'n'))) {
                continue;
            }

            $skills[] = $skill;
        }

        return $skills;
    }

    private function canReadModule(string $dirname): bool
    {
        $module = icms::handler('icms_module')->getByDirname($dirname);

        if (! is_object($module) || ! (bool) $module->getVar('isactive')) {
            return false;
        }

        return $this->policy->canReadModule($module);
    }
}
