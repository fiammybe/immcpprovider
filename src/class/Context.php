<?php

defined('ICMS_ROOT_PATH') or die('ICMS root path not defined');

class mod_mcpapi_Context extends icms_ipf_Object
{
    public const SCOPE_MODULE = 'module';

    public const SCOPE_HANDLER = 'handler';

    public const SCOPE_FIELD = 'field';

    public const SOURCE_MODULE = 'module';

    public const SOURCE_ADMIN = 'admin';

    public function __construct(&$handler)
    {
        parent::__construct($handler);

        $this->quickInitVar('context_id', XOBJ_DTYPE_INT, true);
        $this->quickInitVar('dirname', XOBJ_DTYPE_TXTBOX, true);
        $this->quickInitVar('scope', XOBJ_DTYPE_TXTBOX, true, '', '', self::SCOPE_MODULE);
        $this->quickInitVar('handler', XOBJ_DTYPE_TXTBOX, false);
        $this->quickInitVar('field', XOBJ_DTYPE_TXTBOX, false);
        // XOBJ_DTYPE_SOURCE, not XOBJ_DTYPE_TXTAREA: this is plain text, and TXTAREA would
        // run it through the core's HTML purifier on save, appending a "filtered" marker.
        $this->quickInitVar('description', XOBJ_DTYPE_SOURCE, false);
        $this->quickInitVar('relation_module', XOBJ_DTYPE_TXTBOX, false);
        $this->quickInitVar('relation_handler', XOBJ_DTYPE_TXTBOX, false);
        $this->quickInitVar('source', XOBJ_DTYPE_TXTBOX, true, '', '', self::SOURCE_ADMIN);
    }

    public function isFromModule(): bool
    {
        return $this->getVar('source', 'n') === self::SOURCE_MODULE;
    }

    /** @return array<string, mixed> */
    public function toMcpArray(): array
    {
        $relationModule = (string) $this->getVar('relation_module', 'n');
        $relationHandler = (string) $this->getVar('relation_handler', 'n');

        $data = [
            'description' => (string) $this->getVar('description', 'n'),
            'source' => (string) $this->getVar('source', 'n'),
        ];

        if ($relationModule !== '' && $relationHandler !== '') {
            $data['relation'] = [
                'module' => $relationModule,
                'handler' => $relationHandler,
            ];
        }

        return $data;
    }
}
