<?php
namespace Story\Model;

class Notice
{
    public static function get($id, $exclude_invisible = true)
    {
        $sql = <<<EOT
select * from notice where id = ?
EOT;
        if ($exclude_invisible) {
            $sql .= ' and is_visible = 1';
        }

        global $app;
        return $app['db']->fetchAssoc($sql, array($id));
    }

    public static function getList($exclude_invisible = true)
    {
        $sql = <<<EOT
select * from notice
EOT;
        if ($exclude_invisible) {
            $sql .= ' where is_visible = 1';
        }
        $sql .= ' order by reg_date desc';

        global $app;
        return $app['db']->fetchAll($sql);
    }

    public static function getNewNoticeCount()
    {
        $sql = <<<EOT
select count(*) from notice
where datediff(now(), reg_date) < 2
EOT;
        global $app;
        return $app['db']->fetchColumn($sql);
    }

    public static function create()
    {
        global $app;
        $app['db']->insert('notice', array('title' => '제목이 없습니다.', 'is_visible' => 0));
        return $app['db']->lastInsertId();
    }

    public static function update($id, $values)
    {
        global $app;
        return $app['db']->update('notice', $values, array('id' => $id));
    }

    public static function delete($id)
    {
        global $app;
        return $app['db']->delete('notice', array('id' => $id));
    }
}