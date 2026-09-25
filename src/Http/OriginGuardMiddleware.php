<?php

declare(strict_types=1);

namespace ImMcpProvider\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class OriginGuardMiddleware implements MiddlewareInterface
{
    /** @param array<int, string> $allowedHosts */
    public function __construct(
        private readonly array $allowedHosts,
        private readonly ResponseFactoryInterface $responses,
        private readonly StreamFactoryInterface $streams
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');

        if ($origin === '') {
            return $handler->handle($request);
        }

        $host = parse_url($origin, PHP_URL_HOST);

        if (is_string($host) && in_array(strtolower($host), $this->allowedHosts, true)) {
            return $handler->handle($request);
        }

        return $this->responses
            ->createResponse(403)
            ->withHeader('Content-Type', 'text/plain')
            ->withBody($this->streams->createStream('Forbidden: Invalid Origin header.'));
    }
}
