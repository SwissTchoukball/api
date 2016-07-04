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


// TODO: authentication to specific paths with callback to check the user authorization on the request (e.g. access to specific club data)
// TODO: add the username in the request
$app->add(new \Slim\Middleware\HttpBasicAuthentication([
    "users" => getUsers($container->get('db'))
]));