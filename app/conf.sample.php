<?php

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

$app['cache.namespace'] = 'story_v5';

$app['security.firewalls'] = array(
	'admin' => array(
		'pattern' => '^/admin$',
		'http' => true,
		'users' => array(
			'admin' => array('ROLE_ADMIN', 'a9J7WOe9ezSyXhfIjByZ6Q7xbLGV/69MfQK0RC1zNci+FEYSkt+FnKkomCwtl/G+8+B4HagjKiaQ+JOlUg6N+A=='),
		),
	),
	'coin_product' => array(
		'pattern' => '^/admin/coin_product',
		'http' => true,
		'users' => array(
			'admin' => array('ROLE_ADMIN', '1kf5FXPNsNYWDMPwzWiwSPxELNpzdfzByaTV+mjYuaOZfZ2VR1Irvs1KxAe4CsyBIMVdLJw/GT8a+4RPOB65Ww=='),
		),
	),
	'buyer' => array(
		'pattern' => '^/admin/buyer',
		'http' => true,
		'users' => array(
			'admin' => array('ROLE_ADMIN', '1kf5FXPNsNYWDMPwzWiwSPxELNpzdfzByaTV+mjYuaOZfZ2VR1Irvs1KxAe4CsyBIMVdLJw/GT8a+4RPOB65Ww=='),
		),
	),
	'cp_account' => array(
		'pattern' => '^/admin/cp_account',
		'http' => true,
		'users' => array(
			'admin' => array('ROLE_ADMIN', '1kf5FXPNsNYWDMPwzWiwSPxELNpzdfzByaTV+mjYuaOZfZ2VR1Irvs1KxAe4CsyBIMVdLJw/GT8a+4RPOB65Ww=='),
		),
	),
	'download_sales' => array(
		'pattern' => '^/admin/download_sales',
		'http' => true,
		'users' => array(
			'admin' => array('ROLE_ADMIN', '1kf5FXPNsNYWDMPwzWiwSPxELNpzdfzByaTV+mjYuaOZfZ2VR1Irvs1KxAe4CsyBIMVdLJw/GT8a+4RPOB65Ww=='),
		),
	),
	'inapp_sales' => array(
		'pattern' => '^/admin/inapp_sales',
		'http' => true,
		'users' => array(
			'admin' => array('ROLE_ADMIN', '1kf5FXPNsNYWDMPwzWiwSPxELNpzdfzByaTV+mjYuaOZfZ2VR1Irvs1KxAe4CsyBIMVdLJw/GT8a+4RPOB65Ww=='),
		),
	),
	'ridicash_sales' => array(
		'pattern' => '^/admin/ridicash_sales',
		'http' => true,
		'users' => array(
			'admin' => array('ROLE_ADMIN', '1kf5FXPNsNYWDMPwzWiwSPxELNpzdfzByaTV+mjYuaOZfZ2VR1Irvs1KxAe4CsyBIMVdLJw/GT8a+4RPOB65Ww=='),
		),
	),
	'stats' => array(
		'pattern' => '^/admin/stats',
		'http' => true,
		'users' => array(
			'admin' => array('ROLE_ADMIN', '1kf5FXPNsNYWDMPwzWiwSPxELNpzdfzByaTV+mjYuaOZfZ2VR1Irvs1KxAe4CsyBIMVdLJw/GT8a+4RPOB65Ww=='),
		),
	),
	'test_user' => array(
		'pattern' => '^/admin/test_user',
		'http' => true,
		'users' => array(
			'admin' => array('ROLE_ADMIN', '1kf5FXPNsNYWDMPwzWiwSPxELNpzdfzByaTV+mjYuaOZfZ2VR1Irvs1KxAe4CsyBIMVdLJw/GT8a+4RPOB65Ww=='),
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

$app['sentry.options'] = array(
	'dsn' => 'https://525c32e7d6884fad9e2b9c8315d8340d:f44cc61cfc284212a3056602ffe964db@app.getsentry.com/21581',
	// ... and other sentry options
);

define('STORE_API_BASE_URL', 'http://ridibooks.com');