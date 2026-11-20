## 1. Baseline

- [ ] 1.1 Capture a reference snapshot of the registered tools before the refactor. Done offline with a scratch script that builds the server from the source tree and dumps the SDK tool registry (no login needed); snapshot kept outside the repo

## 2. Catalog (foundation, not used yet)

- [ ] 2.1 Add `src/Tool/ToolDefinition.php`: readonly value object (`name`, `description`, `inputSchema`, `readOnly`, `destructive`, `idempotent`, `method`)
- [ ] 2.2 Add `src/Tool/ToolCatalog.php` with `all()` returning the seven definitions in the current order, moving `ID_SCHEMA` and the `schema()` helper from `ServerFactory` and keeping every string and schema verbatim (including the `\stdClass` for empty `properties`)
- [ ] 2.3 Run `php82 -l` on both new files

## 3. Use the catalog in the MCP server

- [ ] 3.1 Change `ServerFactory::createServer()` to loop over `ToolCatalog::all()` and register each tool, building `ToolAnnotations` from the definition flags
- [ ] 3.2 Remove the inline tool declarations, `ID_SCHEMA` and `schema()` from `ServerFactory`
- [ ] 3.3 Run `php82 -l` on `ServerFactory.php`

## 4. Verify

- [ ] 4.1 Autoloader: not needed, the existing PSR-4 map (`ImMcpProvider\` => `.`) already covers `Tool/`; the module does not use an authoritative classmap
- [ ] 4.2 Dump the registry again from the refactored tree and diff against the baseline: names, descriptions, schemas and annotations are identical (7 tools)
- [ ] 4.3 Confirm offline that every registered tool is bound to an existing `IpfTools` method (all seven resolve; `IpfTools` and the argument names in the schemas are unchanged)
- [ ] 4.4 Smoke test on a site running this code: one read tool and one write tool through an MCP client (needs the OAuth login; run by the user)
