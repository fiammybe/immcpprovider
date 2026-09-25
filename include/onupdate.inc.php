<?php

defined('ICMS_ROOT_PATH') or die('ICMS root path not defined');

require_once __DIR__ . '/oninstall.inc.php';

function icms_module_update_mcpapi($module, $previousVersion = null, $databaseVersion = null): bool
{
    return mcpapi_verifySetup($module);
}
