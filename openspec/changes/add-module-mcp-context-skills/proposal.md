## Why

The MCP tools in `list_modules` and `describe_handler` only expose raw IPF schema (data type, required, persistent, caption). An MCP client has no way to learn a handler's business meaning, how its fields relate to other handlers (foreign keys), or the correct multi-step procedure for a site-specific task. Module authors hold this knowledge but have no way to hand it to the generic provider, and site administrators who layer business rules on top of a module have no way to record them at all.

## What Changes

- Two new IPF object types owned by mcpapi itself: `mcpapi_context` (handler/field descriptions and handler-to-handler relations) and `mcpapi_skill` (named, free-text instructions for a procedure). Both carry a `source` of `module` or `admin`.
- A documented, optional convention file a module may ship (e.g. `include/mcpapi.inc.php`) that a module's own `onInstall`/`onUpdate` calls, through a guarded `icms_getModuleHandler()` lookup, to push its `source = module` rows into mcpapi. No effect when mcpapi is not installed.
- A guarded cleanup call from a module's own `onUninstall` that removes its `source = module` rows from mcpapi.
- mcpapi's own `onInstall`/`onUpdate` performs a read-only filesystem scan for that convention file across active modules and reports which modules could supply MCP context if the webmaster updates them. mcpapi never calls another module's install or update routine itself.
- An mcpapi admin section, gated by `AccessPolicy::canAdministerModule()` against the mcpapi module itself, where an administrator can create, edit and delete `source = admin` context rows and skills for any module. `source = module` rows are read-only in this UI, since a module update replaces them wholesale. Both sources are shown side by side with no merge: admin rows only ever add alongside module rows, never override or hide them.
- `describe_handler` (and `list_modules`) include the stored context and relation data for fields and handlers, scoped by the existing module-exposure rules.
- Skills are exposed as MCP prompts (plain instruction text, no structured tool-call sequence) alongside the existing tools, listable and readable by MCP clients under the same module-exposure rules.

## Capabilities

### New Capabilities
- `module-context`: storage, module-push/admin-authored context and relation metadata for IPF handlers and fields, and its exposure through `describe_handler`/`list_modules` and MCP resources.
- `module-skills`: storage and admin authoring of named free-text skill instructions, and their exposure as MCP prompts.

### Modified Capabilities
- (none — `describe_handler`/`list_modules` gain additional optional output fields, not a change to existing requirements)

## Impact

- `src/Ipf/IpfGateway.php`, `src/Tool/IpfTools.php`, `src/ServerFactory.php`: enrich `describe_handler`/`list_modules` output; register MCP resources and prompts.
- `src/icms_version.php`: two new `object_items` entries (`context`, `skill`) and their IPF handler/object classes under `src/class/`.
- `src/include/oninstall.inc.php`, `src/include/onupdate.inc.php`: add the read-only module scan and surface its result to the installer.
- New admin pages under `src/admin/` for managing `source = admin` context rows and skills, gated on mcpapi's own module permissions.
- Documentation: the convention file name/signature, and guidance for module authors on how to push and clean up their own rows.
- No changes to `src/Security/AccessPolicy.php` beyond reusing `canAdministerModule()` against the mcpapi module object.
