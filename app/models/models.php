<?

class Book
{
	public static function get($id) {
		global $app;
		return $app['db']->fetchAssoc('select * from book where id = ?', array($id));
	}

	public static function getWholeList() {
		return $app['db']->fetchAll('select * from book');
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

}
?>