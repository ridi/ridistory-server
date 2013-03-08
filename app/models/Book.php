<?

class Book
{
	public static function get($id) {
		global $app;
		$b = $app['db']->fetchAssoc('select * from book where id = ?', array($id));
		$b['cover_url'] = Book::getCoverUrl($b['store_id']);
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
select c.name category, last_update, b.* from book b
 join category c on c.id = c_id
 left join (select b_id, max(begin_date) last_update from part group by b_id) p on b.id = p.b_id
where b.begin_date <= ? and end_date >= ?
EOT;
		$bind = array($today, $today);

		global $app;
		$ar = $app['db']->fetchAll($sql, $bind);
		$list = array();

		foreach ($ar as &$b) {
			$b['cover_url'] = Book::getCoverUrl($b['store_id']);
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
		
		$ar = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($ar as &$b) {
			$b['cover_url'] = Book::getCoverUrl($b['store_id']);
		}

		return $ar;
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

	private static function getCoverUrl($store_id) {
		return 'http://misc.ridibooks.com/cover/' . $store_id . '/xlarge';
	}
	
	
	public static function getIntro($b_id) {
		global $app;
		return $app['db']->fetchAssoc('select * from book_intro where b_id = ?', array($b_id));
	}
}
