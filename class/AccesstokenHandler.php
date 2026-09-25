<?php

defined('ICMS_ROOT_PATH') or die('ICMS root path not defined');

if (! class_exists('mod_mcpapi_IdentifiedHandler', false)) {
    require_once __DIR__ . '/IdentifiedHandler.php';
}

class mod_mcpapi_AccesstokenHandler extends mod_mcpapi_IdentifiedHandler
{
    public function __construct(&$db)
    {
        parent::__construct($db, 'accesstoken', 'accesstoken_id', 'identifier', '', 'mcpapi');
    }

    public function revokeWithRefreshTokens(string $identifier): void
    {
        $this->revokeByIdentifier($identifier);

        $refreshTokens = icms_getModuleHandler('refreshtoken', 'mcpapi');
        $criteria = new icms_db_criteria_Item('accesstoken_identifier', $identifier);

        foreach ($refreshTokens->getObjects($criteria) as $refreshToken) {
            $refreshTokens->revokeByIdentifier((string) $refreshToken->getVar('identifier', 'n'));
        }
    }

    public function revokeAllForClient(string $clientIdentifier): void
    {
        $criteria = new icms_db_criteria_Item('client_identifier', $clientIdentifier);

        foreach ($this->getObjects($criteria) as $accessToken) {
            $this->revokeWithRefreshTokens((string) $accessToken->getVar('identifier', 'n'));
        }
    }
}
