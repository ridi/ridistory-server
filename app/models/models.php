<?

class Book
{
	public static function get($id) {
		global $app;
		$b = $app['db']->fetchAssoc('select * from book where id = ?', array($id));
		$b['cover_url'] = 'http://misc.ridibooks.com/cover/' . $b['store_id'] . '/xlarge';
		return $b;
	}

	public static function getWholeList() {
		global $app;
		return $app['db']->fetchAll('select book.*, count(part.b_id) num_parts from book left join part on book.id = part.b_id group by id, part.b_id');
	}
	
	public static function getOpenedBookList() {
		// 카테고리별
		$today = date('Y-m-d');
		$sql = <<<EOT
select c.name category, p.popularity, b.* from book b
 join category c on c.id = c_id
 left join (select b_id, count(*) popularity from user_interest group by b_id) p on b.id = b_id
where b.begin_date <= ? and end_date >= ?
EOT;
		$bind = array($today, $today);

		global $app;
		$ar = $app['db']->fetchAll($sql, $bind);
		$list = array();

		foreach ($ar as &$b) {
			$b['cover_url'] = 'http://misc.ridibooks.com/cover/' . $b['store_id'] . '/xlarge';
		}

		return $ar;
	}
	
	public static function getListByIds(array $b_ids) {
		if (count($b_ids) === 0) {
			return array();
		}
		
		$sql = <<<EOT
select c.name category, p.popularity, b.* from book b
 join category c on c.id = c_id
 left join (select b_id, count(*) popularity from user_interest group by b_id) p on b.id = b_id
where b.id in (?)
EOT;
		global $app;
		$stmt = $app['db']->executeQuery($sql,
			array($b_ids),
			array(\Doctrine\DBAL\Connection::PARAM_INT_ARRAY)
		);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public static function create() {
		global $app;
		$app['db']->insert('book', array());
		return $app['db']->lastInsertId();
	}

	public static function update($id, $values) {
		global $app;
		return $app['db']->update('book', $values, array('id' => $id));
	}
	
	public static function delete($id) {
		global $app;
		return $app['db']->delete('book', array('id' => $id));
	}
}

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

	public static function getByBid($b_id) {
		global $app;
		
		$ar = $app['db']->fetchAll('select * from part where b_id = ?', array($b_id));
		foreach ($ar as &$b) {
			self::_fill_additional($b);
		}

		return $ar; 
	}

	private static function _fill_additional(&$b) {
		$b['meta_url'] = 'http://ridi.com/api/book/metadata.php?id=' . $b['store_id'];
		 
		$query = '?token=' . $b['store_id'];
		$b['download_url'] = 'http://ridi.com/api/story/download_part.php' . $query;
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

class UserInterest
{
	public static function hasInterestInBook($device_id, $b_id) {
		global $app;
		$r = $app['db']->fetchAssoc('select * from user_interest where device_id = ? and b_id = ?', array($device_id, $b_id));
		return ($r !== false);
	}
}

?>