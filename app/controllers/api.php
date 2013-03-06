<?
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

$api = $app['controllers_factory'];

$api->get('/book/list', function () use ($app) {
	$book = Book::getOpenedBookList();
	return $app->json($book);
});

$api->get('/book/{b_id}', function (Request $req, $b_id) use ($app) {
	$book = Book::get($b_id);
	$parts = Part::getByBid($b_id);
	$book["parts"] = $parts;
	
	$device_id = $req->get('device_id');
	$book['interest'] = ($device_id === null) ? false : UserInterest::hasInterestInBook($device_id, $b_id);
	
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
		return $app->json(array('success' => ($r === 1)));
	}
	
	public function clear(Request $req, Application $app, $device_id, $b_id) {
		$r = $app['db']->delete('user_interest', compact('device_id', 'b_id'));
		return $app->json(array('success' => ($r === 1)));
	}
	
	public function get(Request $req, Application $app, $device_id, $b_id) {
		$r = $app['db']->fetchColumn('select id from user_interest where device_id = ? and b_id = ?', array($device_id, $b_id));
		return $app->json(array('success' => ($r !== false)));
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

class PartLikeController
{
	public function like(Request $req, Application $app, $device_id, $p_id) {
		$p = Part::get($p_id);
		if ($p == false) {
			return $app->json(array('success' => false));
		}
		$r = $app['db']->executeUpdate('insert ignore user_part_like (device_id, p_id) values (?, ?)', array($device_id, $p_id));
		return $app->json(array('success' => ($r === 1)));
	}
	
	public function unlike(Request $req, Application $app, $device_id, $p_id) {
		$r = $app['db']->delete('user_part_like', compact('device_id', 'p_id'));
		return $app->json(array('success' => ($r === 1)));
	}
}


$api->get('/comment/add', 'CommentController::add');

$api->get('/user/{device_id}/interest/{b_id}/set', 'InterestController::set');
$api->get('/user/{device_id}/interest/{b_id}/clear', 'InterestController::clear');
$api->get('/user/{device_id}/interest/{b_id}', 'InterestController::get');
$api->get('/user/{device_id}/interest/list', 'InterestController::list_');

$api->get('/user/{device_id}/part/{p_id}/like', 'PartLikeController::like');
$api->get('/user/{device_id}/part/{p_id}/unlike', 'PartLikeController::unlike');

return $api;