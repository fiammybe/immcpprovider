<?php

declare(strict_types=1);

use ImMcpProvider\Http\ResponseEmitter;
use ImMcpProvider\OAuth\AuthorizeFlow;
use ImMcpProvider\OAuth\OAuthServices;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ResponseInterface;

require_once __DIR__ . '/../bootstrap.php';

icms_loadLanguageFile('mcpapi', 'main');

header('X-Frame-Options: DENY');
header('Cache-Control: no-store');

$factory = new Psr17Factory();
$request = (new ServerRequestCreator($factory, $factory, $factory, $factory))->fromGlobals();

try {
    $result = (new AuthorizeFlow(OAuthServices::create()))->handle($request, $factory);
} catch (Throwable $exception) {
    error_log("mcpapi: {$exception}");

    $result = [
        'error' => _MD_MCPAPI_AUTHORIZE_FAILED,
        'client_name' => '',
        'redirect_host' => '',
        'user_name' => '',
        'action' => '',
        'token_html' => '',
    ];
}

if ($result instanceof ResponseInterface) {
    (new ResponseEmitter())->emit($result);

    exit;
}

$xoopsOption['template_main'] = 'mcpapi_authorize.html';

include ICMS_ROOT_PATH . '/header.php';

$icmsTpl->assign('mcpapi', $result);
$icmsTpl->assign('mcpapi_lang', [
    'title' => _MD_MCPAPI_AUTHORIZE_TITLE,
    'intro' => sprintf(_MD_MCPAPI_AUTHORIZE_INTRO, $result['client_name'], $result['user_name']),
    'redirect' => sprintf(_MD_MCPAPI_AUTHORIZE_REDIRECT, $result['redirect_host']),
    'scope' => _MD_MCPAPI_AUTHORIZE_SCOPE,
    'approve' => _MD_MCPAPI_AUTHORIZE_APPROVE,
    'deny' => _MD_MCPAPI_AUTHORIZE_DENY,
]);

include ICMS_ROOT_PATH . '/footer.php';
