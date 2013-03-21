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
			'admin' => array('ROLE_ADMIN', 'wtGTsRVkEvRgi2r9TxHHe7/uuwX16mh3sAT2pwQZDrpdTFYXqCkN9Vvuy5vGd7ZLTNi5LKlJu2fjTZQask60fA=='),
		),
	),
);

use Doctrine\DBAL\Logging\EchoSQLLogger;
if ($app['debug'] = true) {
	//$app['db']->getConfiguration()->setSQLLogger(new EchoSQLLogger());
}
