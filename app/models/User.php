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

?>