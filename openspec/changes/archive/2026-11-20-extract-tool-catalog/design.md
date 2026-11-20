## Context

See proposal.md for motivation. Today `ServerFactory::createServer()` chains seven `addTool()` calls. Each call carries a callable (`[$tools, 'listObjects']`), a name, a description, `ToolAnnotations` and an input schema built by the private `schema()` helper plus the shared `ID_SCHEMA` constant. `IpfTools` is a thin delegator to `IpfGateway`; authorization is enforced in the gateway through `AccessPolicy`, which reads `icms::$user`.

The module requires PHP 8.2+ (`composer.json`), so `readonly` classes and promoted constructor properties are fine here, as `IpfTools` already uses them.

## Goals / Non-Goals

**Goals:**
- One source of truth for tool definitions, usable by the MCP server now and by the chat later.
- No MCP types in the catalog, so a non-MCP consumer does not need the `mcp/sdk` classes for its own logic.
- Identical `tools/list` output before and after.

**Non-Goals:**
- A tool dispatcher or argument validation for non-MCP callers. That belongs to `admin-chat-core`; the MCP SDK maps arguments by reflection today and the chat needs its own validating executor.
- Changing any tool name, description, schema or annotation.
- Per-provider schema adaptation (OpenAI-compatible clients are out of scope for v1).

## Decisions

**`ToolDefinition` is a small readonly value object.** Fields: `name`, `description`, `inputSchema` (array), `method` (the `IpfTools` method name), `readOnly` (bool) and the optional `destructive` and `idempotent` (nullable bool). Plain flags instead of `Mcp\Schema\ToolAnnotations` keeps the catalog free of MCP types; `ServerFactory` builds the `ToolAnnotations` from them. The two optional flags are nullable because the existing tools set only some hints (read tools set only `readOnlyHint`), and passing `false` instead of omitting a hint would change `tools/list`. The chat derives "needs confirmation" from `readOnly === false`, so a future write tool is confirmed automatically.
*Alternative considered:* storing a `ToolAnnotations` instance in the definition. Rejected: couples the catalog to the SDK for no benefit.

**`ToolCatalog::all()` returns `list<ToolDefinition>` in the current registration order.** `ID_SCHEMA` and the `schema()` helper move into the catalog as private members. Order is kept so `tools/list` stays identical.

**Empty `properties` stays a `\stdClass`.** `list_modules` uses `new \stdClass()` so it encodes as `{}` not `[]`. This is preserved verbatim, because both MCP clients and the Anthropic API reject `properties: []`.

**`ServerFactory` loops and registers.** For each definition it calls `addTool([$tools, $definition->method], name:, description:, annotations:, inputSchema:)`.

**Verification by snapshot diff, not by tests.** The repository has no test suite. A scratch script builds the server from a given source tree and dumps the SDK's tool registry as JSON; the dump from before the refactor is compared with the dump after it. This covers definitions and annotations but not the HTTP transport, so one live call through an MCP client remains a manual check (task 4.3).

## Risks / Trade-offs

- [Silent change to a schema or description while moving it] → Move the strings verbatim and diff the registry dump before/after (task 4.2).
- [Optimized Composer classmap does not know the new classes] → The PSR-4 map covers `ImMcpProvider\Tool\`; run `composer dump-autoload -o` only if a site uses an authoritative classmap.
- [Annotation flags mis-mapped (e.g. `idempotentHint` on the wrong tool)] → The snapshot diff covers annotations too.
