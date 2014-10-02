<?php

$app['db.options'] = array(
    'driver' => 'pdo_mysql',
    'host' => 'dev.ridi.kr',
    'user' => 'dev',
    'password' => 'rbx120303',
    'dbname' => 'story_' . date('Ymd'),
    //'dbname' => 'story_20140811',
    'charset' => 'utf8',
    // MySQL 설정에 따라 필요할 수도, 안필요할 수도
    //'driverOptions' => array(
    //	PDO::MYSQL_ATTR_INIT_COMMAND => 'set names utf8'
    //)
);

$app['cache.namespace'] = 'story_test_v10';

$app['debug'] = true;

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

$app['sentry.options'] = array(
    'dsn' => 'https://525c32e7d6884fad9e2b9c8315d8340d:f44cc61cfc284212a3056602ffe964db@app.getsentry.com/21581',
    // ... and other sentry options
);

define('STORE_API_BASE_URL', 'http://ridibooks.com');
define('TEST_STORE_API_BASE_URL', 'http://hw.dev.ridi.kr');
