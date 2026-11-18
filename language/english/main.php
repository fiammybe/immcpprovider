<?php

defined('ICMS_ROOT_PATH') or die('ICMS root path not defined');

define('_MD_MCPAPI_AUTHORIZE_TITLE', 'Authorize application');
define('_MD_MCPAPI_AUTHORIZE_INTRO', '"%s" wants to connect to this site as %s.');
define('_MD_MCPAPI_AUTHORIZE_REDIRECT', 'After you decide, you will be sent back to %s. Only continue if you started this connection yourself.');
define('_MD_MCPAPI_AUTHORIZE_SCOPE', 'The application can read and change site data exactly as far as your account is permitted to.');
define('_MD_MCPAPI_AUTHORIZE_APPROVE', 'Allow');
define('_MD_MCPAPI_AUTHORIZE_DENY', 'Deny');
define('_MD_MCPAPI_AUTHORIZE_NOT_PERMITTED', 'Your account is not permitted to use the MCP provider.');
define('_MD_MCPAPI_AUTHORIZE_INVALID_REQUEST', 'This authorization request is not valid.');
define('_MD_MCPAPI_AUTHORIZE_TOKEN_INVALID', 'Your security token has expired. Please go back to the application and try again.');
define('_MD_MCPAPI_AUTHORIZE_FAILED', 'Something went wrong while handling this request. Please try again later or contact the site administrator.');
