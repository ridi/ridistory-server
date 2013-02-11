<?
require_once 'silex/vendor/autoload.php';

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
		'dbname' => 'changelog',
		'charset' => 'utf8',
	),
));

$app->register(new Silex\Provider\SessionServiceProvider(), array());

$app['debug'] = true;

$app->get('/', function () use ($app) {
	$recentDates = $app['db']->fetchAll('SELECT distinct(date) FROM changes ORDER BY date DESC');
	$changes = array();
	foreach ($recentDates as $d) {
		$date = $d['date'];
		$changes[$date] = $app['db']->fetchAll('SELECT type, comment FROM changes WHERE date = ?', array($date));
		usort($changes[$date], function($a, $b) {
			return strcmp($a['type'], $b['type']);
		});
	}
	return $app['twig']->render('index.twig', array(
		'today' => date('Ymd'),
		'all_changes' => $changes,
	));
});

$app->post('/add', function (Request $request) use ($app) {
	/*
	$user = $app['session']->get('user');
	if ($user === null) {
		return $app->redirect('/');
	}
	 */
	
	$ret = $app['db']->insert('changes', array(
		'date' => $request->get('date'),
		'type' => $request->get('type'),
		'comment' => $request->get('comment'),
	));
	
	if ($ret !== 1) {
		$app->abort(500, "Couldn't add an item.");
	}
	
	return $app->redirect('/');
});

$app->post('/login', function (Request $request) use ($app) {
	$id = $request->get('id');
	$password = $request->get('password');
	
	if ($id === 'admin' && $password === '10') {
		$app['session']->set('user', array('id' => $id)); 
		return $app->redirect('/');
	} 
	
	return 'Not authorized.';
});

$app->get('/admin', function () use ($app) {
	$user = $app['session']->get('user');
	return "Welcome {$user['id']}";
});

$app->run();
