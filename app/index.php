<?
require_once '../lib/vendor/autoload.php';

use Silex\Provider\TwigServiceProvider;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\SecurityServiceProvider;

$app = new Silex\Application();

$app->register(new TwigServiceProvider(), array(
	'twig.path' => __DIR__ . '/views',
));

require 'conf.php';

$app->register(new DoctrineServiceProvider());
$app->register(new SessionServiceProvider());
$app->register(new SecurityServiceProvider());


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
