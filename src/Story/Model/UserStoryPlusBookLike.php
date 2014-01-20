<?php
namespace Story\Model;

class UserStoryPlusBookLike
{
    public static function like($device_id, $b_id)
    {
        global $app;
        $r = $app['db']->executeUpdate(
            'insert ignore user_storyplusbook_like (device_id, b_id) values (?, ?)',
            array($device_id, $b_id)
        );
        return $r;
    }

    public static function unlike($device_id, $b_id)
    {
        global $app;
        $r = $app['db']->delete('user_storyplusbook_like', compact('device_id', 'b_id'));
        return $r;
    }

    public static function getLikeCount($b_id)
    {
        global $app;
        $r = $app['db']->fetchColumn('select count(*) from user_storyplusbook_like where b_id = ?', array($b_id));
        return $r;
    }
}
