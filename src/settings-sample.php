<?php
return [
    'settings' => [
        'displayErrorDetails' => true, // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header
        'determineRouteBeforeAppMiddleware' => true,

        // Renderer settings
        'renderer' => [
            'template_path' => __DIR__ . '/../templates/',
        ],

        // Monolog settings
        'logger' => [
            'name' => 'slim-app',
            'path' => __DIR__ . '/../logs/app.log',
        ],
        'db' => [
            'host' => '',
            'user' => '',
            'pass' => '',
            'dbname' => ''
        ],
        'smtp' => [
            'host' => '',
            'auth' => false,
            'username' => '',
            'password' => '',
            'port' => '',
            'secure' => '' // ssl or tls
        ],
        'emailAddresses' => [
            'developers' => '',
            'headOfChampionship' => '',
            'headOfFinances' => ''
        ]
    ],
];
