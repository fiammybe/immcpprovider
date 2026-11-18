<?php

defined('ICMS_ROOT_PATH') or die('ICMS root path not defined');

$adminmenu = [];

$adminmenu[0]['title'] = _MI_MCPAPI_MENU_DASHBOARD;
$adminmenu[0]['link'] = 'admin/index.php';

$adminmenu[1]['title'] = _MI_MCPAPI_MENU_CLIENTS;
$adminmenu[1]['link'] = 'admin/client.php';

$adminmenu[2]['title'] = _MI_MCPAPI_MENU_TOKENS;
$adminmenu[2]['link'] = 'admin/accesstoken.php';

$adminmenu[3]['title'] = _MI_MCPAPI_MENU_CONTEXT;
$adminmenu[3]['link'] = 'admin/context.php';

$adminmenu[4]['title'] = _MI_MCPAPI_MENU_SKILLS;
$adminmenu[4]['link'] = 'admin/skill.php';

$headermenu = [];

if (isset(icms::$module)) {
    $headermenu[] = [
        'title' => _CO_ICMS_UPDATE_MODULE,
        'link' => ICMS_MODULES_URL . '/system/admin.php?fct=modulesadmin&amp;op=update&amp;module=' . icms::$module->getVar('dirname'),
    ];
}
