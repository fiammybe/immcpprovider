<?php

defined('ICMS_ROOT_PATH') or die('ICMS root path not defined');

if (! class_exists('mod_mcpapi_IdentifiedHandler', false)) {
    require_once __DIR__ . '/IdentifiedHandler.php';
}

class mod_mcpapi_ClientHandler extends mod_mcpapi_IdentifiedHandler
{
    public function __construct(&$db)
    {
        parent::__construct($db, 'client', 'client_id', 'name', '', 'mcpapi');
    }

    public function afterDelete(&$client)
    {
        icms_getModuleHandler('accesstoken', 'mcpapi')->revokeAllForClient((string) $client->getVar('identifier', 'n'));

        return true;
    }

    public function getNameByIdentifier(string $identifier): string
    {
        $client = $this->getByIdentifier($identifier);

        if ($client === null) {
            return $identifier;
        }

        return (string) $client->getVar('name', 'n');
    }
}
