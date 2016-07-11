<?php
// Application middleware

// Note: Middleware are called in the reverse order they are added.

// e.g: $app->add(new \Slim\Csrf\Guard);

$container = $app->getContainer();

// Authorization
$app->add(function($request, $response, $next) use ($container) {
    $path = explode('/', $request->getUri()->getPath());
    $method = $request->getMethod();
    if ($path[1] == 'club' && $method == 'GET') {
        // Authorization to read a specific club data
        if (isset($request->getAttribute('routeInfo')[2]['clubId'])) {
            $clubId = $request->getAttribute('routeInfo')[2]['clubId'];

            $hasClubReadAccess = hasClubMembersReadAccess($container['user'], $clubId);
            // TODO: Implement hasClubTeamsReadAccess and check too in here for the correct path

            if ($hasClubReadAccess) {
                $response = $next($request, $response);
            } else {
                $response = $response->withStatus(403);
            }
        } else {
            // If no clubId is given
            $response = $response->withStatus(400);
        }
    } else {
        $response = $next($request, $response);
    }
    return $response;
});

// Authentication (for now, need to be authenticated for any path)
$app->add(new \Slim\Middleware\HttpBasicAuthentication([
    "users" => getUsers($container->get('db')),
    "callback" => function($request, $response, $arguments) use ($container) {
        try {
            $container['user'] = getUserByUsername($container->get('db'), $arguments['user']);
        } catch (PDOException $e) {
            return $response->withStatus(500)
                ->withHeader('Content-Type', 'text/html')
                ->write($e);
        }
        return $response;
    }
]));

// CORS
$app->add(new \Tuupola\Middleware\Cors([
    'origin' => ['http://localhost:8081', 'https://tchoukball.ch', 'https//www.tchoukball.ch'],
    'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'headers.allow' => ['Content-Type', 'X-Requested-With', 'Authorization', 'Accept-Language', 'Accept'],
    'headers.expose' => [],
    'credentials' => false,
    'cache' => 0,
]));