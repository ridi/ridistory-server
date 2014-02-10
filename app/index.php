<?php
$autoloader = require_once '../lib/vendor/autoload.php';
$autoloader->add('Story', '../src');

$app = new Silex\Application();

$app->register(
    new Silex\Provider\TwigServiceProvider(),
    array(
        'twig.path' => __DIR__ . '/views',
    )
);

require 'conf.php';

$app->register(new Silex\Provider\DoctrineServiceProvider());
$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\SecurityServiceProvider());

$app->register(new Story\Provider\CacheServiceProvider());

require_once 'controllers/api.php';
require_once 'controllers/admin.php';
require_once 'controllers/admin/book.php';
require_once 'controllers/admin/part.php';
require_once 'controllers/admin/storyplusbook.php';
require_once 'controllers/admin/storyplusbook_intro.php';
require_once 'controllers/web.php';

$app->mount('/', new WebControllerProvider());
$app->mount('/api', new ApiControllerProvider());
$app->mount('/admin/book', new AdminBookControllerProvider());
$app->mount('/admin/part', new AdminPartControllerProvider());
$app->mount('/admin/storyplusbook', new AdminStoryPlusBookControllerProvider());
$app->mount('/admin/storyplusbook_intro', new AdminStoryPlusBookIntroControllerProvider());
$app->mount('/admin', new AdminControllerProvider());

$app->run();
