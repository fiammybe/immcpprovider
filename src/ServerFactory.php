<?php

declare(strict_types=1);

namespace ImMcpProvider;

use ImMcpProvider\Http\AuthenticatedUserMiddleware;
use ImMcpProvider\Http\MethodGuardMiddleware;
use ImMcpProvider\Http\OriginGuardMiddleware;
use ImMcpProvider\OAuth\BearerTokenValidator;
use ImMcpProvider\OAuth\OAuthMetadata;
use ImMcpProvider\OAuth\OAuthServices;
use ImMcpProvider\Tool\SkillTools;
use ImMcpProvider\Tool\ToolKit;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\Http\Middleware\AuthorizationMiddleware;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ServerRequestInterface;
use mod_mcpapi_Skill;

final class ServerFactory
{
    private const SESSION_TTL = 2592000;

    private const SERVER_NAME = 'impresscms-mcp';

    private const SERVER_VERSION = '0.2.0';

    private const LOCAL_HOSTS = ['localhost', '127.0.0.1', '[::1]'];

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
        $tools = ToolKit::tools();

        $builder = Server::builder()
            ->setServerInfo(
                self::SERVER_NAME,
                self::SERVER_VERSION,
                'Exposes ImpressCMS IPF objects to MCP clients, restricted by the authenticated user\'s permissions.'
            )
            ->setSession(new FileSessionStore(ICMS_TRUST_PATH . '/mcpapi/sessions', self::SESSION_TTL));

        foreach (ToolKit::catalog()->all() as $definition) {
            $builder->addTool(
                [$tools, $definition->method],
                name: $definition->name,
                description: $definition->description,
                annotations: new ToolAnnotations(
                    readOnlyHint: $definition->readOnly,
                    destructiveHint: $definition->destructive,
                    idempotentHint: $definition->idempotent
                ),
                inputSchema: $definition->inputSchema
            );
        }

        foreach ((new SkillTools())->visibleSkills() as $skill) {
            $builder->addPrompt(
                $this->skillPromptHandler($skill),
                name: (string) $skill->getVar('name', 'n'),
                title: (string) $skill->getVar('title', 'n'),
                description: (string) $skill->getVar('title', 'n')
            );
        }

        return $builder->build();
    }

    /** @return \Closure(): array<int, array<string, string>> */
    private function skillPromptHandler(mod_mcpapi_Skill $skill): \Closure
    {
        return static fn (): array => [$skill->toMcpPromptMessage()];
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
}
