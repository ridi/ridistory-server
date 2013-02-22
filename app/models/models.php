<?

class Book
{
	public static function get($id) {
		global $app;
		return $app['db']->fetchAssoc('select * from book where id = ?', array($id));
	}

	public static function getWholeList() {
		global $app;
		return $app['db']->fetchAll('select * from book');
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
}

class Part
{
	public static function get($id) {
		global $app;
		return $app['db']->fetchAssoc('select * from part where id = ?', array($id));
	}

	public static function getByBid($b_id) {
		global $app;
		return $app['db']->fetchAll('select * from part where b_id = ?', array($b_id));
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