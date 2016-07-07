<?php
if (PHP_SAPI == 'cli-server') {
    // To help the built-in PHP dev server, check if the request was actually for
    // something which should probably be served as a static file
    $url  = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
    if (is_file($file)) {
        return false;
    }
}

require __DIR__ . '/../vendor/autoload.php';

session_start();

// Instantiate the app
$settings = require __DIR__ . '/../src/settings.php';
$app = new \Slim\App($settings);

// Set up dependencies
require __DIR__ . '/../src/dependencies.php';

// Register helpers
require __DIR__ . '/../src/helpers.php';

// Register middleware
require __DIR__ . '/../src/middleware.php';

// Register controllers
require __DIR__ . '/../src/controllers/ClubsController.php';
require __DIR__ . '/../src/controllers/VenuesController.php';
require __DIR__ . '/../src/controllers/ChampionshipController.php';

// Register routes
require __DIR__ . '/../src/routes.php';

// Defining timezone
date_default_timezone_set('Europe/Zurich');

// Run app
$app->run();
