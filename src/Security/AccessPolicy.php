<?php

declare(strict_types=1);

namespace ImMcpProvider\Security;

use icms;
use icms_ipf_Handler;
use icms_ipf_Object;
use icms_ipf_permission_Handler;
use icms_module_Object;

final class AccessPolicy
{
    private const VIEW_PERMISSION = 'view';

    /** @return array<int, int> */
    public function groupIds(): array
    {
        if (! is_object(icms::$user)) {
            return [ICMS_GROUP_ANONYMOUS];
        }

        return icms::$user->getGroups();
    }

    public function canReadModule(icms_module_Object $module): bool
    {
        return icms::handler('icms_member_groupperm')->checkRight(
            'module_read',
            (int) $module->getVar('mid'),
            $this->groupIds()
        );
    }

    public function canAdministerModule(icms_module_Object $module): bool
    {
        if (! is_object(icms::$user)) {
            return false;
        }

        return (bool) icms::$user->isAdmin((int) $module->getVar('mid'));
    }

    public function restrictsViewing(icms_ipf_Handler $handler, icms_module_Object $module): bool
    {
        if ($this->canAdministerModule($module)) {
            return false;
        }

        return $this->declaresViewPermission($handler);
    }

    public function canViewObject(icms_ipf_Handler $handler, icms_module_Object $module, icms_ipf_Object $object): bool
    {
        if (! $this->restrictsViewing($handler, $module)) {
            return true;
        }

        if (! is_string($handler->keyName)) {
            return false;
        }

        $permissions = new icms_ipf_permission_Handler($handler);

        return $permissions->accessGranted(self::VIEW_PERMISSION, (int) $object->getVar($handler->keyName));
    }

    public function restrictCriteriaToViewable(icms_ipf_Handler $handler, icms_module_Object $module, object $criteria): bool
    {
        if (! $this->restrictsViewing($handler, $module)) {
            return true;
        }

        return (bool) $handler->setGrantedObjectsCriteria($criteria, self::VIEW_PERMISSION);
    }

    private function declaresViewPermission(icms_ipf_Handler $handler): bool
    {
        foreach ($handler->getPermissions() as $permission) {
            if (($permission['perm_name'] ?? null) === self::VIEW_PERMISSION) {
                return true;
            }
        }

        return false;
    }
}
