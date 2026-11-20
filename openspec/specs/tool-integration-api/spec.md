# tool-integration-api Specification

## Purpose
Gives another ImpressCMS module a defined, versioned way to use the shared tools, so the MCP provider can change its internals without breaking modules that depend on it.
## Requirements
### Requirement: One entry point for dependent modules
The MCP provider SHALL offer a single entry point from which another module obtains the shared tool definitions and a runner that executes them. Dependent modules SHALL NOT need to know how the runner is assembled.

#### Scenario: A dependent module needs the tools
- **WHEN** a dependent module asks the entry point for the tool definitions and the runner
- **THEN** it receives the same definitions the MCP endpoint offers and a runner that executes them

### Requirement: Same rules as the MCP endpoint
Tools run through the entry point SHALL evaluate the permissions of the signed-in user and SHALL apply the same module exclusions as the MCP endpoint. A dependent module SHALL NOT be able to widen access through the entry point.

#### Scenario: User without access to a module
- **WHEN** a dependent module runs a tool for a user who may not read a module
- **THEN** the tool returns the same "not available" answer as the MCP endpoint

#### Scenario: Module that opted out
- **WHEN** a dependent module runs a tool against a module that opted out of exposure
- **THEN** the call is refused

### Requirement: The API version is readable
The entry point SHALL expose an integer API version. The version SHALL increase only when a change can break a dependent module, such as a removed or renamed tool, definition field or error type.

#### Scenario: Dependent module checks compatibility
- **WHEN** a dependent module reads the API version
- **THEN** it can refuse to run when the version is lower than the one it needs

### Requirement: A missing provider can be detected
A dependent module SHALL be able to tell that the MCP provider is not installed or not active before it uses the tools, and show a clear message instead of failing with an error.

#### Scenario: Provider not installed
- **WHEN** the MCP provider is not installed or not active
- **THEN** a dependent module can detect this through the module registry and without loading provider code

### Requirement: The contract is documented
The documentation SHALL list what a dependent module may use, how to load the provider, the API version and the minimum provider version, and what tools signal on failure.

#### Scenario: Reading the documentation
- **WHEN** an author of a dependent module reads the documentation
- **THEN** they find the entry point, the version rule, the failure type and an example

