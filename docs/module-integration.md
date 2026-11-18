# Supplying MCP context from a module

A module can hand mcpapi field and handler descriptions, and relations between
handlers, so MCP clients see more than raw IPF schema when they call
`describe_handler` or `list_modules`. This needs no dependency on mcpapi: the
calls below do nothing when mcpapi is not installed or not active.

mcpapi never includes or calls your module's code on its own. The only thing
it ever does with the file below is check, with `is_file()`, whether it
exists - during mcpapi's own install or update - to tell the webmaster which
installed modules could supply MCP context if they are updated. Your module's
own install, update and uninstall routines are the only place this file is
ever executed, same as any other optional integration you already write for
this site (compare `include/comment.inc.php`).

## 1. Ship the convention file

Create `include/mcpapi.inc.php` in your module, defining one function that
returns your context entries:

```php
<?php

defined('ICMS_ROOT_PATH') or die('ICMS root path not defined');

function imforum_mcpapi_context(): array
{
    return [
        [
            'scope' => 'handler',
            'handler' => 'post',
            'description' => 'A single message in a forum thread.',
        ],
        [
            'scope' => 'field',
            'handler' => 'post',
            'field' => 'topic_id',
            'description' => 'The thread this post belongs to.',
            'relation_module' => 'imforum',
            'relation_handler' => 'topic',
        ],
    ];
}
```

Each entry has a `scope` of `module`, `handler` or `field`, an optional
`handler`/`field`, a `description`, and - only meaningful at `scope: field` -
an optional `relation_module`/`relation_handler` pointing at the handler the
field's value refers to. Malformed entries are skipped by mcpapi; they never
cause your module's install or update to fail.

## 2. Push on install and update

In your module's own `include/oninstall.inc.php` and `include/onupdate.inc.php`:

```php
require_once __DIR__ . '/mcpapi.inc.php';

function imforum_mcpapi_push(): void
{
    $context = icms_getModuleHandler('context', 'mcpapi', null, true);

    if (! $context instanceof icms_ipf_Handler) {
        return;
    }

    $context->registerForModule('imforum', imforum_mcpapi_context());
}

function icms_module_install_imforum($module): bool
{
    // ... your existing install logic ...

    imforum_mcpapi_push();

    return true;
}
```

`registerForModule()` replaces every entry your module previously pushed; it
never touches context an administrator added through mcpapi's own admin
pages.

## 3. Clean up on uninstall

In `include/onuninstall.inc.php`:

```php
function icms_module_uninstall_imforum($module): bool
{
    $context = icms_getModuleHandler('context', 'mcpapi', null, true);

    if ($context instanceof icms_ipf_Handler) {
        $context->deleteForModule('imforum');
    }

    // ... your existing uninstall logic ...

    return true;
}
```

## Skills are not pushed by modules

Skills (named, free-text procedures for a site-specific task) are authored
only by a site administrator in mcpapi's own admin pages, since they often
encode decisions specific to one site or spanning several modules. There is
no push mechanism for them, and nothing for a module to ship.
