<?php
namespace Story\Model;

class CpAccount
{
    public static function create($cp_site_id)
    {
        global $app;
        $app['db']->insert('cp_account', compact('cp_site_id'));
        return $app['db']->lastInsertId();
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
