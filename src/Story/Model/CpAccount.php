<?php
namespace Story\Model;

class CpAccount
{
    public static function create()
    {
        global $app;
        $app['db']->insert('cp_account', array());
        return $app['db']->lastInsertId();
    }

    public static function update($id, $values)
    {
        global $app;
        return $app['db']->update('cp_account', $values, array('id' => $id));
    }

    public static function delete($id)
    {
        global $app;
        return $app['db']->delete('cp_account', array('id' => $id));
    }

    public static function get($id)
    {
        $sql = <<<EOT
select * from cp_account where id = ?
EOT;

        global $app;
        return $app['db']->fetchAssoc($sql, array($id));
    }

    public static function getWholeList()
    {
        global $app;
        return $app['db']->fetchAll('select * from cp_account');
    }
}