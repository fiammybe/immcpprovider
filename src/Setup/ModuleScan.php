<?php

declare(strict_types=1);

namespace ImMcpProvider\Setup;

use icms;
use icms_db_criteria_Compo;
use icms_db_criteria_Item;

/**
 * Reports which active modules ship the MCP integration convention file, without
 * including or calling into that file. This never runs another module's install,
 * update, or any other code - it only checks whether the file exists on disk, so
 * the webmaster can decide whether to update that module to activate MCP context.
 */
final class ModuleScan
{
    public const CONVENTION_FILE = 'include/mcpapi.inc.php';

    private const EXCLUDED_MODULES = ['system', 'mcpapi'];

    /** @return array<int, array{dirname: string, name: string}> */
    public function candidates(): array
    {
        $criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('isactive', 1));

        $candidates = [];

        foreach (icms::handler('icms_module')->getObjects($criteria) as $module) {
            $dirname = (string) $module->getVar('dirname');

            if (in_array($dirname, self::EXCLUDED_MODULES, true)) {
                continue;
            }

            if (! is_file(ICMS_ROOT_PATH . "/modules/{$dirname}/" . self::CONVENTION_FILE)) {
                continue;
            }

            $candidates[] = [
                'dirname' => $dirname,
                'name' => (string) $module->getVar('name'),
            ];
        }

        return $candidates;
    }
}
