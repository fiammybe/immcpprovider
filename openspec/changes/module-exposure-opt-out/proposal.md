## Why

The tool layer decides which modules an MCP client can reach. It hides `system` by name and hides itself by comparing the directory name `mcpapi` with the folder it runs from. Both rules are hard-coded in `IpfGateway::isExposed()`. This breaks down in two ways:

- A second module that holds sensitive data, such as the admin chat that is moving to its own module (`aichat`), cannot opt out. Its conversations, messages and tool-call records would show up in `list_modules` and be readable and writable through every tool.
- The self-exclusion compares `basename(dirname(__DIR__, 2))` with `mcpapi`. When the module is symlinked from a checkout, `__DIR__` resolves to the real path, the comparison fails, and the module stops hiding itself.

`aichat` also needs a defined way to use the tool layer without reaching into its internals, so that `mcpapi` can change its internals without breaking a dependent module.

## What Changes

- A module can opt out of tool access with a flag in its `icms_version.php`. A module without the flag stays exposed, as today.
- The ImpressCMS `system` module, the bundled `protector` security module and the `tools` management module are hidden by name. The first two belong to ImpressCMS, so their manifests cannot carry the flag; `protector` holds the site's IP ban list and security log, and `tools` is a management module that has no place within an AI client's reach.
- A module that declares no IPF objects is not exposed. The tools work on IPF handlers, so nothing can be done with such a module, and listing it only tells a client which management and security modules are installed. This also hides the `tools` management module and `protector` today.
- `mcpapi` hides itself through the same flag. The directory-name comparison is removed, which also fixes the symlink case.
- Add one entry point for dependent modules (`ToolKit`) that returns the shared tool definitions and the tool runner, which applies the signed-in user's permissions and the same exclusion rules as the MCP endpoint. It carries an integer API version that dependents can check.
- The MCP server obtains its tools through the same entry point, so there is one path for both.
- Document the contract in the README: what a dependent module may use, the API version, the exception type tools throw, and the minimum `mcpapi` version.
- The module version becomes 0.6.1.
- Out of scope: an administrator-editable deny list for modules the administrator does not control, and any change to how permissions are evaluated.

## Capabilities

### New Capabilities

- `module-exposure-control`: which modules the tools can reach, how a module opts out, and that hiding applies to every access path.
- `tool-integration-api`: how another ImpressCMS module obtains and runs the shared tools, with the same rules as the MCP endpoint, and how it detects an incompatible provider.

### Modified Capabilities

None (`openspec/specs/` is empty; the earlier MCP behaviour was never specified).

## Impact

- `src/Ipf/IpfGateway.php` (`isExposed()`), `src/icms_version.php` (flag and version), `src/ServerFactory.php`, new `src/Tool/ToolKit.php`, README.
- Prerequisite for the `aichat-module` change in the `aichat` repository, which depends on this API and on this flag.
- This change ships on a new branch from `master` together with the `extract-tool-catalog` change. The chat code that is currently on `feature/admin-chat` does not ship with `mcpapi`; it moves to `aichat`.
- Admins of third-party modules that rely on the old behaviour are not affected: modules without the flag are exposed exactly as before.
