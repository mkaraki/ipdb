<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Sentry\State\Hub;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

use Monolog\Level;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../_config.php';

require_once __DIR__ . '/../src/DbProxy.php';
require_once __DIR__ . '/../src/AuthMiddlewares.php';
require_once __DIR__ . '/../src/IpAccessUtils.php';
require_once __DIR__ . '/../src/Atk/PostToAtk.php';
require_once __DIR__ . '/../src/Atk/AtkFeed.php';

$logger = new \Monolog\Logger('app');

\Sentry\init([
    'dsn' => defined('SENTRY_DSN') ? SENTRY_DSN ?? '' : '',
    'traces_sample_rate' => 1.0,
    'send_default_pii' => false,
    'enable_logs' => true,
    'environment' => APP_ENV,
    'logger' => $logger,
]);

$logger->pushHandler(new \Sentry\Monolog\BreadcrumbHandler(
    hub: \Sentry\SentrySdk::getCurrentHub(),
    level: Level::Info,
));

// Also write to stdout
//$logger->pushHandler(new \Monolog\Handler\StreamHandler('/var/www/html/log.log', Level::Debug));

$app = AppFactory::create();

[
    $atkBotAuthMiddleware,
    $atkManagerAuthMiddleware,
] = declare_auth_middlewares($app);

$twig = Twig::create(__DIR__ . '/../templates', ['cache' => false]);

$twigEnvironment = $twig->getEnvironment();
$twigEnvironment->addGlobal('sentryBaggage', \Sentry\getBaggage());
$twigEnvironment->addGlobal('sentryTrace', \Sentry\getTraceparent());
$twigEnvironment->addGlobal('sentryDsn', defined('SENTRY_DSN') ? SENTRY_DSN ?? '' : '');
$twigEnvironment->addGlobal('sentryEnv', APP_ENV ?? 'production');


$sentryMiddleware = function (Request $request, RequestHandler $handler) {
    $routeContext = RouteContext::fromRequest($request);
    $route = $routeContext->getRoute();

    $pattern = $route->getPattern();
    $method = $request->getMethod();

    $transactionContext = \Sentry\continueTrace(
        $request->getHeader('sentry-trace')[0] ?? '',
        $request->getHeader('baggage')[0] ?? ''
    );

    $transactionContext->setName($method . ' ' . $pattern);
    $transactionContext->setOp('http.server');
    $transactionContext->setSource(\Sentry\Tracing\TransactionSource::route());

    $queryParams = $request->getQueryParams();
    $args = $route->getArguments();

    $transactionContext->setData([
        'queryParams' => $queryParams,
        'args' => $args,
    ]);

    $transaction = \Sentry\startTransaction($transactionContext);
    \Sentry\SentrySdk::getCurrentHub()->setSpan($transaction);

    try {
        $response = $handler->handle($request);

        return $response;
    } finally {
        $transaction->finish();
        \Sentry\SentrySdk::getCurrentHub()->setSpan(null);
    }
};

$customErrorHandler = function (
    ServerRequestInterface $request,
    Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails
) use ($app, $logger) {
    if ($exception instanceof \Slim\Exception\HttpException) {
        $response = $app->getResponseFactory()->createResponse($exception->getCode());
        return $response;
    }

    if ($logger) {
        $logger->error($exception->getMessage());
    }

    \Sentry\captureException($exception);

    $response = $app->getResponseFactory()->createResponse(500);
    if (APP_ENV === 'development') {
        $response->getBody()->write($exception->getMessage() . PHP_EOL . $exception->getTraceAsString());
    }
    return $response;
};

function sharedAtkPostProcess (Request $request, Response $response, $args) {
    $ip = $_POST['ip'] ?? '';
    $loggedAt = $_POST['loggedat'] ?? time();

    if (empty($loggedAt)) {
        $loggedAt = time();
    }

    if (!validateIpIsPublic($ip)) {
        $response->getBody()->write('Invalid IP address.');
        return $response->withStatus(400);
    }

    if (!is_numeric($loggedAt)) {
        $response->getBody()->write('Logged at value must be numeric.');
        return $response->withStatus(400);
    }

    $link = db_init();
    if (!check_schema_version($link)) {
        $response->getBody()->write('Database schema version mismatch. Please run the update script.');
        return $response->withStatus(500);
    }

    postToAtkDatabase($link, $ip, $loggedAt);

    return null;
}

