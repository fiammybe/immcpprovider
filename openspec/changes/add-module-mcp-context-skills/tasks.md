## 1. Storage: `mcpapi_context`

- [x] 1.1 Add `context` to `object_items` in `src/icms_version.php`, keyed with the fields from design.md (`dirname`, `scope`, `handler`, `field`, `description`, `relation_module`, `relation_handler`, `source`).
- [x] 1.2 Add `src/class/Context.php` (object) and `src/class/ContextHandler.php` (handler), following the `Client`/`ClientHandler` pattern.
- [x] 1.3 Implement `ContextHandler::registerForModule(string $dirname, array $entries)`: deletes existing `source = module` rows for `$dirname`, inserts the new set; validates/rejects malformed entries without throwing into the caller's install/update.
- [x] 1.4 Implement `ContextHandler::deleteForModule(string $dirname)`: deletes `source = module` rows for `$dirname` only.
- [x] 1.5 Bump `MCPAPI_DB_VERSION`. No `include/update.php` was needed: ImpressCMS's `icms_db_legacy_updater_Handler::automaticUpgrade()` already derives each object_item's table from its IPF `quickInitVar` definitions on every module update, so the two new tables are created automatically once `context`/`skill` are declared in `object_items` - confirmed by reading that core class.

## 2. Storage: `mcpapi_skill`

- [x] 2.1 Add `skill` to `object_items` in `src/icms_version.php`, with fields `name`, `title`, `instructions`, `dirname` (nullable).
- [x] 2.2 Add `src/class/Skill.php` and `src/class/SkillHandler.php`, following the same handler pattern.
- [x] 2.3 Covered by the same automatic table creation as task 1.5; no separate entry needed.

## 3. Module-side push/cleanup contract

- [x] 3.1 Document the convention file (name, location, expected function signature) for module authors, including the guarded `icms_getModuleHandler('context', 'mcpapi', null, true)` call pattern from design.md. See `docs/module-integration.md`.
- [x] 3.2 Add a worked example convention file showing push on `onInstall`/`onUpdate` and cleanup on `onUninstall`. Included in `docs/module-integration.md`.

## 4. Read-only module scan

- [x] 4.1 Add a read-only scan (`src/Setup/ModuleScan.php`) that checks `is_file()` for the convention file across active modules (no `include`, no call into module code).
- [x] 4.2 Surface the scan result on the admin dashboard (`src/admin/index.php` / `mcpapi_admin_index.html`) rather than via `$module->setInfo()`: the scan is cheap and always computed live, so the list is never a stale snapshot from install/update time - this is a deliberate simplification over the original plan, removing the "stale candidate list" risk noted in design.md.
- [x] 4.3 A separate "rescan" action turned out to be unnecessary: since the dashboard always recomputes the scan live, every view of it is already a rescan.

## 5. Admin UI for admin-authored context and skills

- [x] 5.1 Add an admin page under `src/admin/` (`context.php`) listing context entries, showing `source = module` rows read-only and `source = admin` rows with a delete action.
- [x] 5.2 Add a create form for `source = admin` context entries (module, scope, optional handler/field, description, optional relation target).
- [x] 5.3 Add an admin page under `src/admin/` (`skill.php`) for listing, creating, and deleting skills (name, title, instructions, optional module scope).
- [x] 5.4 Both admin pages use the same `header.php`/`icms_cp_header()` flow as the existing `client.php`/`accesstoken.php` pages, which already requires administrator rights on the mcpapi module (`AccessPolicy::canAdministerModule()`'s underlying check) to reach any page under `modules/mcpapi/admin/`; no separate check was needed.
- [x] 5.5 Add the new admin pages to `src/admin/menu.php`.

## 6. Exposure through existing tools

- [x] 6.1 Extend `IpfGateway::describeHandler()` to merge stored context entries (both sources) into each field's description and into the handler-level description, including any recorded relation.
- [x] 6.2 Extend `IpfGateway::listModules()` to include module-level context where present.
- [x] 6.3 Confirmed: no signature changes were needed in `IpfTools`/`ServerFactory` for this part.

## 7. Exposure as MCP prompts (skills)

- [x] 7.1 Add `Tool/SkillTools.php` that lists skills via the new `SkillHandler`, filtering module-scoped skills by `AccessPolicy::canReadModule()` and always including unscoped skills.
- [x] 7.2 Register one MCP prompt per visible skill in `ServerFactory::createServer()`, via the SDK's `Builder::addPrompt()` (confirmed against the `modelcontextprotocol/php-sdk` source, which has no built-in "dynamic catalog" prompt type - so each skill becomes one `addPrompt()` call per server build, which already happens per request).

## 8. Tests and verification

- [x] 8.1 `php -l` every new/changed file - all clean.
- [ ] 8.2 Manually verify on a local Herd ImpressCMS site: install mcpapi fresh with a module that already ships the convention file, confirm the candidate-module notice appears and no module code runs during mcpapi's own install.
- [ ] 8.3 Manually verify: update that module afterwards, confirm its context rows appear via `describe_handler`.
- [ ] 8.4 Manually verify: uninstall that module, confirm its `source = module` rows are gone and any `source = admin` rows for it remain.
- [ ] 8.5 Manually verify: admin-authored context and a skill are visible via `describe_handler`/prompts, and are not affected by the module's later update or uninstall.

## 9. Documentation

- [x] 9.1 Update `README.md` with the new prompts capability and a short pointer to the module-author documentation from task 3.1.
