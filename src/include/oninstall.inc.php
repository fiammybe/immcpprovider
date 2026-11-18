<?php

defined('ICMS_ROOT_PATH') or die('ICMS root path not defined');

defined('MCPAPI_DB_VERSION') or define('MCPAPI_DB_VERSION', 1);

function mcpapi_verifySetup($module): bool
{
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';

    if (! is_file($autoload)) {
        $module->setErrors('The vendor/ directory is missing. Run "composer install --no-dev" inside the module directory, or use a release archive that includes vendor/.');

        return false;
    }

    require_once $autoload;

    $failures = (new ImMcpProvider\Setup\Requirements())->failures();

    foreach ($failures as $failure) {
        $module->setErrors($failure);
    }

    return $failures === [];
}

function icms_module_install_mcpapi($module): bool
{
    return mcpapi_verifySetup($module);
}
