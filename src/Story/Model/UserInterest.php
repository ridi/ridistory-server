<?php
namespace Story\Model;

class UserInterest
{
    public static function set($device_id, $b_id)
    {
        global $app;
        $app['db']->executeUpdate(
            'insert user_interest (device_id, b_id) values (?, ?) on duplicate key update cancel = 0',
            array($device_id, $b_id)
        );
        return true;
    }

    public static function clear($device_id, $b_id)
    {
        global $app;
        $r = $app['db']->update('user_interest', array('cancel' => 1), compact('device_id', 'b_id'));
        return $r === 1;
    }

    public static function get($device_id, $b_id)
    {
        global $app;
        $r = $app['db']->fetchColumn(
            'select id from user_interest where device_id = ? and b_id = ? and cancel = 0',
            array($device_id, $b_id)
        );
        return ($r !== false);
    }

    public static function hasInterestInBook($device_id, $b_id)
    {
        global $app;
        $r = $app['db']->fetchAssoc(
            'select * from user_interest where device_id = ? and b_id = ? and cancel = 0',
            array($device_id, $b_id)
        );
        return ($r !== false);
    }

    public static function getList($device_id)
    {
        global $app;
        $r = $app['db']->fetchAll('select b_id from user_interest where device_id = ? and cancel = 0', array($device_id));
        $b_ids = array();
        foreach ($r as $row) {
            $b_ids[] = $row['b_id'];
        }
        return $b_ids;
    }
}
