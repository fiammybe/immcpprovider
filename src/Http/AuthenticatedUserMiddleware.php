<?php

declare(strict_types=1);

namespace ImMcpProvider\Http;

use icms;
use icms_member_user_Object;
use ImMcpProvider\Module;
use ImMcpProvider\OAuth\BearerTokenValidator;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AuthenticatedUserMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responses,
        private readonly StreamFactoryInterface $streams
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $userId = (int) $request->getAttribute(BearerTokenValidator::USER_ATTRIBUTE);
        $user = $userId > 0 ? icms::handler('icms_member')->getUser($userId) : null;

        if (! $user instanceof icms_member_user_Object || ! $user->isActive()) {
            return $this->error(401, 'invalid_token', 'The account for this token is not available.');
        }

        if (! $this->mayUseModule($user)) {
            return $this->error(403, 'access_denied', 'Your account is not permitted to use the MCP provider.');
        }

        icms::$user = $user;
        $GLOBALS['xoopsUser'] = $user;

        return $handler->handle($request);
    }

    private function mayUseModule(icms_member_user_Object $user): bool
    {
        return icms::handler('icms_member_groupperm')->checkRight(
            'module_read',
            (int) Module::object()->getVar('mid'),
            $user->getGroups()
        );
    }

    private function error(int $status, string $error, string $description): ResponseInterface
    {
        $body = json_encode(['error' => $error, 'error_description' => $description], JSON_UNESCAPED_SLASHES);

        return $this->responses
            ->createResponse($status)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streams->createStream((string) $body));
    }
}
