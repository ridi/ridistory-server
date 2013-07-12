<?

class StoryPlusBook
{
	public static function get($id) {
		global $app;
		$sql = <<<EOT
select storyplusbook.*, ifnull(like_sum, 0) like_sum from storyplusbook
	left join (select b_id, count(*) like_sum from user_storyplusbook_like group by b_id) L on storyplusbook.id = L.b_id
where id = ?
EOT;
		$b = $app['db']->fetchAssoc($sql, array($id));
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
				$sql = <<<EOT
select storyplusbook.*, ifnull(like_sum, 0) like_sum from storyplusbook
	left join (select b_id, count(*) like_sum from storyplusbook, user_storyplusbook_like where storyplusbook.id = user_storyplusbook_like.b_id group by b_id) L on storyplusbook.id = L.b_id
where begin_date <= ? and end_date >= ?
EOT;
		
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


class StoryPlusBookComment
{
	public static function add($b_id, $device_id, $comment, $ip) {
		global $app;
		$r = $app['db']->insert('storyplusbook_comment', compact('b_id', 'device_id', 'comment', 'ip'));
		return $r;
	}
	
	public static function delete($c_id) {
		global $app;
		$r = $app['db']->delete('storyplusbook_comment', array('id' => $c_id));
		return $r === 1;
	}
	
	public static function getList($b_id) {
		global $app;
		$r = $app['db']->fetchAll('select comment, `timestamp` from storyplusbook_comment where b_id = ? order by timestamp desc', array($b_id));
		return $r;
	}
	
	public static function getCommentCount($b_id) {
		global $app;
		$r = $app['db']->fetchColumn('select count(*) from storyplusbook_comment where b_id = ?', array($b_id));
		return $r;
	}
}
