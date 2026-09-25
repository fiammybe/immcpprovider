<?php

declare(strict_types=1);

namespace ImMcpProvider;

use ImMcpProvider\Http\AuthenticatedUserMiddleware;
use ImMcpProvider\Http\MethodGuardMiddleware;
use ImMcpProvider\Http\OriginGuardMiddleware;
use ImMcpProvider\Ipf\IpfGateway;
use ImMcpProvider\OAuth\BearerTokenValidator;
use ImMcpProvider\OAuth\OAuthMetadata;
use ImMcpProvider\OAuth\OAuthServices;
use ImMcpProvider\Security\AccessPolicy;
use ImMcpProvider\Tool\IpfTools;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\Http\Middleware\AuthorizationMiddleware;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ServerRequestInterface;

final class ServerFactory
{
    private const SERVER_NAME = 'impresscms-mcp';

    private const SERVER_VERSION = '0.2.0';

    private const LOCAL_HOSTS = ['localhost', '127.0.0.1', '[::1]'];

    private const ID_SCHEMA = [
        'type' => ['string', 'integer', 'object'],
        'description' => 'Object identifier. Use an object of key fields for composite keys.',
    ];

    public function createTransport(ServerRequestInterface $request): StreamableHttpTransport
    {
        return new StreamableHttpTransport($this->canonicalRequest($request), middleware: $this->middleware());
    }

    private function canonicalRequest(ServerRequestInterface $request): ServerRequestInterface
    {
        $site = parse_url(ICMS_URL);

        if (! is_array($site) || ! isset($site['scheme'], $site['host'])) {
            return $request;
        }

        $uri = $request->getUri()
            ->withScheme($site['scheme'])
            ->withHost($site['host'])
            ->withPort($site['port'] ?? null);

        return $request->withUri($uri);
    }

    public function createServer(): Server
    {
        $tools = new IpfTools(new IpfGateway(new AccessPolicy()));

        return Server::builder()
            ->setServerInfo(
                self::SERVER_NAME,
                self::SERVER_VERSION,
                'Exposes ImpressCMS IPF objects to MCP clients, restricted by the authenticated user\'s permissions.'
            )
            ->setSession(new FileSessionStore(ICMS_TRUST_PATH . '/mcpapi/sessions'))
            ->addTool(
                [$tools, 'listModules'],
                name: 'list_modules',
                description: 'List the ImpressCMS modules you can access, with their IPF handlers.',
                annotations: new ToolAnnotations(readOnlyHint: true),
                inputSchema: ['type' => 'object', 'properties' => new \stdClass()]
            )
            ->addTool(
                [$tools, 'describeHandler'],
                name: 'describe_handler',
                description: 'Describe a module handler: its key, identifier and fields.',
                annotations: new ToolAnnotations(readOnlyHint: true),
                inputSchema: $this->schema(
                    ['module' => ['type' => 'string'], 'handler' => ['type' => 'string']],
                    ['module', 'handler']
                )
            )
            ->addTool(
                [$tools, 'listObjects'],
                name: 'list_objects',
                description: 'List objects from a module handler. Filters are equality matches on field names.',
                annotations: new ToolAnnotations(readOnlyHint: true),
                inputSchema: $this->schema(
                    [
                        'module' => ['type' => 'string'],
                        'handler' => ['type' => 'string'],
                        'filters' => ['type' => 'object'],
                        'fields' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'start' => ['type' => 'integer', 'minimum' => 0],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                        'sort' => ['type' => 'string'],
                        'order' => ['type' => 'string', 'enum' => ['ASC', 'DESC']],
                    ],
                    ['module', 'handler']
                )
            )
            ->addTool(
                [$tools, 'getObject'],
                name: 'get_object',
                description: 'Fetch one object by identifier.',
                annotations: new ToolAnnotations(readOnlyHint: true),
                inputSchema: $this->schema(
                    [
                        'module' => ['type' => 'string'],
                        'handler' => ['type' => 'string'],
                        'id' => self::ID_SCHEMA,
                        'fields' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    ['module', 'handler', 'id']
                )
            )
            ->addTool(
                [$tools, 'createObject'],
                name: 'create_object',
                description: 'Create an object. Requires administrator rights on the module. This changes data.',
                annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false),
                inputSchema: $this->schema(
                    [
                        'module' => ['type' => 'string'],
                        'handler' => ['type' => 'string'],
                        'data' => ['type' => 'object'],
                    ],
                    ['module', 'handler', 'data']
                )
            )
            ->addTool(
                [$tools, 'updateObject'],
                name: 'update_object',
                description: 'Update an object. Requires administrator rights on the module. This changes data.',
                annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
                inputSchema: $this->schema(
                    [
                        'module' => ['type' => 'string'],
                        'handler' => ['type' => 'string'],
                        'id' => self::ID_SCHEMA,
                        'data' => ['type' => 'object'],
                    ],
                    ['module', 'handler', 'id', 'data']
                )
            )
            ->addTool(
                [$tools, 'deleteObject'],
                name: 'delete_object',
                description: 'Delete an object. Requires administrator rights on the module. This permanently removes data.',
                annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true),
                inputSchema: $this->schema(
                    [
                        'module' => ['type' => 'string'],
                        'handler' => ['type' => 'string'],
                        'id' => self::ID_SCHEMA,
                    ],
                    ['module', 'handler', 'id']
                )
            )
            ->build();
    }

    /** @return array<int, \Psr\Http\Server\MiddlewareInterface> */
    private function middleware(): array
    {
        $allowedHosts = $this->allowedHosts();

        $factory = new Psr17Factory();
        $validator = new BearerTokenValidator(OAuthServices::create()->resourceServer(), $factory);

        return [
            new CorsMiddleware(),
            new OriginGuardMiddleware($allowedHosts, $factory, $factory),
            new MethodGuardMiddleware($factory),
            new AuthorizationMiddleware($validator, OAuthMetadata::protectedResource(), $factory),
            new AuthenticatedUserMiddleware($factory, $factory),
        ];
    }

    /** @return array<int, string> */
    private function allowedHosts(): array
    {
        $siteHost = parse_url(ICMS_URL, PHP_URL_HOST);

        if (! is_string($siteHost) || $siteHost === '') {
            return self::LOCAL_HOSTS;
        }

        $siteHost = strtolower($siteHost);
        $counterpart = str_starts_with($siteHost, 'www.') ? substr($siteHost, 4) : "www.{$siteHost}";

        return [...self::LOCAL_HOSTS, $siteHost, $counterpart];
    }

    /**
     * @param array<string, array<string, mixed>> $properties
     * @param array<int, string> $required
     *
     * @return array<string, mixed>
     */
    private function schema(array $properties, array $required): array
    {
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ];
    }
}
