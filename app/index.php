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
//$app->register(new Silex\Provider\SecurityServiceProvider());
$app->register(new Story\Provider\CacheServiceProvider());

require_once 'controllers/admin/book.php';
require_once 'controllers/admin/recommended_book.php';
require_once 'controllers/admin/buyer.php';
require_once 'controllers/admin/cp_account.php';
require_once 'controllers/admin/download_sales.php';
require_once 'controllers/admin/part.php';
require_once 'controllers/admin/storyplusbook.php';
require_once 'controllers/admin/storyplusbook_intro.php';

$app->mount('/', new Story\Controller\WebController());
$app->mount('/api', new Story\Controller\ApiController());
$app->mount('/admin', new Story\Controller\AdminController());
$app->mount('/admin/book', new AdminBookControllerProvider());
$app->mount('/admin/buyer', new AdminBuyerControllerProvider());
$app->mount('/admin/cp_account', new AdminCpAccountControllerProvider());
$app->mount('/admin/download_sales', new AdminDownloadSalesControllerProvider());
$app->mount('/admin/part', new AdminPartControllerProvider());
$app->mount('/admin/recommended_book', new AdminRecommendedBookControllerProvider());
$app->mount('/admin/storyplusbook', new AdminStoryPlusBookControllerProvider());
$app->mount('/admin/storyplusbook_intro', new AdminStoryPlusBookIntroControllerProvider());

$app->run();
