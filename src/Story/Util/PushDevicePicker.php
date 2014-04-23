<?php
namespace Story\Util;

use Doctrine\DBAL\Connection;

class PushDevicePicker
{
    /*
     * 기기 정보들 선택 (id, device_token, platform)
     */
    static function pickDevicesUsingInterestBook(Connection $db, $b_id)
    {
        $sql = <<<EOT
select p.id, p.device_token, p.platform from user_interest u
 join push_devices p on u.device_id = p.device_id
where b_id = ? and p.is_active = 1 and u.cancel = 0
EOT;

        $params = array($b_id);
        $devices = $db->fetchAll($sql, $params);

        return new PickDeviceResult($devices);
    }

    static function pickDevicesUsingPlatform(Connection $db, $platform)
    {
        if ($platform == PickDeviceResult::PLATFORM_ALL) {
            $sql = <<<EOT
select id, device_token, platform from push_devices where is_active = 1;
EOT;
            $devices = $db->fetchAll($sql);
        } else {
            $sql = <<<EOT
select id, device_token, platform from push_devices where platform = ? and is_active = 1;
EOT;
            $devices = $db->fetchAll($sql, array($platform));
        }

        return new PickDeviceResult($devices);
    }

    static function pickDevicesUsingIdRange(Connection $db, $range_begin, $range_end)
    {
        $sql = <<<EOT
select id, device_token, platform from push_devices where id >= ? and id <= ? and is_active = 1
EOT;

        $params = array($range_begin, $range_end);
        $devices = $db->fetchAll($sql, $params);

        return new PickDeviceResult($devices);
    }

    static function pickDevicesUsingRegDateRange(Connection $db, $date_begin, $date_end)
    {
        $sql = <<<EOT
select id, device_token, platform from push_devices where reg_date >= ? and reg_date <= ? and is_active = 1
EOT;

        $params = array($date_begin, $date_end);
        $devices = $db->fetchAll($sql, $params);

        return new PickDeviceResult($devices);
    }
}
