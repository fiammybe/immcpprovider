<?php

use ImMcpProvider\Module;
use ImMcpProvider\Setup\Requirements;
use ImMcpProvider\Support\ActivityLog;

include_once __DIR__ . '/header.php';

icms_cp_header();
icms::$module->displayAdminMenu(0, _MI_MCPAPI_NAME);

Module::purgeExpired();

$activeCriteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('revoked', 0));
$activeCriteria->add(new icms_db_criteria_Item('expires', time(), '>'));

global $icmsAdminTpl;

$icmsAdminTpl->assign('mcpapi', [
    'server_url' => Module::url('api/mcp.php'),
    'resource_metadata_url' => Module::url('oauth/protected-resource.php'),
    'server_metadata_url' => Module::url('oauth/authorization-server.php'),
    'wellknown_url' => Module::url('.well-known/openid-configuration'),
    'checks' => (new Requirements())->checks(),
    'clients' => Module::identifiedHandler('client')->getCount(),
    'active_tokens' => Module::identifiedHandler('accesstoken')->getCount($activeCriteria),
]);
$icmsAdminTpl->assign('mcpapi_lang', [
    'connect' => _AM_MCPAPI_DASHBOARD_CONNECT,
    'connect_help' => _AM_MCPAPI_DASHBOARD_CONNECT_HELP,
    'server_url' => _AM_MCPAPI_DASHBOARD_SERVER_URL,
    'discovery' => _AM_MCPAPI_DASHBOARD_DISCOVERY,
    'resource_metadata' => _AM_MCPAPI_DASHBOARD_RESOURCE_METADATA,
    'server_metadata' => _AM_MCPAPI_DASHBOARD_SERVER_METADATA,
    'wellknown' => _AM_MCPAPI_DASHBOARD_WELLKNOWN,
    'checks' => _AM_MCPAPI_DASHBOARD_CHECKS,
    'stats' => _AM_MCPAPI_DASHBOARD_STATS,
    'clients' => _AM_MCPAPI_DASHBOARD_CLIENTS,
    'active_tokens' => _AM_MCPAPI_DASHBOARD_ACTIVE_TOKENS,
    'permissions' => _AM_MCPAPI_DASHBOARD_PERMISSIONS,
    'permissions_help' => _AM_MCPAPI_DASHBOARD_PERMISSIONS_HELP,
    'ok' => _AM_MCPAPI_CHECK_OK,
    'failed' => _AM_MCPAPI_CHECK_FAILED,
]);
$icmsAdminTpl->display('db:mcpapi_admin_index.html');

echo '<h3>' . _AM_MCPAPI_DASHBOARD_ACTIVITY . '</h3>';
echo '<pre style="max-height: 320px; overflow: auto; font-size: 11px; white-space: pre-wrap;">';
echo htmlspecialchars(implode(PHP_EOL, array_reverse(ActivityLog::tail(60))), ENT_QUOTES);
echo '</pre>';

icms_cp_footer();
