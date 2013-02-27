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
		'host' => 'localhost',
		'user' => 'root',
		'password' => '10',
		'dbname' => 'story',
		'charset' => 'utf8',
	),
));

$app->register(new Silex\Provider\SessionServiceProvider(), array());

$app['debug'] = true;

require_once 'models/models.php';

$app->get('/', function() use ($app) {
	return $app->redirect('/admin/book/list');
});

$app->get('/api_list', function() use ($app) {
	return $app['twig']->render('api_list.twig');
});

$app->mount('/api', include 'controllers/api.php');
$app->mount('/admin', include 'controllers/admin.php');


$app->run();
