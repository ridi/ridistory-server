<?php
namespace Story\Model;

class UserPartLike
{
    public static function like($device_id, $p_id)
    {
        global $app;
        $r = $app['db']->executeUpdate(
            'insert ignore user_part_like (device_id, p_id) values (?, ?)',
            array($device_id, $p_id)
        );
        return $r;
    }

    public static function unlike($device_id, $p_id)
    {
        global $app;
        $r = $app['db']->delete('user_part_like', array('device_id' => $device_id, 'p_id' => $p_id));
        return $r;
    }

    public static function getLikeCount($p_id)
    {
        global $app;
        $r = $app['db']->fetchColumn('select count(*) from user_part_like where p_id = ?', array($p_id));
        return $r;
    }
}
