## Context

See proposal.md for motivation and the specs for behaviour.

- `IpfGateway::isExposed()` returns false for `system` (constant `EXCLUDED_MODULES`) and for the module whose folder name equals `basename(dirname(__DIR__, 2))`, then applies the `module_read` permission. Every tool resolves a module through it, so it is the single place where exposure is decided.
- `ServerFactory` builds the tools as `new IpfTools(new IpfGateway(new AccessPolicy()))` and takes definitions from `ToolCatalog` (from `extract-tool-catalog`). The admin chat did the same in `ToolExecutor::create()`.
- A manifest key that ImpressCMS does not know is still available through `$module->getInfo('<key>')`; the gateway already reads `object_items` that way.
- Tool failures are signalled with `Mcp\Exception\ToolCallException`, which the MCP SDK turns into an error result.
- The module requires PHP 8.2 and has no automated test suite; verification so far has been a registry dump, CLI harnesses against a real site, and manual calls.

## Goals / Non-Goals

**Goals:**
- A module can keep its data out of the tools by declaring it, without changes to `mcpapi`.
- The exposure rule cannot be defeated by the folder name or by a symbolic link.
- Dependent modules use one small, versioned entry point instead of constructing internals.

**Non-Goals:**
- An administrator-editable deny list, per-user exposure rules, or any change to permission evaluation.
- Making the provider usable without its Composer dependencies. A dependent module loads the provider's autoloader.
- Replacing `ToolCallException` with a provider-owned exception.

## Decisions

### D1. Exposure is a manifest flag that defaults to exposed
A module sets `'mcpapi_exposed' => false` in `icms_version.php`. Absent or any other value means exposed. Defaulting to exposed keeps every existing module working; opting out is the explicit, rare case, and it is made by the module that holds the data.
*Alternative:* an allow list in the module's preferences. Rejected: it hides new modules by default, which changes behaviour for existing installs, and it makes a module's privacy depend on an administrator remembering a setting.

### D2. `isExposed()` reads the flag; the directory-name check goes
Order: the names hard-excluded for modules whose manifest is not ours (`system`, `protector` and `tools`), then modules that declare no IPF objects, then the module's own flag, then the existing `module_read` check. `mcpapi` sets the flag on itself, so its self-exclusion no longer depends on the folder name or on symlinks.

### D2b. `system`, `protector` and `tools` are excluded by name
`system` and `protector` are bundled with ImpressCMS, so adding the flag to their manifests would be a core change, which is not allowed. `protector` holds the IP ban list (`protector_access`) and the security log (`protector_log`). `tools` is a management module with settings such as FTP access. All three are hidden by name even though `protector` and `tools` declare no IPF objects today (D2c), so they stay hidden if they ever gain some. The names are one constant that can hold further modules that cannot carry the flag.
*Alternative for `tools`:* a flag in the module's own manifest, so that no name is hard-coded in `mcpapi`. Not chosen: the name list is one place to maintain, it does not depend on that module being updated, and hiding a module called `tools` is the safe default even where it is something else.
*Alternative for all:* an administrator-editable deny list. Rejected for this change: more machinery than the need justifies, since the flag covers every module we control.

### D2c. A module without IPF objects is not exposed
`isExposed()` returns false when the module declares no IPF objects (`object_items` is empty or absent), using the same reader as the handler list. The tools only act on IPF handlers, so such a module offers nothing; listing it only reveals which management and security modules exist. In the test site this hides `protector` and `tools`, while `content`, `events`, `news` and `simplecart` declare objects and stay listed.
*Alternative:* keep listing modules with an empty handler list so that a client can see they exist. Rejected: it has no use for a client and leaks the presence of security and management modules.

### D3. A `ToolKit` entry point with a small surface
`ImMcpProvider\Tool\ToolKit` has `API_VERSION`, `catalog()` (the `ToolCatalog`) and `tools()` (an `IpfTools` built with the shared gateway and policy). `ServerFactory` uses it too, so the MCP endpoint and every dependent module go through one path and cannot diverge. Dependents never build `IpfGateway` or `AccessPolicy` themselves.
*Alternative:* let dependents construct `IpfTools` as the chat did. Rejected: it exposes internals as the contract and makes every internal refactor a breaking change.

### D4. The API version is an integer constant
`ToolKit::API_VERSION = 1`. Module versions in ImpressCMS are floats, which are awkward to compare, and the module version changes for reasons unrelated to compatibility. A dependent module requires a minimum API version and refuses to run below it.

### D5. Failure type stays `ToolCallException`
Tools keep throwing `Mcp\Exception\ToolCallException`, and the contract documents it. The MCP SDK relies on that type to turn failures into error results, so a provider-owned type would need an adapter in the server for no gain. A dependent module therefore needs the provider's Composer dependencies loaded, which loading the provider's autoloader does.

### D6. Detecting a missing provider uses the module registry
A dependent module checks that the module `mcpapi` is installed and active through the ImpressCMS module handler, and only then loads the provider's autoloader and reads `ToolKit::API_VERSION`. This works without loading any provider code and gives a clear message when the provider is missing, inactive or too old.

### D7. Delivery without rewriting history
The chat code and its planning are currently on `feature/admin-chat` and in open PR #5. This change ships on a new branch from `master` that contains the `extract-tool-catalog` commits and this change only. `feature/admin-chat` is kept untouched as the source for the `aichat` repository, so nothing is lost and no commit is rewritten or force-pushed. PR #5 is closed and replaced by a new PR from the new branch. Both Forgejo actions need the user's explicit approval.

### D8. Verification
No test suite exists, so verification is a CLI harness that runs against the real site with an in-memory user: it checks the flag with true, false and absent, the `system`, `protector` and `tools` modules, a module without `object_items`, and the self-exclusion with the module reached through a symbolic link under another folder name. The MCP `tools/list` dump must stay identical to the one from `extract-tool-catalog`.

## Risks / Trade-offs

- [A dependent module treats an internal class as API] → The README names the entry point as the only contract, and `ToolKit` is the only thing the chat is written against.
- [A module called `tools` on another site is something unrelated and meant to be reachable] → It is hidden there too. That is the safe default, the cost is one name in a constant, and it can be changed or replaced by a deny list later.
- [A bundled or third-party module that declares IPF objects, should be hidden and cannot carry the flag] → Only `system`, `protector` and `tools` are hidden by name; the rest behave as today. The name list is where more can be added, and an administrator-editable deny list can be a later change. Which modules with private data (messages, profiles) to hide is a decision for the site's owner.
- [A module without `object_items` that is meant to be reachable] → It could not be used by the tools anyway; if a module should be reachable it must declare its IPF objects, as the README says.
- [Wrong flag name or value type makes a module exposed by accident] → Only the boolean `false` hides; the README states it and the harness covers `true`, `false` and absent.
- [Version drift between the two modules] → The API version constant and the dependent module's check make an incompatible pair fail with a message instead of an error.
- [Abandoned PR #5 loses review context] → The new PR description links to it, and the branch stays.

## Migration Plan

1. Create the new branch from `master`, bring in the `extract-tool-catalog` commits, then implement this change.
2. Open the new PR and close PR #5 (after approval).
3. No data migration. The module has never been released, so there is nothing to keep compatible. A site that ran the earlier chat build uninstalls the module and installs this version fresh.
4. Rollback: remove the flag and `ToolKit`; the gateway falls back to the previous behaviour only if the old directory check is restored, so rollback is a revert of the change.

## Open Questions

- None that change the specs or the task breakdown.
