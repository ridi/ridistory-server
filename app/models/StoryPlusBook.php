<?

class StoryPlusBook
{
	public static function get($id) {
		global $app;
		$b = $app['db']->fetchAssoc('select * from storyplusbook where id = ?', array($id));
		if ($b) {
			$b['cover_url'] = self::getCoverUrl($b['store_id']);
			self::_fill_additional($b);
		}
		return $b;
	}

	public static function getWholeList() {
		$today = date('Y-m-d H:00:00');
		$sql = "select * from storyplusbook";

		global $app;
		return $app['db']->fetchAll($sql);
	}
	
	public static function getOpenedBookList() {
		$today = date('Y-m-d H:00:00');
		$sql = "select * from storyplusbook where begin_date <= ? and end_date >= ?";
		$bind = array($today, $today);
		
		global $app;
		$ar = $app['db']->fetchAll($sql, $bind);
		$list = array();

		foreach ($ar as &$b) {
			$b['cover_url'] = self::getCoverUrl($b['store_id']);
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
	
	private static function _fill_additional(&$b) {
		$b['meta_url'] = 'http://ridibooks.com/api/book/metadata.php?id=' . $b['store_id'];

		$query = http_build_query(array('store_id' => $b['store_id'], 'storyplusbook_id' => $b['id']));
		$b['download_url'] = STORE_API_BASE_URL . '/api/story/download_storyplusbook.php?' . $query;
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
