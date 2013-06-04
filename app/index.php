<?
$autoloader = require_once '../lib/vendor/autoload.php';
$autoloader->add('Ridibooks\Story', __DIR__);

$app = new Silex\Application();

$app->register(new Silex\Provider\TwigServiceProvider(), array(
	'twig.path' => __DIR__ . '/views',
));

require 'conf.php';

$app->register(new Silex\Provider\DoctrineServiceProvider());
$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\SecurityServiceProvider());

$app->register(new Ridibooks\Story\Provider\CacheServiceProvider());

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
