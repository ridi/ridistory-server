<?php
namespace Story\Model;

class CoinProduct
{
    const TYPE_ALL = 'ALL';
    const TYPE_INAPP = 'GOOGLE';
    const TYPE_RIDICASH = 'RIDICASH';

    public static function createCoinProduct()
    {
        global $app;
        $app['db']->insert('inapp_products', array());
        return $app['db']->lastInsertId();
    }

    public static function updateCoinProduct($id, $values)
    {
        global $app;
        return $app['db']->update('inapp_products', $values, array('id' => $id));
    }

    public static function deleteCoinProduct($id)
    {
        global $app;
        return $app['db']->delete('inapp_products', array('id' => $id));
    }

    public static function getCoinProduct($id)
    {
        global $app;
        return $app['db']->fetchAssoc('select * from inapp_products where id = ?', array($id));
    }

    public static function getCoinProductBySkuAndType($sku, $type)
    {
        global $app;
        return $app['db']->fetchAssoc('select * from inapp_products where sku = ? and type = ?', array($sku, $type));
    }

    public static function getCoinProductsByType($type)
    {
        global $app;

        if ($type == CoinProduct::TYPE_ALL) {
            $sql = <<<EOT
select * from inapp_products order by type asc, coin_amount asc
EOT;
            return $app['db']->fetchAll($sql);
        } else {
            $sql = <<<EOT
select * from inapp_products where type = ? order by coin_amount asc
EOT;
            return $app['db']->fetchAll($sql, array($type));
        }
    }
}