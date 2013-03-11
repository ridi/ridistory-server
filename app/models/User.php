<?

class UserInterest
{
	public static function set($device_id, $b_id) {
		global $app;
		$r = $app['db']->executeUpdate('insert ignore user_interest (device_id, b_id) values (?, ?)', array($device_id, $b_id));
		return $r === 1;
	}
	
	public static function clear($device_id, $b_id) {
		global $app;
		$r = $app['db']->delete('user_interest', compact('device_id', 'b_id'));
		return $r === 1;
	}
	
	public static function get($device_id, $b_id) {
		global $app;
		$r = $app['db']->fetchColumn('select id from user_interest where device_id = ? and b_id = ?', array($device_id, $b_id));
		return ($r !== false);
	}
	
	public static function hasInterestInBook($device_id, $b_id) {
		global $app;
		$r = $app['db']->fetchAssoc('select * from user_interest where device_id = ? and b_id = ?', array($device_id, $b_id));
		return ($r !== false);
	}
}


class PushDevice
{
	public static function insertOrUpdate($device_id, $platform, $device_token) {
		global $app;
		$info = PushDevice::get($device_id);
		if ($info) {
			// already exists ?
			if ($info['platform'] == $platform && $info['device_token'] == $device_token && $info['is_active']) {
				return true;
			}
		
			$data = array('platform' => $platform, 'device_token' => $device_token, 'is_active' => 1);
			$where = array('device_id' => $device_id);
			$r = $app['db']->update('push_devices', $data, $where);
			return $r === 1;
		}
		
		$data = array('device_id' => $device_id, 'platform' => $platform, 'device_token' => $device_token, 'is_active' => 1);
		$r = $app['db']->insert('push_devices', $data);
		return $r === 1;
	}
	
	public static function get($device_id) {
		global $app;
		$r = $app['db']->fetchAssoc('select * from push_devices where device_id = ?', array($device_id));
		return $r;
	}
	
	public static function delete($device_id) {
		global $app;
		$r = $app['db']->delete('push_devices', compact('device_id'));
		return $r === 1;
	}
}


?>