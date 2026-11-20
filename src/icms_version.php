<?php

defined('ICMS_ROOT_PATH') or die('ICMS root path not defined');

$modversion = [
    'name' => _MI_MCPAPI_NAME,
    'version' => '0.6.1',
    'description' => _MI_MCPAPI_DESC,
    'author' => 'David Janssens',
    'license' => 'Copyright (c) David Janssens',
    'official' => 0,
    'dirname' => 'mcpapi',
    'modname' => 'mcpapi',

    // Keeps this module's own data (clients, tokens) out of the tools.
    'mcpapi_exposed' => false,

    'iconsmall' => 'assets/image/icon_small.png',
    'iconbig' => 'assets/image/logo.png',
    'image' => 'assets/image/logo.png',

    'status_version' => '0.6.1',
    'status' => 'Beta',

    'hasAdmin' => 1,
    'adminindex' => 'admin/index.php',
    'adminmenu' => 'admin/menu.php',

    'onInstall' => 'include/oninstall.inc.php',
    'onUpdate' => 'include/onupdate.inc.php',
    'onUninstall' => 'include/onuninstall.inc.php',

    'hasMain' => 0,
    'hasSearch' => 0,
    'hasComments' => 0,
    'hasNotification' => 0,
];

$modversion['object_items'][1] = 'client';
$modversion['object_items'][2] = 'accesstoken';
$modversion['object_items'][3] = 'refreshtoken';
$modversion['object_items'][4] = 'authcode';

$modversion['tables'] = icms_getTablesArray($modversion['dirname'], $modversion['object_items']);

$modversion['templates'] = [
    [
        'file' => 'mcpapi_authorize.html',
        'description' => 'Consent page shown when an MCP client asks to connect.',
    ],
    [
        'file' => 'mcpapi_admin_index.html',
        'description' => 'Administration dashboard.',
    ],
];
