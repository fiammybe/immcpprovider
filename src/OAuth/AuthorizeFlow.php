<?php

declare(strict_types=1);

namespace ImMcpProvider\OAuth;

use icms;
use ImMcpProvider\Module;
use ImMcpProvider\OAuth\Entity\UserEntity;
use ImMcpProvider\Support\ActivityLog;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthorizeFlow
{
    public function __construct(private readonly OAuthServices $services)
    {
    }

    /**
     * @return ResponseInterface|array{
     *     error: ?string,
     *     client_name: string,
     *     redirect_host: string,
     *     user_name: string,
     *     action: string,
     *     token_html: string
     * }
     */
    public function handle(ServerRequestInterface $request, Psr17Factory $factory): ResponseInterface|array
    {
        $request = $this->restorePackedQuery($request);

        ActivityLog::record(sprintf(
            'AUTHORIZE %s client=%s logged_in=%s',
            $request->getMethod(),
            $this->clientIdentifier($request),
            is_object(icms::$user) ? 'yes' : 'no'
        ));

        if (! is_object(icms::$user)) {
            return $this->redirectToLogin($request, $factory);
        }

        if (! $this->mayUseModule()) {
            ActivityLog::record('AUTHORIZE refused: the account has no access to the module');

            return $this->errorPage(_MD_MCPAPI_AUTHORIZE_NOT_PERMITTED);
        }

        $server = $this->services->authorizationServer();

        try {
            $authorizationRequest = $server->validateAuthorizationRequest($request);
        } catch (OAuthServerException $exception) {
            return $this->failure($exception, $factory);
        }

        if ($request->getMethod() !== 'POST') {
            return $this->consentPage($request, $authorizationRequest);
        }

        if (! icms::$security->check()) {
            ActivityLog::record('AUTHORIZE refused: the form security token was invalid or expired');

            return $this->errorPage(_MD_MCPAPI_AUTHORIZE_TOKEN_INVALID);
        }

        $approved = ($request->getParsedBody()['decision'] ?? null) === 'approve';

        ActivityLog::record('AUTHORIZE decision=' . ($approved ? 'approve' : 'deny') . ' user=' . (int) icms::$user->getVar('uid'));

        $authorizationRequest->setUser(new UserEntity((string) icms::$user->getVar('uid')));
        $authorizationRequest->setAuthorizationApproved($approved);

        try {
            return $server->completeAuthorizationRequest($authorizationRequest, $factory->createResponse());
        } catch (OAuthServerException $exception) {
            return $this->failure($exception, $factory);
        }
    }

    /** @return ResponseInterface|array<string, mixed> */
    private function failure(OAuthServerException $exception, Psr17Factory $factory): ResponseInterface|array
    {
        ActivityLog::record(sprintf(
            'AUTHORIZE failed: %s hint="%s" redirect=%s',
            $exception->getErrorType(),
            $exception->getHint(),
            $exception->hasRedirect() ? 'yes' : 'no'
        ));

        if ($exception->hasRedirect()) {
            return $exception->generateHttpResponse($factory->createResponse());
        }

        return $this->errorPage(_MD_MCPAPI_AUTHORIZE_INVALID_REQUEST);
    }

    private function clientIdentifier(ServerRequestInterface $request): string
    {
        $identifier = $request->getQueryParams()['client_id'] ?? null;

        return is_string($identifier) && $identifier !== '' ? substr($identifier, 0, 40) : '-';
    }

    private function restorePackedQuery(ServerRequestInterface $request): ServerRequestInterface
    {
        $packed = $request->getQueryParams()['q'] ?? null;

        if (! is_string($packed)) {
            return $request;
        }

        $decoded = base64_decode(strtr($packed, '-_', '+/'), true);

        if ($decoded === false) {
            return $request;
        }

        parse_str($decoded, $params);

        return $request->withQueryParams($params);
    }

    private function packQuery(ServerRequestInterface $request): string
    {
        $query = http_build_query($request->getQueryParams());

        return rtrim(strtr(base64_encode($query), '+/', '-_'), '=');
    }

    private function selfUrl(ServerRequestInterface $request): string
    {
        return Module::url('oauth/authorize.php') . '?q=' . $this->packQuery($request);
    }

    private function redirectToLogin(ServerRequestInterface $request, Psr17Factory $factory): ResponseInterface
    {
        $login = ICMS_URL . '/user.php?xoops_redirect=' . rawurlencode($this->selfUrl($request));

        return $factory->createResponse(302)->withHeader('Location', $login);
    }

    private function mayUseModule(): bool
    {
        return icms::handler('icms_member_groupperm')->checkRight(
            'module_read',
            (int) Module::object()->getVar('mid'),
            icms::$user->getGroups()
        );
    }

    /** @return array<string, mixed> */
    private function consentPage(ServerRequestInterface $request, AuthorizationRequestInterface $authorizationRequest): array
    {
        $redirectUri = (string) $authorizationRequest->getRedirectUri();
        $client = $authorizationRequest->getClient();

        return [
            'error' => null,
            'client_name' => $client->getName(),
            'redirect_host' => (string) parse_url($redirectUri, PHP_URL_HOST),
            'user_name' => (string) icms::$user->getVar('uname'),
            'action' => $this->selfUrl($request),
            'token_html' => icms::$security->getTokenHTML(),
        ];
    }

    /** @return array<string, mixed> */
    private function errorPage(string $message): array
    {
        return [
            'error' => $message,
            'client_name' => '',
            'redirect_host' => '',
            'user_name' => '',
            'action' => '',
            'token_html' => '',
        ];
    }
}
