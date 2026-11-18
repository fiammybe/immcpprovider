<?php

defined('ICMS_ROOT_PATH') or die('ICMS root path not defined');

function mcpapi_removeDirectory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    foreach (scandir($directory) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = "{$directory}/{$entry}";

        is_dir($path) ? mcpapi_removeDirectory($path) : unlink($path);
    }

    rmdir($directory);
}

function icms_module_uninstall_mcpapi($module): bool
{
    mcpapi_removeDirectory(ICMS_TRUST_PATH . '/mcpapi');
    mcpapi_removeDirectory(ICMS_CACHE_PATH . '/mcpapi');

    return true;
}
