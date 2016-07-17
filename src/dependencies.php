<?php
// DIC configuration

$container = $app->getContainer();

// view renderer
$container['renderer'] = function ($c) {
    $settings = $c->get('settings')['renderer'];
    return new Slim\Views\PhpRenderer($settings['template_path']);
};

// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], Monolog\Logger::DEBUG));
    return $logger;
};

// database
$container['db'] = function ($c) {
    $db = $c->get('settings')['db'];
    $pdo = new PDO("mysql:host=" . $db['host'] . ";dbname=" . $db['dbname'],
        $db['user'], $db['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
};

// mailer
$container['mailer'] = function($c) {
    $smtp = $c->get('settings')['smtp'];
    $mailer = new PHPMailer;

    // SMTP settings
    $mailer->SMTPDebug = 3;
    $mailer->isSMTP();
    $mailer->Host = $smtp['host'];
    $mailer->SMTPAuth = $smtp['auth'];
    $mailer->Username = $smtp['username'];
    $mailer->Password = $smtp['password'];
    $mailer->SMTPSecure = $smtp['secure'];
    $mailer->Port = $smtp['port'];

    // Default mail settings
    $mailer->CharSet = 'utf-8';
    $mailer->setFrom('no-reply@tchoukball.ch', 'Swiss Tchoukball');

    return $mailer;
};

// mailer
$container['emailAddresses'] = function($c) {
    $emailAddresses = $c->get('settings')['emailAddresses'];
    return $emailAddresses;
};