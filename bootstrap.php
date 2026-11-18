<?php

declare(strict_types=1);

$scriptPath = $_SERVER['SCRIPT_FILENAME'] ?? '';
$siteRoot = $scriptPath !== '' ? dirname($scriptPath, 4) : __DIR__ . '/../..';

require_once $siteRoot . '/mainfile.php';
require_once __DIR__ . '/vendor/autoload.php';

register_shutdown_function(static function (): void {
    $error = error_get_last();
    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR];

    if ($error === null || ! in_array($error['type'], $fatal, true)) {
        return;
    }

    ImMcpProvider\Support\ActivityLog::record(sprintf(
        'FATAL %s in %s:%d',
        $error['message'],
        basename($error['file']),
        $error['line']
    ));
});
