<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

function authBasic($userList): bool
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

function requestAuth($app) {
    $response = $app->getResponseFactory()->createResponse(401);
    $response->getBody()->write('Unauthorized');
    return $response->withHeader('WWW-Authenticate', 'Basic realm="ipdb"');
}

function declare_auth_middlewares($app): array {
    $atkBotAuthMiddleware = function (Request $request, RequestHandler $handler) use ($app) {
        $auth = $request->getHeaderLine('Authorization');
        if (!$auth) {
            return requestAuth($app);
        }

        $authRes = authBasic(USER_ATK_REPORTER);
        if (!$authRes) {
            return requestAuth($app);
        }

        return $handler->handle($request);
    };

    $atkManagerAuthMiddleware = function (Request $request, RequestHandler $handler) use ($app) {
        $auth = $request->getHeaderLine('Authorization');
        if (!$auth) {
            return requestAuth($app);
        }

        $authRes = authBasic(USER_ATK_MANAGER);
        if (!$authRes) {
            return requestAuth($app);
        }

        return $handler->handle($request);
    };

    return [
        $atkBotAuthMiddleware,
        $atkManagerAuthMiddleware,
    ];
}
