<?php
namespace Story\Util;

class PickDeviceResult
{
    const PLATFORM_ALL = 'All';
    const PLATFORM_IOS = 'iOS';
    const PLATFORM_ANDROID = 'Android';

    private $devices;

    function __construct($devices)
    {
        $this->devices = $devices;
    }

    /*
     * 플랫폼에 따라 필터링
     */

    public function getAllDevices()
    {
        return $this->devices;
    }

    public function getIosDevices()
    {
        return $this->getDevicesForPlatform(self::PLATFORM_IOS);
    }

    public function getAndroidDevices()
    {
        return $this->getDevicesForPlatform(self::PLATFORM_ANDROID);
    }

    private function getDevicesForPlatform($platform)
    {
        $devices_for_platform = array();

        foreach ($this->devices as $device) {
            if ($device['platform'] == $platform) {
                array_push($devices_for_platform, $device);
            }
        }

        return $devices_for_platform;
    }
}
