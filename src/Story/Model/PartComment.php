<?php
namespace Story\Model;

class PartComment
{
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

    public static function getList($p_id)
    {
        global $app;
        $r = $app['db']->fetchAll(
            'select * from part_comment where p_id = ? order by timestamp desc limit 100',
            array($p_id)
        );
        return $r;
    }

    public static function getCommentCount($p_id)
    {
        global $app;
        $r = $app['db']->fetchColumn('select count(*) from part_comment where p_id = ?', array($p_id));
        return $r;
    }
}
