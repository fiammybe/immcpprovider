<?php

declare(strict_types=1);

namespace ImMcpProvider\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MethodGuardMiddleware implements MiddlewareInterface
{
    private const ALLOWED_METHODS = ['POST', 'DELETE', 'OPTIONS'];

    public function __construct(private readonly ResponseFactoryInterface $responses)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (in_array($request->getMethod(), self::ALLOWED_METHODS, true)) {
            return $handler->handle($request);
        }

        return $this->responses
            ->createResponse(405)
            ->withHeader('Allow', implode(', ', self::ALLOWED_METHODS));
    }
}
