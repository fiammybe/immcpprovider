## Purpose

Lets a module author, and separately a site administrator, attach business-meaning descriptions and cross-handler relation hints to IPF handlers and fields, so that MCP clients see more than raw schema when they call `list_modules` or `describe_handler`.

## ADDED Requirements

### Requirement: Context storage with a tracked source
The system SHALL store context entries (handler-level or field-level descriptions, and handler-to-handler relation hints) as IPF objects owned by the mcpapi module, each tagged with a `source` of either `module` or `admin`, and the `dirname` of the module the entry describes.

#### Scenario: A context entry always names its owning module and source
- **WHEN** a context entry is stored
- **THEN** it records the target module's dirname, an optional handler name, an optional field name, and a `source` of `module` or `admin`

### Requirement: Module-provided context is replaced wholesale on push
When a module pushes its context entries (via its own `onInstall` or `onUpdate`), the system SHALL replace all of that module's existing `source = module` entries with the newly pushed set, and SHALL NOT alter or remove any `source = admin` entries for that module.

#### Scenario: Module update replaces its own prior context
- **WHEN** module `x` pushes a new set of context entries during its `onUpdate`
- **THEN** every existing entry with `dirname = x` and `source = module` is removed and replaced by the newly pushed entries
- **AND** every existing entry with `dirname = x` and `source = admin` is left unchanged

#### Scenario: Pushing context has no effect when mcpapi is absent
- **WHEN** a module calls the guarded context-registration lookup and mcpapi is not installed or active
- **THEN** no context entries are created, changed, or removed, and the module's install or update completes normally

### Requirement: Module context is cleaned up on uninstall
When a module is uninstalled, the system SHALL remove all `source = module` context entries for that module's dirname, through a guarded call from the module's own `onUninstall`. Entries with `source = admin` for that module's dirname SHALL be left in place.

#### Scenario: Uninstalling a module removes its pushed context only
- **WHEN** module `x` is uninstalled and calls the guarded cleanup lookup
- **THEN** all context entries with `dirname = x` and `source = module` are deleted
- **AND** context entries with `dirname = x` and `source = admin` remain

### Requirement: mcpapi never triggers another module's install or update
The system SHALL discover modules that could supply context only by checking, during mcpapi's own `onInstall` or `onUpdate`, whether a documented convention file exists in each active module's directory. The system SHALL NOT invoke that module's install, update, or any other code as part of this discovery.

#### Scenario: mcpapi installation reports candidate modules without touching them
- **WHEN** mcpapi is installed on a site where module `x` already ships the convention file but has not pushed any context
- **THEN** mcpapi's installer lists `x` as a module the webmaster can update to activate MCP context
- **AND** no code belonging to module `x` is executed during this check

### Requirement: Administrators can author supplementary context
An administrator with `canAdministerModule` rights on the mcpapi module SHALL be able to create, edit, and delete `source = admin` context entries for any module, handler, or field. The system SHALL NOT allow editing or deleting `source = module` entries from this admin interface.

#### Scenario: Admin adds a site-specific field note
- **WHEN** an mcpapi administrator creates a context entry for module `x`, handler `y`, field `z` with `source = admin`
- **THEN** the entry is stored and later returned alongside any `source = module` entry for the same field, without replacing it

#### Scenario: Module-provided entries are read-only in the admin UI
- **WHEN** an mcpapi administrator views a `source = module` context entry in the admin interface
- **THEN** no edit or delete action is offered for that entry

### Requirement: Context and relations are exposed through existing tools
`describe_handler` and `list_modules` SHALL include any stored context entries (both `source = module` and `source = admin`, shown side by side) for the module, handler, or field being described, including any recorded handler-to-handler relation, scoped by the same module-exposure rules already applied to the rest of their output.

#### Scenario: Relation hint is included in a field description
- **WHEN** an MCP client calls `describe_handler` for a handler with a field that has a recorded relation to another handler
- **THEN** the response includes, for that field, the related module and handler name alongside its existing schema data

#### Scenario: Context is withheld for a module the caller cannot read
- **WHEN** an MCP client calls `describe_handler` for a module the caller does not have read access to
- **THEN** the call is rejected the same way it is today, and no context data is revealed
