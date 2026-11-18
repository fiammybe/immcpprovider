<?php

use ImMcpProvider\Module;

include_once __DIR__ . '/header.php';

$handler = Module::identifiedHandler('accesstoken');
$operation = isset($_REQUEST['op']) ? preg_replace('/[^a-z_]/', '', $_REQUEST['op']) : 'list';

if ($operation === 'revoke') {
    $token = $handler->get((int) ($_REQUEST['accesstoken_id'] ?? 0));

    if (is_object($token) && ! $token->isNew()) {
        $handler->revokeWithRefreshTokens((string) $token->getVar('identifier', 'n'));
    }

    redirect_header('accesstoken.php', 2, _AM_MCPAPI_TOKEN_REVOKED);
}

icms_cp_header();
icms::$module->displayAdminMenu(2, _MI_MCPAPI_MENU_TOKENS);

if ($operation === 'del') {
    $controller = new icms_ipf_Controller($handler);
    $controller->handleObjectDeletion(_AM_MCPAPI_TOKEN_DELETE_CONFIRM);
}

$table = new icms_ipf_view_Table($handler, false, ['delete']);
$table->addColumn(new icms_ipf_view_Column('client_identifier', _GLOBAL_LEFT, 200, 'displayClient', false, _AM_MCPAPI_TOKEN_CLIENT));
$table->addColumn(new icms_ipf_view_Column('user_id', _GLOBAL_LEFT, 150, 'displayUser', false, _AM_MCPAPI_TOKEN_USER));
$table->addColumn(new icms_ipf_view_Column('created', 'center', 160, 'displayCreated', false, _AM_MCPAPI_TOKEN_CREATED));
$table->addColumn(new icms_ipf_view_Column('expires', 'center', 160, 'displayExpires', false, _AM_MCPAPI_TOKEN_EXPIRES));
$table->addColumn(new icms_ipf_view_Column('revoked', 'center', 100, 'displayStatus', false, _AM_MCPAPI_TOKEN_STATUS));
$table->addCustomAction('getRevokeLink');
$table->setDefaultSort('created');
$table->setDefaultOrder('DESC');
$table->render();

icms_cp_footer();
