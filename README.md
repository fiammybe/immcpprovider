# MCP Provider for ImpressCMS 2

Lets MCP clients such as Claude work with an ImpressCMS site. Everyone signs in with their own site account, and every call is checked against that account's ImpressCMS groups and permissions.

## Requirements

- ImpressCMS 2.x with the IPF (the module works on IPF handlers declared in a module's `object_items`)
- PHP 8.2 or newer with the `openssl`, `json` and `mbstring` extensions
- A public HTTPS address if you want to connect Claude.ai (a local address is fine for Claude Code and MCP Inspector)

## Install

1. Copy this folder to `modules/mcpapi` on the site. The folder **must** be named `mcpapi`.
2. Make sure the `vendor/` folder is present. If you cloned the repository, run this inside the module folder:

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. In the ImpressCMS admin open **System, Modules** and install **MCP Provider**. The installer creates the tables, checks the requirements and generates the OAuth signing keys in your trust path (`<trust path>/mcpapi`).
4. Under **System, Modules, Permissions** give the groups that may use MCP access to the module. Only members of those groups can authorize an application or call the server.
5. Open the module's admin dashboard. It shows the MCP server URL and runs the setup checks.

### Developing from a checkout

The entry points locate `mainfile.php` through the requested script path, so you can symlink the repository into a site instead of copying it:

```powershell
New-Item -ItemType SymbolicLink -Path C:\path\to\site\modules\mcpapi -Target C:\path\to\immcpprovider
```

## Discovery addresses

MCP clients discover the login server from the 401 response, so the module works without any web server configuration. Some clients also try the standard well-known addresses. The bundled `.htaccess` maps them for Apache:

- `/modules/mcpapi/.well-known/openid-configuration`
- `/modules/mcpapi/.well-known/oauth-authorization-server`
- `/modules/mcpapi/.well-known/oauth-protected-resource`

On nginx add:

```nginx
location = /modules/mcpapi/.well-known/openid-configuration      { rewrite ^ /modules/mcpapi/oauth/authorization-server.php last; }
location = /modules/mcpapi/.well-known/oauth-authorization-server { rewrite ^ /modules/mcpapi/oauth/authorization-server.php last; }
location = /modules/mcpapi/.well-known/oauth-protected-resource  { rewrite ^ /modules/mcpapi/oauth/protected-resource.php last; }
location ~ ^/modules/mcpapi/vendor/ { deny all; }
```

The web server must pass the `Authorization` header to PHP. On Apache with CGI/FastCGI add `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1` if the header is missing.

## Connect Claude

- **Claude.ai / Claude Desktop:** Settings, Connectors, add a custom connector and enter `https://your-site/modules/mcpapi/api/mcp.php`. Claude registers itself, sends you to the site's login and consent page, and you approve it.
- **Claude Code:**

  ```bash
  claude mcp add --transport http mysite https://your-site/modules/mcpapi/api/mcp.php
  ```

  then run `/mcp` in Claude Code and choose *Authenticate*.

## Tools

| Tool | What it does |
|---|---|
| `list_modules` | Modules you may access, with their IPF handlers |
| `describe_handler` | Key, identifier and fields of a handler |
| `list_objects` | Objects of a handler (equality filters, paging, sorting) |
| `get_object` | One object by identifier |
| `create_object`, `update_object`, `delete_object` | Change data. Requires administrator rights on the module |

Reading follows the module access permission and, where a handler declares a `view` permission, the object permissions. The `system` module and this module are never exposed, and password-type fields are never returned.

### Module context and skills

`describe_handler` and `list_modules` include any stored context (field and handler descriptions, and relations between handlers) alongside the raw schema. This context can come from the module itself - see [`docs/module-integration.md`](docs/module-integration.md) for how a module pushes it - or from an mcpapi administrator, who can add or replace it under the module's **Module context** admin page. The two sources are always shown side by side; an admin entry never replaces a module's own entry.

mcpapi also exposes MCP **prompts**: named, free-text "skills" an administrator writes under **Skills** in the admin pages, for site-specific or cross-module procedures no single module could describe on its own. A skill can be scoped to one module (then only listed to callers who can read that module) or left unscoped to appear for every caller.

## How the login works

The module is an OAuth 2.1 authorization server (authorization code with PKCE, refresh tokens, dynamic client registration) built on `league/oauth2-server`, with the MCP transport from `mcp/sdk`. Tokens, clients and authorization codes are stored as IPF objects in the module's own tables. The consent page is a normal ImpressCMS page behind the normal site login. The admin pages list connected applications and issued tokens and can revoke them.

Access tokens last one hour and refresh tokens 30 days. Refresh tokens are rotated on use.

## Limitations

- Only public clients with PKCE are accepted.
- Access tokens are bound to the site, not to a resource indicator (RFC 8707).
- The bundled language files are English only.
- The database changes are only created when the module is installed or updated through the admin.
