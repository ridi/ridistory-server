<?php
namespace Story\Model;

class StoryPlusBookComment
{
    public static function add($b_id, $device_id, $comment, $ip)
    {
        $bind = array(
            'b_id' => $b_id,
            'device_id' => $device_id,
            'comment' => $comment,
            'ip' => $ip,
        );

        global $app;
        $r = $app['db']->insert('storyplusbook_comment', $bind);
        return $r;
    }

    public static function delete($c_id)
    {
        global $app;
        $r = $app['db']->delete('storyplusbook_comment', array('id' => $c_id));
        return $r === 1;
    }

    public static function getList($b_id)
    {
        global $app;
        $r = $app['db']->fetchAll(
            'select comment, `timestamp` from storyplusbook_comment where b_id = ? order by timestamp desc',
            array($b_id)
        );
        return $r;
    }

    public static function getCommentCount($b_id)
    {
        global $app;
        $r = $app['db']->fetchColumn('select count(*) from storyplusbook_comment where b_id = ?', array($b_id));
        return $r;
    }
}