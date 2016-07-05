<?php
// Application middleware

// e.g: $app->add(new \Slim\Csrf\Guard);

$container = $app->getContainer();

$app->add(new \Tuupola\Middleware\Cors([
    'origin' => ['http://localhost:8081', 'https://tchoukball.ch', 'https//www.tchoukball.ch'],
    'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'headers.allow' => ['Content-Type', 'X-Requested-With', 'Authorization', 'Accept-Language', 'Accept'],
    'headers.expose' => [],
    'credentials' => false,
    'cache' => 0,
]));


// TODO: authentication to specific paths
$app->add(new \Slim\Middleware\HttpBasicAuthentication([
    "users" => getUsers($container->get('db')),
    "callback" => function($request, $response, $arguments) use ($container) {
        //TODO: Check the user authorization on the request (e.g. access to specific club data)
        $_SESSION['__username__'] = $arguments['user'];
        return $response;
    }
]));