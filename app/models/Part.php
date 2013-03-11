<?

class Part
{
	public static function get($id) {
		global $app;
		$b = $app['db']->fetchAssoc('select * from part where id = ?', array($id));
		if ($b !== false) {
			self::_fill_additional($b);
		}
		return $b;
	}

	public static function getListByBid($b_id, $with_social_info = false) {
		global $app;
		
		if ($with_social_info) {
			$today = date('Y-m-d');
			$sql = <<<EOT
select p.*, ifnull(like_count, 0) like_count, ifnull(comment_count, 0) comment_count from part p
 left join (select p_id, count(*) like_count from user_part_like group by p_id) l on p.id = l.p_id
 left join (select p_id, count(*) comment_count from part_comment group by p_id) c on p.id = c.p_id
where b_id = ? and begin_date <= ? and end_date >= ?
order by seq
EOT;
			$bind = array($b_id, $today, $today);
		} else {
			$sql = 'select * from part where b_id = ? order by seq';
			$bind = array($b_id);
		}
		
		$ar = $app['db']->fetchAll($sql, $bind);
		foreach ($ar as &$b) {
			self::_fill_additional($b);
		}

		return $ar; 
	}

	private static function _fill_additional(&$b) {
		define('STORE_API_BASE_URL', 'http://hw.dev.ridibooks.kr');
		
		$b['meta_url'] = STORE_API_BASE_URL . '/api/book/metadata.php?id=' . $b['store_id'];
		
		$query = '?token=' . $b['store_id'];
		$b['download_url'] = STORE_API_BASE_URL . '/api/story/download_part.php' . $query;
	}
	
	public static function create($b_id) {
		global $app;
		$app['db']->insert('part', array('b_id' => $b_id));
		return $app['db']->lastInsertId();
	}
	
	public static function update($id, $values) {
		global $app;
		return $app['db']->update('part', $values, array('id' => $id));
	}

	public static function delete($id) {
		global $app;
		return $app['db']->delete('part', array('id' => $id));
	}
}

class PartComment
{
	public static function add($p_id, $device_id, $nickname, $comment) {
		global $app;
		$r = $app['db']->insert('part_comment', compact('p_id', 'device_id', 'nickname', 'comment'));
		return $r;
	}
	
	public static function getList($p_id) {
		global $app;
		$r = $app['db']->fetchAll('select * from part_comment where p_id = ? order by timestamp desc limit 100', array($p_id));
		return $r;
	}
	
	public static function getCommentCount($p_id) {
		global $app;
		$r = $app['db']->fetchColumn('select count(*) from part_comment where p_id = ?', array($p_id));
		return $r;
	}
}
