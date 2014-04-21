<?php
namespace Story\Model;

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
                $sql = <<<EOT
insert ignore into push_devices (device_id, platform, device_token, is_active) values (?, ?, ?, 1)
EOT;
                $r = $app['db']->executeUpdate($sql, array($device_id, $platform, $device_token));
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
