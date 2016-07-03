<?php
// Application middleware

// e.g: $app->add(new \Slim\Csrf\Guard);

$app->add(new \Tuupola\Middleware\Cors([
    'origin' => ['http://localhost:8081', 'https://tchoukball.ch', 'https//www.tchoukball.ch'],
    'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'headers.allow' => ['Content-Type', 'X-Requested-With', 'Authorization', 'Accept-Language', 'Accept'],
    'headers.expose' => [],
    'credentials' => false,
    'cache' => 0,
]));