# ImpressCMS MCP provider

This proof of concept exposes generic IPF handler operations through `mcp.php`
and the stdio client. It is not safe to expose to untrusted clients.

The provider calls IPF handlers directly. The default IPF handler does not
automatically enforce the target module's object or action permissions.
Module access to the provider is not equivalent to authorization to read or
modify every target module's objects. Restrict network and provider access
until the target modules and ImpressCMS provide and enforce appropriate
authorization for these calls.

List filters use the configured ImpressCMS database connection to quote values,
and filter and sort names must be persistent fields declared by the handler's
object. Handlers using another database connection require additional review.
Object identifiers are still passed to handlers unchanged; the default IPF
`get()` in the inspected ImpressCMS version constructs SQL without escaping
them. Do not expose get, update, or delete operations to untrusted clients
until that is fixed in the relevant handler or ImpressCMS core.

Run the standalone criteria checks with `php tests/criteria_security.php`.
