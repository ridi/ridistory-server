<?php

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
