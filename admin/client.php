<?php

use ImMcpProvider\Module;

include_once __DIR__ . '/header.php';

$handler = Module::identifiedHandler('client');
$operation = isset($_REQUEST['op']) ? preg_replace('/[^a-z_]/', '', $_REQUEST['op']) : 'list';

icms_cp_header();
icms::$module->displayAdminMenu(1, _MI_MCPAPI_MENU_CLIENTS);

if ($operation === 'del') {
    $controller = new icms_ipf_Controller($handler);
    $controller->handleObjectDeletion(_AM_MCPAPI_CLIENT_DELETE_CONFIRM);
}

$table = new icms_ipf_view_Table($handler, false, ['delete']);
$table->addColumn(new icms_ipf_view_Column('name', _GLOBAL_LEFT, 200, 'displayName', false, _AM_MCPAPI_CLIENT_NAME));
$table->addColumn(new icms_ipf_view_Column('redirect_uris', _GLOBAL_LEFT, false, 'displayRedirectUris', false, _AM_MCPAPI_CLIENT_REDIRECTS, false));
$table->addColumn(new icms_ipf_view_Column('created', 'center', 160, 'displayCreated', false, _AM_MCPAPI_CLIENT_CREATED));
$table->setDefaultSort('created');
$table->setDefaultOrder('DESC');
$table->render();

icms_cp_footer();
