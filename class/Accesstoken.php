<?php

defined('ICMS_ROOT_PATH') or die('ICMS root path not defined');

class mod_mcpapi_Accesstoken extends icms_ipf_Object
{
    public function __construct(&$handler)
    {
        parent::__construct($handler);

        $this->quickInitVar('accesstoken_id', XOBJ_DTYPE_INT, true);
        $this->quickInitVar('identifier', XOBJ_DTYPE_TXTBOX, true);
        $this->quickInitVar('client_identifier', XOBJ_DTYPE_TXTBOX, true);
        $this->quickInitVar('user_id', XOBJ_DTYPE_INT, true);
        $this->quickInitVar('scopes', XOBJ_DTYPE_TXTBOX, false);
        $this->quickInitVar('expires', XOBJ_DTYPE_INT, true);
        $this->quickInitVar('revoked', XOBJ_DTYPE_INT, false, '', '', 0);
        $this->quickInitVar('created', XOBJ_DTYPE_INT, false, '', '', 0);
    }

    public function displayClient(): string
    {
        $name = icms_getModuleHandler('client', 'mcpapi')->getNameByIdentifier((string) $this->getVar('client_identifier', 'n'));

        return htmlspecialchars($name, ENT_QUOTES);
    }

    public function displayUser(): string
    {
        $user = icms::handler('icms_member')->getUser((int) $this->getVar('user_id', 'n'));

        if (! is_object($user)) {
            return '#' . (int) $this->getVar('user_id', 'n');
        }

        return htmlspecialchars((string) $user->getVar('uname'), ENT_QUOTES);
    }

    public function displayCreated(): string
    {
        return formatTimestamp((int) $this->getVar('created', 'n'), 'm');
    }

    public function displayExpires(): string
    {
        return formatTimestamp((int) $this->getVar('expires', 'n'), 'm');
    }

    public function displayStatus(): string
    {
        if ((bool) $this->getVar('revoked', 'n')) {
            return _AM_MCPAPI_STATUS_REVOKED;
        }

        if ((int) $this->getVar('expires', 'n') < time()) {
            return _AM_MCPAPI_STATUS_EXPIRED;
        }

        return _AM_MCPAPI_STATUS_ACTIVE;
    }

    public function getRevokeLink(): string
    {
        if ((bool) $this->getVar('revoked', 'n')) {
            return '';
        }

        $url = 'accesstoken.php?op=revoke&amp;accesstoken_id=' . (int) $this->getVar('accesstoken_id', 'n');

        return '<a href="' . $url . '" title="' . _AM_MCPAPI_REVOKE . '">' . _AM_MCPAPI_REVOKE . '</a>';
    }
}
