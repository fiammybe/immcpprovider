<?php

defined('ICMS_ROOT_PATH') or die('ICMS root path not defined');

class mod_mcpapi_Refreshtoken extends icms_ipf_Object
{
    public function __construct(&$handler)
    {
        parent::__construct($handler);

        $this->quickInitVar('refreshtoken_id', XOBJ_DTYPE_INT, true);
        $this->quickInitVar('identifier', XOBJ_DTYPE_TXTBOX, true);
        $this->quickInitVar('accesstoken_identifier', XOBJ_DTYPE_TXTBOX, true);
        $this->quickInitVar('expires', XOBJ_DTYPE_INT, true);
        $this->quickInitVar('revoked', XOBJ_DTYPE_INT, false, '', '', 0);
        $this->quickInitVar('created', XOBJ_DTYPE_INT, false, '', '', 0);
    }
}
