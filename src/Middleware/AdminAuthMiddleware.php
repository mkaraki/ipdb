<?php

namespace Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Repositories\AdminUserRepository;

class AdminAuthMiddleware extends AuthMiddleware implements MiddlewareInterface
{
    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        parent::__construct($responseFactory);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $auth = $this->isAuth($request);
        if (!$auth) {
            return $this->requestAuth();
        }

        $authInfo = $this->getAuthInfo();
        if ($authInfo === null || empty($authInfo['username']) || empty($authInfo['password'])) {
            return $this->requestAuth();
        }

        $db = db_init();
        $repo = new AdminUserRepository($db);

        $user = $repo->findByUsername($authInfo['username']);
        if ($user === null) {
            // No user found
            return $this->requestAuth();
        }

        if (!password_verify($authInfo['password'], $user['password_hash'])) {
            // No password match
            return $this->requestAuth();
        }

        if (password_needs_rehash($user['password_hash'], null)) {
            $authInfo['password_hash'] = password_hash($authInfo['password_hash'], PASSWORD_DEFAULT);
            $repo->updateById($user['id'], $authInfo);
        }

        // ToDo: Add CSRF protection on request.
        // See: https://github.com/mkaraki/ipdb/pull/69#discussion_r4106763455

        return $handler->handle($request);
    }
}