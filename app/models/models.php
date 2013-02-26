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
		return $app['db']->fetchAll('select * from book');
	}
	
	public static function getOpenedBookList() {
		global $app;
		
		$today = date('Y-m-d');
		$sql = <<<EOT
select c.name, b.*, max(p.begin_date) last_update from book b
 join category c on c.id = c_id
 left join part p on b.id = p.b_id
where b.begin_date <= ? and b.end_date >= ? and p.begin_date <= ? and p.end_date >= ?
group by b.id
EOT;

		$ar = $app['db']->fetchAll($sql, array($today, $today, $today, $today));
		$list = array();
		foreach ($ar as $b) {
			$cat_name = $b['name'];
			unset($b['name']);
			
			$b['cover_url'] = 'http://misc.ridibooks.com/cover/' . $b['store_id'] . '/xlarge';
			
			$list[$cat_name][] = $b;
		}
		
		return $list; 
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
		self::_fill_additional($b);
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
?>