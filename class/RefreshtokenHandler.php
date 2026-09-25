<?php

defined('ICMS_ROOT_PATH') or die('ICMS root path not defined');

if (! class_exists('mod_mcpapi_IdentifiedHandler', false)) {
    require_once __DIR__ . '/IdentifiedHandler.php';
}

class mod_mcpapi_RefreshtokenHandler extends mod_mcpapi_IdentifiedHandler
{
    public function __construct(&$db)
    {
        parent::__construct($db, 'refreshtoken', 'refreshtoken_id', 'identifier', '', 'mcpapi');
    }
}
