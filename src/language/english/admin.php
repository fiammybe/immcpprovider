<?php

defined('ICMS_ROOT_PATH') or die('ICMS root path not defined');

define('_AM_MCPAPI_DASHBOARD_CONNECT', 'Connect Claude');
define('_AM_MCPAPI_DASHBOARD_CONNECT_HELP', 'In Claude, open Settings, Connectors, add a custom connector and enter this address as the remote MCP server URL. Each person signs in with their own account on this site.');
define('_AM_MCPAPI_DASHBOARD_SERVER_URL', 'MCP server URL');
define('_AM_MCPAPI_DASHBOARD_DISCOVERY', 'Discovery documents');
define('_AM_MCPAPI_DASHBOARD_RESOURCE_METADATA', 'Protected resource metadata');
define('_AM_MCPAPI_DASHBOARD_SERVER_METADATA', 'Authorization server metadata');
define('_AM_MCPAPI_DASHBOARD_WELLKNOWN', 'Well-known address (needs the rewrite rules from the README)');
define('_AM_MCPAPI_DASHBOARD_CHECKS', 'Setup checks');
define('_AM_MCPAPI_DASHBOARD_STATS', 'Usage');
define('_AM_MCPAPI_DASHBOARD_CLIENTS', 'Connected applications');
define('_AM_MCPAPI_DASHBOARD_ACTIVE_TOKENS', 'Active access tokens');
define('_AM_MCPAPI_DASHBOARD_PERMISSIONS', 'Who can connect');
define('_AM_MCPAPI_DASHBOARD_PERMISSIONS_HELP', 'Only members of groups that have access to this module can authorize an application and use the MCP server. Adjust this under System, Modules, Permissions. Once connected, everyone works within their own permissions on each module; creating, changing and deleting data additionally requires administrator rights on that module.');
define('_AM_MCPAPI_DASHBOARD_ACTIVITY', 'Recent activity (newest first)');
define('_AM_MCPAPI_DASHBOARD_CANDIDATES', 'Modules offering MCP context');
define('_AM_MCPAPI_DASHBOARD_CANDIDATES_HELP', 'These active modules ship MCP context for Claude that has not been activated yet. Update them from System, Modules to push their context into mcpapi. Nothing here is changed automatically.');
define('_AM_MCPAPI_DASHBOARD_CANDIDATES_NONE', 'None found.');
define('_AM_MCPAPI_CHECK_OK', 'OK');
define('_AM_MCPAPI_CHECK_FAILED', 'Problem');

define('_AM_MCPAPI_CLIENT_NAME', 'Application');
define('_AM_MCPAPI_CLIENT_REDIRECTS', 'Redirect addresses');
define('_AM_MCPAPI_CLIENT_CREATED', 'Registered');
define('_AM_MCPAPI_CLIENT_DELETE_CONFIRM', 'Delete this application? Its access tokens are revoked immediately.');

define('_AM_MCPAPI_TOKEN_CLIENT', 'Application');
define('_AM_MCPAPI_TOKEN_USER', 'Account');
define('_AM_MCPAPI_TOKEN_CREATED', 'Issued');
define('_AM_MCPAPI_TOKEN_EXPIRES', 'Expires');
define('_AM_MCPAPI_TOKEN_STATUS', 'Status');
define('_AM_MCPAPI_TOKEN_DELETE_CONFIRM', 'Delete this token record?');
define('_AM_MCPAPI_TOKEN_REVOKED', 'The access token and its refresh tokens have been revoked.');

define('_AM_MCPAPI_STATUS_ACTIVE', 'Active');
define('_AM_MCPAPI_STATUS_EXPIRED', 'Expired');
define('_AM_MCPAPI_STATUS_REVOKED', 'Revoked');
define('_AM_MCPAPI_REVOKE', 'Revoke');
