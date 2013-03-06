<?
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

$api = $app['controllers_factory'];

$api->get('/book/list', function () use ($app) {
	$book = Book::getOpenedBookList();
	return $app->json($book);
});

$api->get('/book/{id}', function ($id) use ($app) {
	$book = Book::get($id);
	$parts = Part::getByBid($id);

	$book["parts"] = $parts;
	
	return $app->json($book); 
});

$api->get('/book/{id}/parts', function ($id) use ($app) {
	$parts = Part::getByBid($id);
	return $app->json($parts);
});


class CommentController
{
	public function add(Request $req, Application $app) {
		$p_id = $req->get('p_id');
		$device_id = $req->get('device_id');
		$comment = $req->get('comment');
		
		// TODO: abuse detection

		$app['db']->insert('user_comment', compact('p_id', 'device_id', 'comment'));
		return $app->json(array('success' => true));
	}
}

class InterestController
{
	public function set(Request $req, Application $app, $device_id, $b_id) {
		$r = $app['db']->executeUpdate('insert ignore user_interest (device_id, b_id) values (?, ?)', array($device_id, $b_id));
		return $app->json(array('success' => ($r == 1)));
	}
	
	public function clear(Request $req, Application $app, $device_id, $b_id) {
		$r = $app['db']->delete('user_interest', compact('device_id', 'b_id'));
		return $app->json(array('success' => ($r == 1)));
	}
	
	public function list_(Request $req, Application $app, $device_id) {
		$r = $app['db']->fetchAll('select b_id from user_interest where device_id = ?', array($device_id));
		$b_ids = array();
		foreach ($r as $row) {
			$b_ids[] = $row['b_id'];
		}
		
		$list = Book::getListByIds($b_ids);
		return $app->json($list);
	}
}


$api->get('/comment/add', 'CommentController::add');

$api->get('/user/{device_id}/interest/{b_id}/set', 'InterestController::set');
$api->get('/user/{device_id}/interest/{b_id}/clear', 'InterestController::clear');
$api->get('/user/{device_id}/interest/list', 'InterestController::list_');

return $api;