<?

class Book
{
	public static function get($id) {
		global $app;
		$b = $app['db']->fetchAssoc('select * from book where id = ?', array($id));
		if ($b) {
			$b['cover_url'] = Book::getCoverUrl($b['store_id']);
		}
		return $b;
	}

	public static function getWholeList() {
		$today = date('Y-m-d');
		$sql = <<<EOT
select count(part.b_id) uploaded_part_count, ifnull(open_part_count, 0) open_part_count, b.* from book b
 left join (select b_id, count(*) open_part_count from part where begin_date <= ? and end_date >= ? group by b_id) pc on b.id = pc.b_id
 left join part on b.id = part.b_id group by b.id, part.b_id
EOT;
		$bind = array($today, $today);
		global $app;
		return $app['db']->fetchAll($sql, $bind);
	}
	
	public static function getOpenedBookList() {
		$today = date('Y-m-d');
		$sql = <<<EOT
select ifnull(last_update, 0) last_update, ifnull(open_part_count, 0) open_part_count, like_sum, b.* from book b
 left join (select b_id, count(*) open_part_count from part where begin_date <= ? and end_date >= ? group by b_id) pc on b.id = pc.b_id
 join (select b_id, count(*) like_sum from user_part_like, part where p_id = part.id group by b_id) ls on b.id = ls.b_id
 left join (select b_id, 1 last_update from part where begin_date = date(now()) group by b_id) p on b.id = p.b_id
where b.begin_date <= ? and end_date >= ?
EOT;
		$bind = array($today, $today, $today, $today);

		global $app;
		$ar = $app['db']->fetchAll($sql, $bind);
		$list = array();

		foreach ($ar as &$b) {
			$b['cover_url'] = Book::getCoverUrl($b['store_id']);
		}

		return $ar;
	}
	
	public static function getListByIds(array $b_ids, $with_part_info = false) {
		if (count($b_ids) === 0) {
			return array();
		}
		
		global $app;
		
		if ($with_part_info) {
			$sql = <<<EOT
select i.popularity, ifnull(last_update, 0) last_update, open_part_count, b.* from book b
 join (select b_id, count(*) open_part_count from part where begin_date <= ? and end_date >= ? group by b_id) pc on b.id = pc.b_id
 left join (select b_id, count(*) popularity from user_interest group by b_id) i on b.id = i.b_id
 left join (select b_id, 1 last_update from part where begin_date = date(now()) group by b_id) p on b.id = p.b_id
where b.id in (?)
EOT;
			$today = date('Y-m-d');
			$stmt = $app['db']->executeQuery($sql,
				array($today, $today, $b_ids),
				array(\PDO::PARAM_STR, \PDO::PARAM_STR, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY)
			);
		} else {
			$sql = <<<EOT
select p.popularity, b.* from book b
 left join (select b_id, count(*) popularity from user_interest group by b_id) p on b.id = b_id
where b.id in (?)
EOT;
			$stmt = $app['db']->executeQuery($sql,
				array($b_ids),
				array(\Doctrine\DBAL\Connection::PARAM_INT_ARRAY)
			);
		}
		
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

	public static function getCoverUrl($store_id) {
		return 'http://misc.ridibooks.com/cover/' . $store_id . '/xxlarge';
	}
	
	
	public static function createIntro($values) {
		global $app;
		return $app['db']->insert('book_intro', $values);
	}
	
	public static function getIntro($b_id) {
		global $app;
		return $app['db']->fetchAssoc('select * from book_intro where b_id = ?', array($b_id));
	}
	
	public static function updateIntro($b_id, $values) {
		global $app;
		return $app['db']->update('book_intro', $values, array('b_id' => $b_id));
	}
}

class BookList
{
	public static function getRecommendedBooks() {
		global $app;
		$r = $app['db']->fetchAll('select b_id from recommended_books order by reg_date desc limit 3');
		$b_ids = array();
		foreach ($r as $row) {
			$b_ids[] = $row['b_id'];
		}
		
		$books = Book::getListByIds($b_ids);
		foreach ($books as &$b) {
			$b['href'] = 'storyholic://native/book/' . $b['id'] . '/detail';
		}
		return $books;
	}
	
	public static function getDesignatedBooks() {
		global $app;
		$r = $app['db']->fetchAll('select id from book where begin_date > now() order by begin_date limit 2');
		$b_ids = array();
		foreach ($r as $row) {
			$b_ids[] = $row['id'];
		}
		
		$books = Book::getListByIds($b_ids);
		foreach ($books as &$b) {
			$b['href'] = 'storyholic://native/book/' . $b['id'] . '/detail';
		}
		
		return $books;
	}
	
	public static function getTodayBest() {
		global $app;
		$part_ids = array(24, 46, 57, 58, 19, 48);
		$stmt = $app['db']->executeQuery('select * from part where id in (?)',
			array($part_ids),
			array(\Doctrine\DBAL\Connection::PARAM_INT_ARRAY)
		);
		
		$ar = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($ar as &$p) {
			$p['cover_url'] = Book::getCoverUrl($p['store_id']);
			$p['href'] = 'storyholic://native/book/' . $p['b_id'] . '/detail';
		}
		
		return $ar;
	}
}

