<?php
namespace Story\Model;

class PartComment
{
    const ADMIN_NICKNAME = '리디스토리';

    public static function add($p_id, $device_id, $nickname, $comment, $ip)
    {
        global $app;
        $r = $app['db']->insert('part_comment', compact('p_id', 'device_id', 'nickname', 'comment', 'ip'));
        return $r;
    }

    public static function delete($c_id)
    {
        global $app;
        $r = $app['db']->delete('part_comment', array('id' => $c_id));
        return $r === 1;
    }

    public static function getList($p_id, $exclude_admin_comment = true)
    {
        $sql = <<<EOT
select * from part_comment
where p_id = ?
EOT;
        if ($exclude_admin_comment) {
            $sql .= ' and nickname != "' . PartComment::ADMIN_NICKNAME . '"';
        }
        $sql .= ' order by timestamp desc limit 100';

        global $app;
        return $app['db']->fetchAll($sql, array($p_id));
    }

    public static function getAdminComments($p_id)
    {
        $admin_nickname = PartComment::ADMIN_NICKNAME;

        $sql = <<<EOT
select * from part_comment
where p_id = ? and nickname = '{$admin_nickname}' order by timestamp desc
EOT;
        global $app;
        return $app['db']->fetchAll($sql, array($p_id));
    }

    public static function getCommentCount($p_id, $exclude_admin_comment = true)
    {
        $sql = <<<EOT
select count(*) from part_comment
where p_id = ?
EOT;
        if ($exclude_admin_comment) {
            $sql .= ' and nickname !="' . PartComment::ADMIN_NICKNAME . '"';
        }

        global $app;
        return $app['db']->fetchColumn($sql, array($p_id));
    }
}
