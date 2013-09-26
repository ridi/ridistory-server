<?php

class UserInterest
{
    public static function set($device_id, $b_id)
    {
        global $app;
        $r = $app['db']->executeUpdate(
            'insert ignore user_interest (device_id, b_id) values (?, ?)',
            array($device_id, $b_id)
        );
        return $r === 1;
    }

    public static function clear($device_id, $b_id)
    {
        global $app;
        $r = $app['db']->delete('user_interest', compact('device_id', 'b_id'));
        return $r === 1;
    }

    public static function get($device_id, $b_id)
    {
        global $app;
        $r = $app['db']->fetchColumn(
            'select id from user_interest where device_id = ? and b_id = ?',
            array($device_id, $b_id)
        );
        return ($r !== false);
    }

    public static function hasInterestInBook($device_id, $b_id)
    {
        global $app;
        $r = $app['db']->fetchAssoc(
            'select * from user_interest where device_id = ? and b_id = ?',
            array($device_id, $b_id)
        );
        return ($r !== false);
    }

    public static function getList($device_id)
    {
        global $app;
        $r = $app['db']->fetchAll('select b_id from user_interest where device_id = ?', array($device_id));
        $b_ids = array();
        foreach ($r as $row) {
            $b_ids[] = $row['b_id'];
        }
        return $b_ids;
    }
}

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
        $r = $app['db']->delete('user_part_like', compact('device_id', 'p_id'));
        return $r;
    }

    public static function getLikeCount($p_id)
    {
        global $app;
        $r = $app['db']->fetchColumn('select count(*) from user_part_like where p_id = ?', array($p_id));
        return $r;
    }
}

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

class PushDevice
{
    public static function insertOrUpdate($device_id, $platform, $device_token)
    {
        global $app;
        $info = PushDevice::getByDeviceId($device_id);

        if ($info) {
            // device_id가 이미 있는 경우 처리
            if ($info['platform'] == $platform && $info['device_token'] == $device_token && $info['is_active']) {
                return true;
            }

            $data = array('platform' => $platform, 'device_token' => $device_token, 'is_active' => 1);
            $where = array('device_id' => $device_id);
            $r = $app['db']->update('push_devices', $data, $where);
            return $r === 1;
        } else {
            $info = PushDevice::getByDeviceToken($device_token);

            if ($info) {
                // device_token이 이미 있는 경우 처리
                if ($info['platform'] == $platform && $info['device_id'] == $device_id && $info['is_active']) {
                    return true;
                }

                $data = array('platform' => $platform, 'device_id' => $device_id, 'is_active' => 1);
                $where = array('device_token' => $device_token);
                $r = $app['db']->update('push_devices', $data, $where);
                return $r === 1;
            } else {
                // 한번도 등록되지 않은 device_id, device_token일 경우 처리
                $data = array(
                    'device_id' => $device_id,
                    'platform' => $platform,
                    'device_token' => $device_token,
                    'is_active' => 1
                );
                $r = $app['db']->insert('push_devices', $data);
                return $r === 1;
            }
        }
    }

    public static function getByDeviceId($device_id)
    {
        global $app;
        $r = $app['db']->fetchAssoc('select * from push_devices where device_id = ?', array($device_id));
        return $r;
    }

    public static function getByDeviceToken($device_token)
    {
        global $app;
        $r = $app['db']->fetchAssoc('select * from push_devices where device_token = ?', array($device_token));
        return $r;
    }

    public static function delete($device_id)
    {
        global $app;
        $r = $app['db']->delete('push_devices', compact('device_id'));
        return $r === 1;
    }

    public static function deactivate($pk)
    {
        global $app;
        $r = $app['db']->update('push_devices', array('is_active' => 0), array('id' => $pk));
        return $r === 1;
    }

    public static function update($pk, $device_token)
    {
        global $app;
        $r = $app['db']->update('push_devices', array('device_token' => $device_token), array('id' => $pk));
        return $r === 1;
    }
}


?>