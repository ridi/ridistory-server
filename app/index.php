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

require_once 'controllers/controllers.php';
require_once 'models/models.php';

$app->get('/api/book/list', function () use ($app) {
	$ar = $app['db']->fetchAll('SELECT C.name, B.title, B.author, B.catchphrase FROM book B JOIN category C ON C.id = c_id');
	$list = array();
	foreach ($ar as $b) {
		$cat_name = $b['name'];
		unset($b['name']);
		$list[$cat_name][] = $b;
	} 
	
	return $app->json($list);
});

$app->get('/', function() use ($app) {
	return $app->redirect('/admin/book/list');
});

$app->get('/api/book/{id}', function ($id) use ($app) {
	$book = Book::get($id);
	return $app->json($book); 
});

$app->get('/api/book/{id}/parts', function ($id) use ($app) {
	$parts = Part::getByBid($id);
	return $app->json($parts);
});


$app->get('/admin/book/list', 'BookController::index');
$app->get('/admin/book/add', 'BookController::add');
$app->get('/admin/book/{id}', 'BookController::detail');
$app->post('/admin/book/{id}/delete', 'BookController::delete');
$app->post('/admin/book/{id}/edit', 'BookController::edit');

$app->get('/admin/part/add', 'PartController::add');
$app->get('/admin/part/{id}', 'PartController::detail');
$app->get('/admin/part/{id}/delete', 'PartController::delete');
$app->post('/admin/part/{id}/edit', 'PartController::edit');

$app->run();
