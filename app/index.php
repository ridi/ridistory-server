<?
require_once '../lib/silex/vendor/autoload.php';

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

$app->get('/whole_list', function () use ($app) {
	$ar = $app['db']->fetchAll('SELECT C.name, B.title, B.author, B.catchphrase FROM book B JOIN category C ON C.id = c_id');
	$list = array();
	foreach ($ar as $b) {
		$cat_name = $b['name'];
		unset($b['name']);
		$list[$cat_name][] = $b;
	} 
	
	return $app->json($list);
});

require_once 'controllers/admin/book.php';

$app->get('/admin/book/list', 'Admin\BookController::index');
$app->get('/admin/book/{id}/', 'Admin\BookController::detail');
$app->get('/admin/book/edit/{id}/', 'Admin\BookController::edit');

$app->run();
