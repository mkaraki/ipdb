<?php

namespace Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpNotImplementedException;

class AuthMiddleware implements MiddlewareInterface
{
    private ResponseFactoryInterface $responseFactory;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    protected function isAuth(ServerRequestInterface $request): bool
    {
        global $_SERVER;
        $authLine = $request->getHeaderLine('Authorization');
        if (empty($authLine)) { return false; }
        if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
            return false;
        }
        return true;
    }

    protected function requestAuth(): ResponseInterface {
        $response = $this->responseFactory->createResponse(401);
        $response->getBody()->write('Unauthorized');
        return $response->withHeader('WWW-Authenticate', 'Basic realm="ipdb"');
    }

    protected function getAuthInfo(): ?array
    {
        global $_SERVER;
        if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
            return null;
        }
        return [
            'username' => $_SERVER['PHP_AUTH_USER'],
            'password' => $_SERVER['PHP_AUTH_PW'],
        ];
    }

    protected function authBasic($userList): bool
    {
        global $_SERVER;
        if (
            !isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) ||
            !isset($userList[$_SERVER['PHP_AUTH_USER']]) ||
            password_verify($_SERVER['PHP_AUTH_PW'], $userList[$_SERVER['PHP_AUTH_USER']]) === false
        ) {
            return false;
        }

        return true;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        throw new HttpNotImplementedException($request);
    }
}