<?php

defined('ICMS_ROOT_PATH') or die('ICMS root path not defined');

class mod_mcpapi_Client extends icms_ipf_Object
{
    public function __construct(&$handler)
    {
        parent::__construct($handler);

        $this->quickInitVar('client_id', XOBJ_DTYPE_INT, true);
        $this->quickInitVar('identifier', XOBJ_DTYPE_TXTBOX, true);
        $this->quickInitVar('name', XOBJ_DTYPE_TXTBOX, true);
        $this->quickInitVar('redirect_uris', XOBJ_DTYPE_SOURCE, true);
        $this->quickInitVar('secret_hash', XOBJ_DTYPE_TXTBOX, false);
        $this->quickInitVar('created', XOBJ_DTYPE_INT, false, '', '', 0);
    }

    public function displayName(): string
    {
        return htmlspecialchars((string) $this->getVar('name', 'n'), ENT_QUOTES);
    }

    /** @return array<int, string> */
    public function getRedirectUris(): array
    {
        $stored = html_entity_decode((string) $this->getVar('redirect_uris', 'n'), ENT_QUOTES | ENT_HTML5);
        $uris = json_decode($stored, true);

        if (! is_array($uris)) {
            return [];
        }

        return array_values(array_filter($uris, 'is_string'));
    }

    public function displayRedirectUris(): string
    {
        return implode('<br />', array_map(
            static fn (string $uri): string => htmlspecialchars($uri, ENT_QUOTES),
            $this->getRedirectUris()
        ));
    }

    public function displayCreated(): string
    {
        return formatTimestamp((int) $this->getVar('created', 'n'), 'm');
    }
}