if (defined('PROVIDE_ATK_WP_ADMIN_ENDPOINT') && PROVIDE_ATK_WP_ADMIN_ENDPOINT) {
    $app->any('/wp-admin[/{params:.*}]', function (Request $request, Response $response, $args) {
        postClientToAtkDatabase($request);
        $response->getBody()->write("Error.");
        $response->withStatus(500);
        return $response;
    });
}

if (defined('PROVIDE_ATK_XML_RPC_ENDPOINT') && PROVIDE_ATK_XML_RPC_ENDPOINT) {
    $app->any('/xmlrpc.php', function (Request $request, Response $response, $args) {
        postClientToAtkDatabase($request);
        $response->getBody()->write("Error.");
        $response->withStatus(500);
        return $response;
    });
}

if (defined('PROVIDE_ATK_DOT_ENV_ENDPOINT') && PROVIDE_ATK_DOT_ENV_ENDPOINT) {
    $app->any('/.env', function (Request $request, Response $response, $args) {
        postClientToAtkDatabase($request);
        $response->getBody()->write("Error.");
        $response->withStatus(500);
        return $response;
    });
}

if (defined('PROVIDE_ATK_GIT_DIR_ENDPOINT') && PROVIDE_ATK_GIT_DIR_ENDPOINT) {
    $app->any('/.git[/{params:.*}]', function (Request $request, Response $response, $args) {
        postClientToAtkDatabase($request);
        $response->getBody()->write("Error.");
        $response->withStatus(500);
        return $response;
    });
}

$app->get('/', function (Request $request, Response $response, $args) {
    $view = Twig::fromRequest($request);
    return $view->render($response, 'index.html.twig', [
        'remoteIp' => getAccessingIp($request) ?? 'Unknown',
    ]);
});

