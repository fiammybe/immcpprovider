## Why

The seven MCP tools (name, description, JSON schema, annotations) are defined inline in `ServerFactory::createServer()`. Execution is already independent of the MCP transport (`IpfTools` → `IpfGateway` → `AccessPolicy`), but the tool *definitions* are not reusable. The planned admin chat (`admin-chat-core`) must send the same definitions to an LLM and decide which tools need confirmation. Duplicating them would mean two sets of tools to keep in sync.

## What Changes

- Add a transport-neutral `ToolCatalog` that returns the tool definitions (name, description, input schema, read-only / destructive / idempotent flags, and the `IpfTools` method that executes them).
- `ServerFactory` registers its MCP tools by looping over the catalog instead of declaring them inline.
- The MCP surface stays byte-for-byte the same: same tool names, descriptions, schemas and annotations.
- No new dependencies, no behaviour change, no new endpoints.

## Capabilities

### New Capabilities

None. This is a pure refactor; `.openspec.yaml` sets `skip_specs: true`.

### Modified Capabilities

None.

## Impact

- `src/ServerFactory.php`: inline tool declarations removed, replaced by a loop over the catalog.
- New `src/Tool/ToolCatalog.php` and `src/Tool/ToolDefinition.php`.
- Prerequisite for the `admin-chat-core` change, which consumes the catalog.
- Anyone running an optimized Composer classmap must regenerate the autoloader after updating (new classes).
