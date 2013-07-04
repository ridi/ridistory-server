<?

class StoryPlusBook
{
	public static function get($id) {
		global $app;
		$b = $app['db']->fetchAssoc('select * from storyplusbook where id = ?', array($id));
		if ($b) {
			$b['cover_url'] = StoryPlusBook::getCoverUrl($b['store_id']);
		}
		return $b;
	}

	public static function getWholeList() {
		$today = date('Y-m-d H:i:s');
		$sql = "select * from storyplusbook";

		global $app;
		return $app['db']->fetchAll($sql);
	}
	
	public static function getOpenedBookList() {
		$today = date('Y-m-d H:i:s');
		$sql = "select * from storyplusbook where begin_date <= ? and end_date >= ?";
		$bind = array($today, $today);

		global $app;
		$ar = $app['db']->fetchAll($sql, $bind);
		$list = array();

		foreach ($ar as &$b) {
			$b['cover_url'] = Book::getCoverUrl($b['store_id']);
			// TODO: iOS 앱 업데이트 후 아래 코드 제거할 것
			// iOS에서 시간 영역을 파싱하지 못하는 문제가 있어 하위호환을 위해 기존처럼 날짜만 내려줌.
			$b['begin_date'] = substr($b['begin_date'], 0, 10);
			$b['end_date'] = substr($b['end_date'], 0, 10);
		}

		return $ar;
	}
	
	public static function create() {
		global $app;
		$app['db']->insert('storyplusbook', array());
		return $app['db']->lastInsertId();
	}

	public static function update($id, $values) {
		global $app;
		return $app['db']->update('storyplusbook', $values, array('id' => $id));
	}
	
	public static function delete($id) {
		global $app;
		return $app['db']->delete('storyplusbook', array('id' => $id));
	}

	public static function getCoverUrl($store_id) {
		return 'http://misc.ridibooks.com/cover/' . $store_id . '/xxlarge';
	}
}

class StoryPlusBookIntro
{
	public static function create($b_id) {
		global $app;
		$app['db']->insert('storyplusbook_intro', array('b_id' => $b_id));
		return $app['db']->lastInsertId();
	}
	
	public static function get($id) {
		global $app;
		return $app['db']->fetchAssoc('select * from storyplusbook_intro where id = ?', array($id));
	}
	
	public static function delete($id) {
		global $app;
		return $app['db']->delete('storyplusbook_intro', array('id' => $id));
	}
	
	public static function update($id, $values) {
		global $app;
		return $app['db']->update('storyplusbook_intro', $values, array('id' => $id));
	}
	
	public static function getListByBid($b_id) {
		global $app;
		
		$sql = 'select * from storyplusbook_intro where b_id = ?';
		$bind = array($b_id);
		
		$ar = $app['db']->fetchAll($sql, $bind);
		return $ar; 
	}
}
