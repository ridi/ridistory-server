<?php
$autoloader = require_once __DIR__ . '/../lib/vendor/autoload.php';
$autoloader->add('Story', '../src');

$app = new Silex\Application();

require 'conf.php';
require 'security.php';

// Twig은 config DI 안됨. why?
$app->register(
    new Silex\Provider\TwigServiceProvider(),
    array(
        'twig.path' => __DIR__ . '/views',
    )
);

$app->register(new Silex\Provider\DoctrineServiceProvider());
$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\SecurityServiceProvider());
$app->register(new Silex\Provider\ServiceControllerServiceProvider());
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Story\SilexServiceProvider\CacheServiceProvider());

if ($app['debug']) {
    /*
    $app->register(new Silex\Provider\WebProfilerServiceProvider(),
        array(
            'profiler.cache_dir' => __DIR__ . '/../cache/profiler',
            'profiler.mount_prefix' => '/_profiler', // this is the default
        )
    );
    */
} else {
	$app->register(new \Moriony\Silex\Provider\SentryServiceProvider());
	$app->error(
		function (\Exception $e, $code) use ($app) {
			$client = $app['sentry'];
			$client->captureException($e);
		}
	);

	$eh = $app['sentry.error_handler'];
	$eh->registerExceptionHandler();
	$eh->registerErrorHandler();
	$eh->registerShutdownFunction();
}


$app->mount('/', new Story\Controller\WebController());
$app->mount('/api', new Story\Controller\ApiController());
$app->mount('/admin', new Story\Controller\AdminController());
$app->mount('/admin/book', new Story\Controller\Admin\BookController());
$app->mount('/admin/book/notice', new \Story\Controller\Admin\BookNoticeController());
$app->mount('/admin/book/recommended_book', new Story\Controller\Admin\RecommendedBookController());
$app->mount('/admin/buyer', new Story\Controller\Admin\BuyerController());
$app->mount('/admin/coin_product', new Story\Controller\Admin\CoinProductController());
$app->mount('/admin/cp_account', new Story\Controller\Admin\CpAccountController());
$app->mount('/admin/download_sales', new Story\Controller\Admin\DownloadSalesController());
$app->mount('/admin/notice', new \Story\Controller\Admin\NoticeController());
$app->mount('/admin/part', new Story\Controller\Admin\PartController());
$app->mount('/admin/push', new Story\Controller\Admin\PushNotificationController());
$app->mount('/admin/stats', new \Story\Controller\Admin\StatsController());
$app->mount('/admin/storyplusbook', new Story\Controller\Admin\StoryPlusBookController());
$app->mount('/admin/storyplusbook_intro', new Story\Controller\Admin\StoryPlusBookIntroController());
$app->mount('/admin/test_user', new Story\Controller\Admin\TestUserController());
$app->mount('/cp_admin', new Story\Controller\CpAdmin\CpAdminController());

$app->run();
