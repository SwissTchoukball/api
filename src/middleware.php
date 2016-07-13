<?php
// Application middleware

// Note: Middleware are called in the reverse order they are added.

// e.g: $app->add(new \Slim\Csrf\Guard);

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$container = $app->getContainer();

// Authorization
$app->add('SwissTchoukball\Middleware\Authorization');

// Authentication (for now, need to be authenticated for any path)
$app->add(new \Slim\Middleware\HttpBasicAuthentication([
    "users" => getUsers($container->get('db')),
    "callback" => function(Request $request, Response $response, $arguments) use ($container) {
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