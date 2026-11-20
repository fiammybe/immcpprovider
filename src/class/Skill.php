<?php

defined('ICMS_ROOT_PATH') or die('ICMS root path not defined');

class mod_mcpapi_Skill extends icms_ipf_Object
{
    public function __construct(&$handler)
    {
        parent::__construct($handler);

        $this->quickInitVar('skill_id', XOBJ_DTYPE_INT, true);
        $this->quickInitVar('name', XOBJ_DTYPE_TXTBOX, true);
        $this->quickInitVar('title', XOBJ_DTYPE_TXTBOX, true);
        // XOBJ_DTYPE_SOURCE, not XOBJ_DTYPE_TXTAREA: this is plain text, and TXTAREA would
        // run it through the core's HTML purifier on save, appending a "filtered" marker.
        $this->quickInitVar('instructions', XOBJ_DTYPE_SOURCE, true);
        $this->quickInitVar('dirname', XOBJ_DTYPE_TXTBOX, false);
    }

    public function isModuleScoped(): bool
    {
        return (string) $this->getVar('dirname', 'n') !== '';
    }

    /** @return array<string, string> */
    public function toMcpPromptMessage(): array
    {
        return [
            'role' => 'user',
            'content' => (string) $this->getVar('instructions', 'n'),
        ];
    }
}
