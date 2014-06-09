<?php
namespace Story\Model;

class PushDevice
{
    public static function insertOrUpdate($device_id, $platform, $device_token, $u_id = null)
    {
        global $app;

        $info = PushDevice::getByDeviceId($device_id);
        if ($info) {
            // 회원 정보, 푸시 정보 바인딩
            if ($u_id && $info['u_id'] != $u_id) {
                self::bindUid($device_id, $u_id);
            }

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
                if ($u_id && $info['u_id'] != $u_id) {
                    self::bindUid($device_id, $u_id);
                }

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
insert ignore into push_devices (u_id, device_id, platform, device_token, is_active) values (?, ?, ?, ?, 1)
EOT;
                $r = $app['db']->executeUpdate($sql, array($u_id, $device_id, $platform, $device_token));
                return $r === 1;
            }
        }
    }

    private static function bindUid($device_id, $u_id)
    {
        $sql = <<<EOT
update ignore push_devices set u_id = ? where device_id = ?
EOT;
        global $app;
        return $app['db']->executeUpdate($sql, array($u_id, $device_id));
    }

    public static function getByDeviceId($device_id)
    {
        $sql = <<<EOT
select * from push_devices where device_id = ?
EOT;
        global $app;
        $r = $app['db']->fetchAssoc($sql, array($device_id));
        return $r;
    }

    public static function getByDeviceToken($device_token)
    {
        $sql = <<<EOT
select * from push_devices where device_token = ?
EOT;
        global $app;
        $r = $app['db']->fetchAssoc($sql, array($device_token));
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

    public static function update($id, $values)
    {
        global $app;
        $r = $app['db']->update('push_devices', $values, array('id' => $id));
        return $r === 1;
    }
}
