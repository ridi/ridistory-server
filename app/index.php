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
		'host' => '192.168.1.216',
		'user' => 'root',
		'password' => '10',
		'dbname' => 'story',
		'charset' => 'utf8',
	),
));

$app->register(new Silex\Provider\SessionServiceProvider(), array());

use Doctrine\DBAL\Logging\EchoSQLLogger;
if ($app['debug'] = true) {
	//$app['db']->getConfiguration()->setSQLLogger(new EchoSQLLogger());
}

require_once 'models/Book.php';
require_once 'models/Part.php';
require_once 'models/User.php';
require_once 'controllers/api.php';
require_once 'controllers/admin.php';
require_once 'controllers/comment.php';

$app->get('/', function() use ($app) {
	return $app->redirect('/admin/book/list');
});


$app->mount('/api', new ApiControllerProvider());
$app->mount('/admin', new AdminControllerProvider());

$app->mount('/comment', new CommentControllerProvider());

$app->run();
