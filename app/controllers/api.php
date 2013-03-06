<?
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;

class ApiControllerProvider implements ControllerProviderInterface
{
	public function connect(Application $app) {
		$api = $app['controllers_factory'];
		
		$api->get('/book/list', array($this, 'bookList'));
		$api->get('/book/{b_id}', array($this, 'book'));
		$api->get('/book/{b_id}/parts', array($this, 'bookParts'));
		
		$api->get('/user/{device_id}/interest/list', array($this, 'userInterestList'));
		$api->get('/user/{device_id}/interest/{b_id}/set', array($this, 'userInterestSet'));
		$api->get('/user/{device_id}/interest/{b_id}/clear', array($this, 'userInterestClear'));
		$api->get('/user/{device_id}/interest/{b_id}', array($this, 'userInterestGet'));
		
		$api->get('/user/{device_id}/part/{p_id}/like', array($this, 'userPartLike'));
		$api->get('/user/{device_id}/part/{p_id}/unlike', array($this, 'userPartUnlike'));
		
		$api->get('/user/{device_id}/comment/add', array($this, 'userCommentAdd'));
		
		return $api;
	}

	public function bookList(Application $app) {
		$book = Book::getOpenedBookList();
		return $app->json($book);
	}
	
	public function book(Request $req, Application $app, $b_id) {
		$book = Book::get($b_id);
		$parts = Part::getByBid($b_id);
		$book["parts"] = $parts;
		
		$device_id = $req->get('device_id');
		$book['interest'] = ($device_id === null) ? false : UserInterest::hasInterestInBook($device_id, $b_id);
		
		return $app->json($book); 
	}
	
	public function bookParts(Application $app, $b_id) {
		$parts = Part::getByBid($b_id);
		return $app->json($parts);
	}
	

	public function userInterestSet(Request $req, Application $app, $device_id, $b_id) {
		$r = $app['db']->executeUpdate('insert ignore user_interest (device_id, b_id) values (?, ?)', array($device_id, $b_id));
		return $app->json(array('success' => ($r === 1)));
	}
	
	public function userInterestClear(Request $req, Application $app, $device_id, $b_id) {
		$r = $app['db']->delete('user_interest', compact('device_id', 'b_id'));
		return $app->json(array('success' => ($r === 1)));
	}
	
	public function userInterestGet(Request $req, Application $app, $device_id, $b_id) {
		$r = $app['db']->fetchColumn('select id from user_interest where device_id = ? and b_id = ?', array($device_id, $b_id));
		return $app->json(array('success' => ($r !== false)));
	}

	public function userInterestList(Application $app, $device_id) {
		$r = $app['db']->fetchAll('select b_id from user_interest where device_id = ?', array($device_id));
		$b_ids = array();
		foreach ($r as $row) {
			$b_ids[] = $row['b_id'];
		}
		
		$list = Book::getListByIds($b_ids);
		return $app->json($list);
	}
	
	
	public function userPartLike(Request $req, Application $app, $device_id, $p_id) {
		$p = Part::get($p_id);
		if ($p == false) {
			return $app->json(array('success' => false));
		}
		$r = $app['db']->executeUpdate('insert ignore user_part_like (device_id, p_id) values (?, ?)', array($device_id, $p_id));
		return $app->json(array('success' => ($r === 1)));
	}
	
	public function userPartUnlike(Request $req, Application $app, $device_id, $p_id) {
		$r = $app['db']->delete('user_part_like', compact('device_id', 'p_id'));
		return $app->json(array('success' => ($r === 1)));
	}


	public function userCommentAdd(Request $req, Application $app) {
		$p_id = $req->get('p_id');
		$device_id = $req->get('device_id');
		$comment = $req->get('comment');
		
		// TODO: abuse detection

		$app['db']->insert('user_comment', compact('p_id', 'device_id', 'comment'));
		return $app->json(array('success' => true));
	}
}

