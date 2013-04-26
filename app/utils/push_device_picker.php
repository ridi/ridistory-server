<?php

class PushDevicePicker {
	/*
	 * 기기 정보들 선택 (id, device_token, platform)
	 */
	static function pickDevicesUsingInterestBook($db, $b_id) {
		$sql = <<<EOT
select p.id, p.device_token, p.platform from user_interest u
 join push_devices p on u.device_id = p.device_id
where b_id = ? and p.is_active = 1
EOT;
		
		$params = array($b_id);
		$devices = $db->fetchAll($sql, $params);
		
		return new PickDeviceResult($devices);
	}
	
	static function pickDevicesUsingIdRange($db, $range_begin, $range_end) {
		$sql = <<<EOT
select id, device_token, platform from push_devices where id >= ? and id <= ?
EOT;

		$params = array($range_begin, $range_end);
		$devices = $db->fetchAll($sql, $params);
		
		return new PickDeviceResult($devices);
	}
	
	static function pickDevicesUsingRegDateRange($db, $date_begin, $date_end) {
		$sql = <<<EOT
select id, device_token, platform from push_devices where reg_date >= ? and reg_date <= ?
EOT;

		$params = array($date_begin, $date_end);
		$devices = $db->fetchAll($sql, $params);
		
		return new PickDeviceResult($devices);
	}
}

class PickDeviceResult {
	const PLATFORM_IOS = 'iOS';
	const PLATFORM_ANDROID = 'Android';

	private $devices;
	
	function __construct($devices) {
		$this->devices = $devices;
	}
	
	/*
	 * 플랫폼에 따라 필터링
	 */
	
	public function getAllDevices() {
		return $this->devices;
	}
	
	public function getIosDevices() {
		return $this->getDevicesForPlatform(self::PLATFORM_IOS);
	}
	
	public function getAndroidDevices() {
		return $this->getDevicesForPlatform(self::PLATFORM_ANDROID);
	}
	
	private function getDevicesForPlatform($platform) {
		$devices_for_platform = array();
		
		foreach ($this->devices as $device) {
			if ($device['platform'] == $platform) {
				array_push($devices_for_platform, $device);
			}
		}
		
		return $devices_for_platform;
	}
}


?>