<?
require_once '../lib/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

$app = new Silex\Application();

$app->register(new Silex\Provider\TwigServiceProvider(), array(
	'twig.path' => __DIR__ . '/views',
));

$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
	'db.options' => array(
		'driver' => 'pdo_mysql',
		'host' => 'dev.ridibooks.kr',
		'user' => 'root',
		'password' => 'rbx120303',
		'dbname' => 'story',
		'charset' => 'utf8',
		// MySQL 설정에 따라 필요할 수도, 안필요할 수도
		'driverOptions' => array(
			PDO::MYSQL_ATTR_INIT_COMMAND => 'set names utf8'
		)
	),
));

$app->register(new Silex\Provider\SessionServiceProvider(), array());
/*
$app->register(new Silex\Provider\SecurityServiceProvider(), array(
    'security.firewalls' => array(
    	'admin' => array(
    		'pattern' => '^/admin',
    		'http' => true,
    		'users' => array(
    			'admin' => array('ROLE_ADMIN', 'Iv8muTbXrq3KkPHI2RT2gPQDJy3u5Bs8mTLOf0mC71+CfZLXpEuUaZhJKcG4BT3d0PZrfNhBlpXd6eQc5Wzxow=='),
    		),
    	),
    ),
));
*/
use Doctrine\DBAL\Logging\EchoSQLLogger;
if ($app['debug'] = true) {
	//$app['db']->getConfiguration()->setSQLLogger(new EchoSQLLogger());
}

require_once 'models/Book.php';
require_once 'models/Part.php';
require_once 'models/User.php';
require_once 'controllers/api.php';
require_once 'controllers/admin.php';
require_once 'controllers/web.php';

$app->mount('/', new WebControllerProvider());
$app->mount('/api', new ApiControllerProvider());
$app->mount('/admin', new AdminControllerProvider());

$app->run();
