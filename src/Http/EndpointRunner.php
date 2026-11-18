<?php

declare(strict_types=1);

namespace ImMcpProvider\Http;

use ImMcpProvider\Support\ActivityLog;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class EndpointRunner
{
    private const DEFAULT_ERROR_BODY = ['error' => 'server_error'];

    private const BODY_EXCERPT_LENGTH = 300;

    /** @param array<string, mixed> $errorBody */
    public function __construct(
        private readonly ResponseEmitter $emitter = new ResponseEmitter(),
        private readonly array $errorBody = self::DEFAULT_ERROR_BODY
    ) {
    }

    /** @param callable(ServerRequestInterface, Psr17Factory): ResponseInterface $handler */
    public function run(callable $handler): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $factory = new Psr17Factory();
        $request = null;

        try {
            $request = $this->withForwardedAuthorization(
                (new ServerRequestCreator($factory, $factory, $factory, $factory))->fromGlobals()
            );

            $this->recordArrival($request);

            $response = $handler($request, $factory);

            $this->record($request, $response);
            $this->emitter->emit($response);
        } catch (OAuthServerException $exception) {
            $response = $exception->generateHttpResponse($factory->createResponse());

            $this->record($request, $response, "oauth_error={$exception->getErrorType()} hint=\"{$exception->getHint()}\"");
            $this->emitter->emit($response);
        } catch (Throwable $exception) {
            error_log("mcpapi: {$exception}");

            ActivityLog::record(sprintf(
                'EXCEPTION %s: %s in %s:%d',
                $exception::class,
                $exception->getMessage(),
                basename($exception->getFile()),
                $exception->getLine()
            ));

            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');

            echo json_encode($this->errorBody, JSON_UNESCAPED_SLASHES);
        }

        exit;
    }

    private function withForwardedAuthorization(ServerRequestInterface $request): ServerRequestInterface
    {
        if ($request->getHeaderLine('Authorization') !== '') {
            return $request;
        }

        $forwarded = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

        if (! is_string($forwarded) || $forwarded === '') {
            return $request;
        }

        return $request->withHeader('Authorization', $forwarded);
    }

    private function record(?ServerRequestInterface $request, ResponseInterface $response, string $detail = ''): void
    {
        $status = $response->getStatusCode();
        $parts = [
            $request?->getMethod() ?? '-',
            $request?->getUri()->getPath() ?? '-',
            'auth=' . $this->authorizationScheme($request),
            "status={$status}",
        ];

        if ($detail !== '') {
            $parts[] = $detail;
        }

        if ($status >= 400) {
            $parts[] = $this->errorDetail($response);
        }

        if ($status === 200 && str_ends_with((string) $request?->getUri()->getPath(), '/token.php')) {
            $parts[] = $this->tokenResponseShape($response);
        }

        if (headers_sent($file, $line)) {
            $parts[] = 'HEADERS_ALREADY_SENT at ' . basename((string) $file) . ":{$line}";
        }

        ActivityLog::record(implode(' ', $parts));
    }

    private function recordArrival(ServerRequestInterface $request): void
    {
        $server = $request->getServerParams();

        ActivityLog::record(sprintf(
            'START %s %s auth=%s ip=%s ua="%s"',
            $request->getMethod(),
            $request->getUri()->getPath(),
            $this->authorizationScheme($request),
            $server['REMOTE_ADDR'] ?? '-',
            mb_substr($request->getHeaderLine('User-Agent'), 0, 80)
        ));
    }

    private function tokenResponseShape(ResponseInterface $response): string
    {
        $body = $response->getBody();
        $body->rewind();
        $decoded = json_decode((string) $body, true);
        $body->rewind();

        if (! is_array($decoded)) {
            return 'token_response=NOT_JSON';
        }

        $fields = [];

        foreach ($decoded as $key => $value) {
            $fields[] = is_string($value) ? "{$key}(len=" . strlen($value) . ')' : "{$key}=" . json_encode($value);
        }

        $accessToken = is_string($decoded['access_token'] ?? null) ? $decoded['access_token'] : '';

        return sprintf(
            'token_response={%s} jwt_segments=%d jwt_claims=%s content-type="%s"',
            implode(',', $fields),
            substr_count($accessToken, '.') + 1,
            $this->jwtClaimsSummary($accessToken),
            $response->getHeaderLine('Content-Type')
        );
    }

    private function jwtClaimsSummary(string $jwt): string
    {
        $segments = explode('.', $jwt);
        $payload = json_decode((string) base64_decode(strtr($segments[1] ?? '', '-_', '+/')), true);

        if (! is_array($payload)) {
            return 'unreadable';
        }

        $visible = array_intersect_key($payload, array_flip(['iss', 'aud', 'exp', 'scopes']));

        return (string) json_encode($visible, JSON_UNESCAPED_SLASHES);
    }

    private function authorizationScheme(?ServerRequestInterface $request): string
    {
        $header = $request?->getHeaderLine('Authorization') ?? '';

        if ($header === '') {
            return 'none';
        }

        return strtolower(strtok($header, ' ') ?: 'unknown');
    }

    private function errorDetail(ResponseInterface $response): string
    {
        $detail = '';
        $challenge = $response->getHeaderLine('WWW-Authenticate');

        if ($challenge !== '') {
            $detail .= "www-authenticate=\"{$challenge}\" ";
        }

        $body = $response->getBody();

        if (! $body->isSeekable()) {
            return $detail;
        }

        $body->rewind();
        $excerpt = $body->read(self::BODY_EXCERPT_LENGTH);
        $body->rewind();

        return $detail . "body=\"{$excerpt}\"";
    }
}