$app->get('/info', function (Request $request, Response $response, $args) {
    $searchIp = $request->getQueryParams()['q'] ?? null;
    if (empty($searchIp)) {
        $response->getBody()->write("Missing IP parameter.");
        return $response->withStatus(400);
    }
    $view = Twig::fromRequest($request);

    $ipIsValid = filter_var($searchIp, FILTER_VALIDATE_IP);
    $ipIsNotPrivate = filter_var($searchIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE);

    if (!$ipIsValid || !$ipIsNotPrivate) {
        return $view->render($response, 'info.html.twig', [
            'ipIsInvalid' => !$ipIsValid,
            'ipIsPrivate' => !$ipIsNotPrivate,
            'ip' => $searchIp,
        ])->withStatus(400);
    }

    $ip = normalizeIp($searchIp);

    $db = db_init();
    if (!check_schema_version($db)) {
        $response->getBody()->write('Database schema version mismatch. Please run the update script.');
        return $response->withStatus(500);
    }

    $metaRdnsData = getReverseDnsInfo($db, $ip);
    if ($metaRdnsData !== null) {
        $metaRdnsData['last_checked_formatted'] = date('Y/m/d H:i:s', $metaRdnsData['last_checked']);
    }

    $dbIp = formatIpForDb($ip);

    $atkInfo = query_row_params($db, '
SELECT
    ip, ccode, asn,
    UNIX_TIMESTAMP(lastseen) AS lastseen,
    lastseen AS lastseen_formatted,
    UNIX_TIMESTAMP(addedat) AS addedat,
    addedat AS addedat_formatted
FROM atkIps WHERE ip = ? LIMIT 1', 's', [$dbIp]);

    $ipInAtk = !empty($atkInfo);

    if (isIp4($ip)) {
        $atkNeighbours = query_all_params($db, "
SELECT
    ip,
    UNIX_TIMESTAMP(lastseen) AS lastseen,
    lastseen AS lastseen_formatted,
    UNIX_TIMESTAMP(addedat) AS addedat,
    addedat AS addedat_formatted
FROM atkIps WHERE
    INET6_ATON(ip) BETWEEN 
        CONCAT(SUBSTRING(INET6_ATON(?), 1, LENGTH(INET6_ATON(?)) - 1), UNHEX('00')) 
        AND 
        CONCAT(SUBSTRING(INET6_ATON(?), 1, LENGTH(INET6_ATON(?)) - 1), UNHEX('FF'))
ORDER BY INET6_ATON(ip) ASC
LIMIT 256
        ", 'ssss', [$dbIp, $dbIp, $dbIp, $dbIp]);

        for ($i = 0; $i < count($atkNeighbours); $i++) {
            $atkNeighbours[$i]['ip'] = formatDbIpForUser($atkNeighbours[$i]['ip']);
        }
    } else {
        $atkNeighbours = [];
    }

    $ipInAtkNeighbours = !empty($atkNeighbours);
    $ipInAtkRelated = $ipInAtk || $ipInAtkNeighbours;

    return $view->render($response, 'info.html.twig', [
        'ip' => $searchIp,
        'meta_rdns_data' => $metaRdnsData,
        'ipInAtk' => $ipInAtk,
        'ipInAtkNeighbours' => $ipInAtkNeighbours,
        'ipInAtkRelated' => $ipInAtkRelated,
        'atk' => $atkInfo,
        'atkNeighbours' => $atkNeighbours,
    ]);
});

// Compatibility endpoints
$app->post('/wwwroot/atk/post.php', function (Request $request, Response $response, $args) {
    $res = sharedAtkPostProcess($request, $response, $args);
    if ($res !== null) {
        return $res;
    }

    return $response->withStatus(200);
})->add($atkBotAuthMiddleware);

$app->group('/atk', function (RouteCollectorProxy $group) use($atkBotAuthMiddleware, $atkManagerAuthMiddleware) {
    $group->get('/', function (Request $request, Response $response, $args) {
        $link = db_init();
        if (!check_schema_version($link)) {
            $response->getBody()->write('Database schema version mismatch. Please run the update script.');
            return $response->withStatus(500);
        }

        $ipCount = query_row_params($link, 'SELECT COUNT(*) AS count FROM atkIps');
        if (empty($ipCount)) {
            $response->getBody()->write('No information available. DB related error.');
            return $response->withStatus(500);
        }
        $ipCount = $ipCount['count'];

        $query = "
    SELECT count, day
    FROM (
        SELECT 1 AS day, COUNT(*) AS count FROM atkIps WHERE lastseen >= NOW() - INTERVAL 1 DAY
        UNION ALL
        SELECT 7 AS day, COUNT(*) AS count FROM atkIps WHERE lastseen >= NOW() - INTERVAL 7 DAY
        UNION ALL
        SELECT 14 AS day, COUNT(*) AS count FROM atkIps WHERE lastseen >= NOW() - INTERVAL 14 DAY
        UNION ALL
        SELECT 30 AS day, COUNT(*) AS count FROM atkIps WHERE lastseen >= NOW() - INTERVAL 30 DAY
        UNION ALL
        SELECT 60 AS day, COUNT(*) AS count FROM atkIps WHERE lastseen >= NOW() - INTERVAL 60 DAY
        UNION ALL
        SELECT 180 AS day, COUNT(*) AS count FROM atkIps WHERE lastseen >= NOW() - INTERVAL 180 DAY
        UNION ALL
        SELECT 365 AS day, COUNT(*) AS count FROM atkIps WHERE lastseen >= NOW() - INTERVAL 365 DAY
    ) AS subquery
";
        $results = query_all_params($link, $query);
        $atkPerDay = [];
        for($i = 0; $i < count($results); $i++) {
            $atkPerDay[$results[$i]['day']] = $results[$i]['count'];
        }

        $countryStats = query_all_params($link, 'SELECT ccode, COUNT(*) as cnt FROM atkIps WHERE lastseen >= NOW() - INTERVAL 30 DAY GROUP BY ccode ORDER BY cnt DESC LIMIT 10');
        $asnStats = query_all_params($link, 'SELECT asn, COUNT(*) as cnt FROM atkIps WHERE lastseen >= NOW() - INTERVAL 30 DAY GROUP BY asn ORDER BY cnt DESC LIMIT 10');

        $view = Twig::fromRequest($request);
        return $view->render($response, 'atk/index.html.twig', [
            'atkIpCnt' => $ipCount,
            'atkPerDay' => $atkPerDay,
            'countryStats' => $countryStats,
            'asnStats' => $asnStats,
        ]);
    });
    $group->get('/list', function (Request $request, Response $response, $args) {
        $link = db_init();
        if (!check_schema_version($link)) {
            $response->getBody()->write('Database schema version mismatch. Please run the update script.');
            return $response->withStatus(500);
        }

        $pageNo = 1;
        if (!empty($request->getQueryParams()['page']) && is_numeric($request->getQueryParams()['page'])) {
            $pageNo = intval($request->getQueryParams()['page']);
        }

        $atkIpCnt = query_row_params($link, "SELECT COUNT(*) AS count FROM atkIps");
        if (empty($atkIpCnt)) {
            $response->getBody()->write('No information available. DB related error.');
            return $response->withStatus(500);
        }
        $atkIpCnt = $atkIpCnt['count'];

        $pageCnt = floor($atkIpCnt / 100);

        $ips = query_all_params($link, "
SELECT
    atkIps.ip AS ip,
    ccode,
    asn,
    UNIX_TIMESTAMP(lastseen) AS lastseen,
    lastseen AS lastseen_formatted,
    UNIX_TIMESTAMP(addedat) AS addedat,
    addedat AS addedat_formatted,
    rdns,
    attack_count,
    is_frontend_attack
FROM 
    atkIps
    LEFT JOIN meta_rdns ON atkIps.ip = meta_rdns.ip
ORDER BY lastseen DESC
LIMIT 100 OFFSET ?
        ", 'i', [($pageNo - 1) * 100]);

        for ($i = 0; $i < count($ips); $i ++) {
            $ips[$i]['ip'] = formatDbIpForUser($ips[$i]['ip']);
        }

        $view = Twig::fromRequest($request);
        return $view->render($response, 'atk/list.html.twig', [
            'ips' => $ips,
            'page' => $pageNo,
            'pageCnt' => $pageCnt,
            'atkIpCnt' => $atkIpCnt,
        ]);
    });

    $group->get('/fgfeed[.php]', function (Request $request, Response $response, $args) {
        $link = db_init();
        if (!check_schema_version($link)) {
            $response->getBody()->write('Database schema version mismatch. Please run the update script.');
            return $response->withStatus(500);
        }

        $range = $request->getQueryParams()['range'] ?? 'host';
        $dayRange = intval($request->getQueryParams()['since'] ?? '0');
        $family = $request->getQueryParams()['family'] ?? 'ipv4,ipv6';

        if ($dayRange != 0) {
            $now = time();
            if (defined('MAX_ATK_FEED_TIME')) {
                $min_time = $now - MAX_ATK_FEED_TIME;
            } else {
                $min_time = 0;
            }

            $dayRange = $now - $dayRange;
            $dayRange = max($dayRange, $min_time, 0);

            if ($dayRange > $now) {
                $response->getBody()->write('You can not specify now or future date');
                return $response->withStatus(400);
            }
        } else if (defined('MAX_ATK_FEED_TIME')) {
            $dayRange = time() - MAX_ATK_FEED_TIME;
        }

        $list = getAtkFeedData($link, $range, $family, $dayRange);
        $body = $response->getBody();

        $body->write('# Attack detected IP feed' . PHP_EOL);
        $body->write(implode(PHP_EOL, $list));
        return $response
            ->withHeader('Content-Type', 'text/plain')
            ->withStatus(200);
    });

    // Compatibility endpoint for old ATK bot. New bots should use /atk/post with POST method.
    $group->post('/post.php', function (Request $request, Response $response, $args) {
        $res = sharedAtkPostProcess($request, $response, $args);
        if ($res !== null) {
            return $res;
        }

        return $response->withStatus(200);
    })->add($atkBotAuthMiddleware);
    $group->group('/post', function (RouteCollectorProxy $group) {
        $group->post('', function (Request $request, Response $response, $args) {
            $res = sharedAtkPostProcess($request, $response, $args);
            if ($res !== null) {
                return $res;
            }

            return $response->withStatus(200);
        });
    })->add($atkBotAuthMiddleware);

    $group->group('/admin', function (RouteCollectorProxy $group) {
        $group->get('/', function (Request $request, Response $response, $args) {
            $view = Twig::fromRequest($request);
            return $view->render($response, 'atk/admin/index.html.twig');
        });

        $group->post('/post', function (Request $request, Response $response, $args) {
            $res = sharedAtkPostProcess($request, $response, $args);
            if ($res !== null) {
                return $res;
            }

            $noRedirect = !empty($_POST['no_redirect']) && $_POST['no_redirect'] === '1';
            if ($noRedirect) {
                return $response->withStatus(200);
            } else {
                return $response
                    ->withHeader('Location', '/atk/list')
                    ->withStatus(303);
            }
        });

        $group->get('/postform', function (Request $request, Response $response, $args) {
            $view = Twig::fromRequest($request);
            return $view->render($response, 'atk/admin/postform.html.twig');
        });

        $group->get('/batchpostform', function (Request $request, Response $response, $args) {
            $view = Twig::fromRequest($request);
            return $view->render($response, 'atk/admin/batchpostform.html.twig');
        });

        $group->get('/unlistform', function (Request $request, Response $response, $args) {
            $view = Twig::fromRequest($request);
            return $view->render($response, 'atk/admin/unlistform.html.twig');
        });

        $group->post('/unlist', function (Request $request, Response $response, $args) {
            $ip = $_POST['ip'] ?? '';

            if (!validateIpIsPublic($ip)) {
                $response->getBody()->write('Invalid IP address.');
                return $response->withStatus(400);
            }

            $link = db_init();
            if (!check_schema_version($link)) {
                $response->getBody()->write('Database schema version mismatch. Please run the update script.');
                return $response->withStatus(500);
            }

            $dbIp = formatIpForDb(normalizeIp($ip));
            query_params($link, 'DELETE FROM atkIps WHERE ip = ?', 's', [$dbIp]);

            return $response
                ->withHeader('Location', '/atk/list')
                ->withStatus(303);
        });

        $group->group('/ignorelist', function (RouteCollectorProxy $group) {
            $group->get('/', function (Request $request, Response $response, $args) {
                $link = db_init();
                if (!check_schema_version($link)) {
                    $response->getBody()->write('Database schema version mismatch. Please run the update script.');
                    return $response->withStatus(500);
                }

                $ignoreList = query_all_params($link, 'SELECT id, network, cidr, description FROM atkDbIgnoreList');

                for ($i = 0; $i < count($ignoreList); $i++) {
                    $ignoreList[$i]['network'] = formatDbIpForUser($ignoreList[$i]['network']);
                }

                $remoteIp = getAccessingIp($request);
                $isIgnored = false;
                if (!empty($remoteIp)) {
                    $dbIp = formatIpForDb($remoteIp);
                    $isIgnored = checkIpForIgnoredDb($link, $remoteIp, $dbIp);
                }

                $view = Twig::fromRequest($request);
                return $view->render($response, 'atk/admin/ignorelist/index.html.twig', [
                    'ignoreList' =>$ignoreList,
                    'remoteIp' => $remoteIp,
                    'isIgnored' => $isIgnored,
                ]);
            });

            $group->post('/add', function (Request $request, Response $response, $args) {
                $network = $_POST['network'] ?? '';
                $cidr = $_POST['cidr'] ?? '';
                $description = $_POST['description'] ?? '';

                if (!validateIpIsPublic($network)) {
                    $response->getBody()->write('Invalid network address.');
                    return $response->withStatus(400);
                }

                if (!is_numeric($cidr) || intval($cidr) < 0 || intval($cidr) > 128) {
                    $response->getBody()->write('Invalid CIDR value.');
                    return $response->withStatus(400);
                }

                $link = db_init();
                if (!check_schema_version($link)) {
                    $response->getBody()->write('Database schema version mismatch. Please run the update script.');
                    return $response->withStatus(500);
                }

                $cidr = intval($cidr);
                if ($cidr < 0 || $cidr > 128) {
                    $response->getBody()->write('CIDR value must be between 0 and 128.');
                    return $response->withStatus(400);
                }

                query_params($link, 'INSERT INTO atkDbIgnoreList (network, cidr, description) VALUES (?, ?, ?)', 'sis', [
                    formatIpForDb(normalizeIp($network)),
                    $cidr,
                    $description,
                ]);

                return $response
                    ->withHeader('Location', '/atk/admin/ignorelist/')
                    ->withStatus(303);
            });

            $group->post('/delete', function (Request $request, Response $response, $args) {
                $id = $_POST['id'] ?? '';

                if (!is_numeric($id)) {
                    $response->getBody()->write('Invalid ID value.');
                    return $response->withStatus(400);
                }

                $link = db_init();
                if (!check_schema_version($link)) {
                    $response->getBody()->write('Database schema version mismatch. Please run the update script.');
                    return $response->withStatus(500);
                }

                query_params($link, 'DELETE FROM atkDbIgnoreList WHERE id = ?', 'i', [intval($id)]);

                return $response
                    ->withHeader('Location', '/atk/admin/ignorelist/')
                    ->withStatus(303);
            });
        });
    })->add($atkManagerAuthMiddleware);
});

$app->add(TwigMiddleware::create($app, $twig));
$app->add($sentryMiddleware);
$app->addRoutingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(false, true, true, $logger);
$errorMiddleware->setDefaultErrorHandler($customErrorHandler);


$app->run();
