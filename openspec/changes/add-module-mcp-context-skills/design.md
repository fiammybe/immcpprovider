## Context

See proposal.md - Why. mcpapi today is purely a generic IPF introspector: `IpfGateway` and `IpfTools` read schema straight from a module's declared `object_items` and its IPF object's `getVars()`. There is no place to attach meaning, relations, or procedural knowledge, and no module is aware mcpapi exists.

mcpapi itself already has a working IPF pattern to extend: legacy `mod_mcpapi_*` handler/object classes (e.g. `ClientHandler`), declared as `object_items` in its own `icms_version.php`, with cross-module calls elsewhere in the codebase made through guarded `icms_getModuleHandler()` lookups (see `ClientHandler::afterDelete()` calling into `accesstoken`). This change follows the same idioms rather than introducing a new integration mechanism.

## Goals / Non-Goals

**Goals:**
- Let a module author hand mcpapi context and relation hints for their own handlers/fields, without a hard dependency on mcpapi.
- Let an mcpapi administrator add site-specific context and skills that no module author could anticipate.
- Keep mcpapi's own `icms_version.php` manifest small; put the actual content in rows, not in manifest data parsed on every page load.
- Never have mcpapi call into another module's install/update code.

**Non-Goals:**
- Validating or executing skill instructions as tool-call sequences (skills are opaque text).
- Resolving conflicts between `source = module` and `source = admin` content (both are shown; no override semantics).
- A generic plugin API for arbitrary MCP primitives beyond context (resources) and skills (prompts).

## Decisions

### Storage: new IPF object types inside mcpapi's own module
`mcpapi_context` and `mcpapi_skill` become two more `object_items` in `src/icms_version.php`, implemented as legacy `mod_mcpapi_ContextHandler`/`mod_mcpapi_SkillHandler` classes under `src/class/`, following the existing `Client`/`ClientHandler` pattern exactly. This reuses `IpfGateway`'s serialization and `AccessPolicy`'s exposure rules instead of inventing a parallel storage/access layer.

Alternative considered: store context/skills as flat files per module (e.g. JSON in each module's own directory). Rejected because it would require mcpapi to read and parse arbitrary module directories on every `describe_handler` call, and would make admin-authored content either impossible (files belong to the module, not the site) or require a second, inconsistent storage mechanism just for admin content.

### `mcpapi_context` shape
Fields: `dirname` (module), `scope` (`module` | `handler` | `field`), `handler` (nullable), `field` (nullable), `description` (text), `relation_module` (nullable), `relation_handler` (nullable), `source` (`module` | `admin`). One row per described thing; relation fields are only meaningful at `scope = field`.

### `mcpapi_skill` shape
Fields: `name`, `title`, `instructions` (text), `dirname` (nullable module). No `source` field is needed because skills only ever come from admins (see proposal decision: skills are not pushed by modules).

### Discovery without side effects: filesystem check, not a code call
mcpapi's `onInstall`/`onUpdate` checks `is_file()` for the convention file (e.g. `modules/<dirname>/include/mcpapi.inc.php`) across active modules, and only reports the list to the installer UI. It never `include`s or calls into that file. The module's own `onInstall`/`onUpdate` is the only place that file is ever executed, and only when that module's own lifecycle runs it - normally, under the webmaster's control.

Alternative considered: mcpapi actively re-running a module's update after its own install. Rejected per explicit instruction - side effects of triggering another module's update are unpredictable and not mcpapi's call to make.

### Push/cleanup contract: guarded `icms_getModuleHandler()`, no new API surface
A module's `onInstall`/`onUpdate` does:
```php
$context = icms_getModuleHandler('context', 'mcpapi', null, true);
if ($context instanceof icms_ipf_Handler) {
    $context->registerForModule('<dirname>', [...]);
}
```
and `onUninstall` does the equivalent `deleteForModule('<dirname>')` call. This mirrors the existing `ClientHandler::afterDelete()` cross-handler call already in the codebase; no SDK, trait, or shared contract class is introduced, since ImpressCMS modules do not share a composer autoload boundary with each other.

### Admin authoring: additive only, gated on mcpapi's own permissions
`source = admin` rows are fully editable through new admin pages; `source = module` rows are read-only there and are replaced wholesale on the next module push. No per-field override flag or merge step exists - the UI simply renders both sets together. This was an explicit choice (see conversation): admins cannot edit a module's own row because any edit would silently vanish on the module's next update, which would be a worse experience than not allowing the edit at all.

Access is `AccessPolicy::canAdministerModule()` evaluated against the **mcpapi module object**, not the described module - skills and context can span modules, so module-level admin rights would be either too narrow (cross-module skills) or inconsistent (which module's admin owns a field-level note).

### Exposure: enrich existing tools, add MCP resources/prompts
- `describe_handler`/`list_modules` gain extra, purely additive output (context text, relations) - no breaking change to existing fields.
- Skills are exposed through the MCP SDK's prompts capability (`addPrompt`/prompts/list/prompts/get) in `ServerFactory`, mirroring how tools are registered today.
- Context could additionally be exposed as MCP resources (resources/list, resources/read) for clients that prefer to browse documentation separately from tool schemas; this is a straightforward addition once the storage and tool-enrichment work lands, left to task breakdown rather than a separate design decision.

## Risks / Trade-offs

- **Stale candidate list**: mcpapi's install-time module scan is a snapshot; a module added or updated after that point won't be re-detected until mcpapi's admin dashboard re-runs the same read-only scan on demand. Mitigation: add a "rescan" action in the admin UI rather than only running it at install/update time.
- **No override means duplicate/conflicting text can appear**: a module's description and an admin's note for the same field are both shown, even if they disagree. Mitigation: this is the explicitly chosen trade-off (simplicity over merge correctness); the admin UI should visually distinguish the two sources so a reader isn't confused about authority.
- **Composite/legacy IPF handlers vary in constructor shape**: `HandlerLocator` already has to cope with multiple naming/instantiation conventions for arbitrary modules' handlers; the new `mcpapi_context`/`mcpapi_skill` handlers avoid this entirely since mcpapi controls their class names directly.
- **Convention file has no enforced contract/version**: a module could ship a file that doesn't match the expected function signature. Mitigation: mcpapi's push-receiving methods (`registerForModule`, `deleteForModule`) validate and reject malformed input defensively, the same way `IpfGateway` already validates tool input; a malformed push must not break the module's own install/update.

## Migration Plan

This is purely additive: new object types, new admin pages, enriched (not changed) tool output. Existing installations of mcpapi gain the new tables on their next module update (via `include/update.php` and a bumped `MCPAPI_DB_VERSION`, per the project's standard IPF schema-change convention). No existing data or API contract changes.
