## 1. Exposure flag

- [x] 1.1 Make `IpfGateway::isExposed()` read the `mcpapi_exposed` manifest key (only boolean `false` hides), hard-exclude the modules whose manifest is not ours (`system`, `protector` and `tools`) by name, treat a module that declares no IPF objects as not exposed, and remove the directory-name comparison
- [x] 1.2 Set `'mcpapi_exposed' => false` in `src/icms_version.php`
- [x] 1.3 Run `php82 -l` on the changed files

## 2. Integration entry point

- [x] 2.1 Add `src/Tool/ToolKit.php` with `API_VERSION = 1`, `catalog()` and `tools()` (the shared `IpfTools`)
- [x] 2.2 Make `ServerFactory` obtain its catalog and tools through `ToolKit`
- [x] 2.3 Document the contract in the README: the entry point, the API version rule, `ToolCallException`, how to load the provider's autoloader, how to detect a missing or inactive provider, the exposure flag, and a short example
- [x] 2.4 Raise the module version to 0.6.1

## 3. Verification

- [x] 3.1 Write a CLI harness (outside the repository) with an in-memory user that checks the flag as true, false and absent, the `system`, `protector` and `tools` modules and a module without `object_items` (not listed, every tool refuses them), and `mcpapi` hiding itself when run from a symbolic link under another folder name
- [x] 3.2 Confirm the MCP registry dump is identical to the one taken for `extract-tool-catalog`
- [x] 3.3 On a local site: do a fresh install of this version (the module has never been released, so there is no update path to keep), confirm the setup output, the four OAuth tables, the templates and permissions, newly generated OAuth keys, that the MCP endpoint answers (`401` without a token, `200` metadata) and that the real module is hidden from the tools
- [x] 3.4 Reconnect an MCP client (the fresh install dropped the earlier client registrations and tokens) and confirm one read and one write call still work (needs the user's login)

## 4. Delivery (outward actions need the user's explicit approval)

- [x] 4.1 Create a new branch from `master` and bring in the `extract-tool-catalog` commits; add the commits of this change in the usual groups
- [x] 4.2 Push the branch to the Forgejo repository `beheerder/immcpprovider` and open a new PR whose description links to PR #5
- [x] 4.3 Close PR #5 with a comment that points to the new PR and to the preserved `feature/admin-chat` branch
