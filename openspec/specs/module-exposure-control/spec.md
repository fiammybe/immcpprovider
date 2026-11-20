# module-exposure-control Specification

## Purpose
Decides which ImpressCMS modules the shared tools can read and change, and lets a module keep its own data out of reach.
## Requirements
### Requirement: A module can opt out of tool access
A module SHALL be able to declare in its manifest that it must not be reachable through the tools. A module that makes no such declaration SHALL remain reachable, subject to the existing permission checks.

#### Scenario: Module opts out
- **WHEN** a module declares that it is not exposed
- **THEN** it does not appear in the list of modules and every tool call that names it returns the same "not available" answer as for a module that does not exist

#### Scenario: Module makes no declaration
- **WHEN** an active module has no exposure declaration
- **THEN** it is listed and usable exactly as before, subject to the user's permissions

### Requirement: Hiding applies to every way of calling the tools
A module that opts out SHALL be unreachable through the MCP endpoint and through any other module that uses the shared tools.

#### Scenario: Another module calls the tools
- **WHEN** a dependent module runs a tool against a module that opted out
- **THEN** the call is refused in the same way as through the MCP endpoint

### Requirement: The MCP provider hides itself wherever it is installed
The MCP provider module SHALL be unreachable through the tools regardless of the name of its folder or whether it was installed by copying or by a symbolic link.

#### Scenario: Installed through a symbolic link
- **WHEN** the module runs from a symbolic link to a checkout whose folder has a different name
- **THEN** its own data is still not reachable through the tools

### Requirement: System, security and management modules stay hidden
The ImpressCMS `system` module, the `protector` security module and the `tools` management module SHALL NOT be reachable through the tools and SHALL NOT appear in the list of modules, whether or not a user may read them and whether or not they declare IPF objects.

#### Scenario: Asking for the system module
- **WHEN** a tool call names the system module
- **THEN** it is refused as not available

#### Scenario: Asking for the protector module
- **WHEN** a tool call names the protector module
- **THEN** it is refused as not available

#### Scenario: Asking for the tools module
- **WHEN** a tool call names the tools module
- **THEN** it is refused as not available

#### Scenario: Listing modules
- **WHEN** a user lists the modules they can access
- **THEN** none of the system, protector and tools modules is in the list

### Requirement: Modules without IPF objects are not exposed
A module that declares no IPF objects SHALL NOT appear in the list of modules and SHALL be refused by every tool, because the tools only work on IPF objects.

#### Scenario: Listing modules
- **WHEN** a user lists the modules they can access
- **THEN** a module that declares no IPF objects is not in the list, even when the user may read it

#### Scenario: Naming such a module in a tool call
- **WHEN** a tool call names a module that declares no IPF objects
- **THEN** it is refused as not available, in the same way as for a module that does not exist

#### Scenario: Module with IPF objects
- **WHEN** a module declares IPF objects and does not opt out
- **THEN** it is listed and usable, subject to the user's permissions

