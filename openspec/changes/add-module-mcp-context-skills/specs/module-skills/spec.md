## Purpose

Lets a site administrator record named, free-text procedures ("skills") for correctly carrying out a site-specific or cross-module task, and exposes them to MCP clients as prompts alongside the existing tools.

## ADDED Requirements

### Requirement: Skills are administrator-authored only
The system SHALL store skills as IPF objects owned by the mcpapi module, each with a name, a title, free-text instructions, and an optional module dirname when the skill is specific to one module. Skills SHALL only be created, edited, or deleted by an administrator with `canAdministerModule` rights on the mcpapi module; no automated push mechanism for skills exists.

#### Scenario: Admin creates a cross-module skill
- **WHEN** an mcpapi administrator creates a skill with no module dirname set, describing a procedure spanning two modules
- **THEN** the skill is stored and is not tied to either module's own lifecycle (install, update, or uninstall)

#### Scenario: Admin creates a module-specific skill
- **WHEN** an mcpapi administrator creates a skill with module dirname `x` set
- **THEN** the skill is stored and associated with module `x` for exposure scoping, but is never altered by an install, update, or uninstall of module `x`

### Requirement: Skill content is plain instruction text
A skill's instructions SHALL be stored and returned as plain text intended to guide an MCP client through a procedure. The system SHALL NOT interpret, validate, or execute the instructions as a structured sequence of tool calls.

#### Scenario: Skill instructions are returned unmodified
- **WHEN** an MCP client reads a skill
- **THEN** the instructions text is returned exactly as the administrator wrote it, with no validation against available tools or handlers

### Requirement: Skills are exposed as MCP prompts
The system SHALL expose stored skills to MCP clients as MCP prompts: listable, and readable to obtain their instruction text. A module-specific skill SHALL only be listed when the caller can read that module under the existing module-exposure rules; a skill with no module dirname SHALL be listed for any authenticated caller.

#### Scenario: Module-specific skill hidden from a caller without access
- **WHEN** an MCP client without read access to module `x` lists prompts
- **THEN** skills with module dirname `x` do not appear in the list

#### Scenario: Cross-module skill is always listed
- **WHEN** any authenticated MCP client lists prompts
- **THEN** skills with no module dirname set appear in the list
