<?php

$app['twig.path'] = array('views');

$app['db.options'] = array(
    'driver' => 'pdo_mysql',
    #'host' => 'dev.ridibooks.kr',
    'host' => 'localhost',
    'user' => 'root',
    'password' => '10',
    'dbname' => 'story',
    'charset' => 'utf8',
    // MySQL 설정에 따라 필요할 수도, 안필요할 수도
    //'driverOptions' => array(
    //	PDO::MYSQL_ATTR_INIT_COMMAND => 'set names utf8'
    //)
);

$app['security.firewalls'] = array(
    'admin' => array(
        'pattern' => '^/admin',
        'http' => true,
        'users' => array(
            'admin' => array(
                'ROLE_ADMIN',
                'wtGTsRVkEvRgi2r9TxHHe7/uuwX16mh3sAT2pwQZDrpdTFYXqCkN9Vvuy5vGd7ZLTNi5LKlJu2fjTZQask60fA=='
            ),
        ),
    ),
);

// TO CREATE PASSWORD
/*
	$user = new Symfony\Component\Security\Core\User\User('admin', '');
	$encoder = $app['security.encoder_factory']->getEncoder($user);
	$password = $encoder->encodePassword('rbxTjdigu!@#', $user->getSalt());
 */

/*
use Doctrine\DBAL\Logging\EchoSQLLogger;

if ($app['debug'] == true) {
    $app['db']->getConfiguration()->setSQLLogger(new EchoSQLLogger());
}
*/

use Moriony\Silex\Provider\SentryServiceProvider;
if (!$app['debug']) {
    $app->register(
        new SentryServiceProvider,
        array(
            'sentry.options' => array(
                'dsn' => 'https://0b1148ed3b11405596ba90b4a26bc016:f17fee722aca41f896893fd8ded83a2b@app.getsentry.com/11327',
                // ... and other sentry options
            )
        )
    );
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

define('STORE_API_BASE_URL', 'http://ridibooks.com');
