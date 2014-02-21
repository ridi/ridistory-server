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
        $sql = <<<EOT
select ifnull(book_count, 0) book_count, cp.* from cp_account cp
 left join (select cp_id, count(*) book_count from book group by cp_id) b on b.cp_id = cp.id
order by cp.name
EOT;
        global $app;
        return $app['db']->fetchAll($sql);
    }

    public static function getCpList()
    {
        $sql = <<<EOT
select id, name from cp_account
order by name
EOT;
        global $app;
        return $app['db']->fetchAll($sql);
    }
}