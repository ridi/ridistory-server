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
		
		$api->get('/user/{device_id}/interest/list', array($this, 'userInterestList'));
		$api->get('/user/{device_id}/interest/{b_id}/set', array($this, 'userInterestSet'));
		$api->get('/user/{device_id}/interest/{b_id}/clear', array($this, 'userInterestClear'));
		$api->get('/user/{device_id}/interest/{b_id}', array($this, 'userInterestGet'));
		
		$api->get('/user/{device_id}/part/{p_id}/like', array($this, 'userPartLike'));
		$api->get('/user/{device_id}/part/{p_id}/unlike', array($this, 'userPartUnlike'));
		
		$api->get('/push_device/register', array($this, 'pushDeviceRegister'));
		
		$api->get('/latest_version', array($this, 'latestVersion'));
		
		return $api;
	}

	public function bookList(Application $app) {
		$book = Book::getOpenedBookList();
		return $app->json($book);
	}
	
	/**
	 * 상세페이지에서 보여질 데이터를 JSON 형태로 전송
	 *  - 책 정보
	 *  - 파트 정보 리스트 (각 파트별 좋아요, 댓글 갯수 포함)
	 *  - 관심책 지정 여부
	 */
	public function book(Request $req, Application $app, $b_id) {
		$book = Book::get($b_id);
		if ($book == false) {
			return $app->json(array('success' => false, 'error' => 'no such book'));
		}

		$parts = Part::getListByBid($b_id, true);
		foreach ($parts as &$part) {
			$part["last_update"] = ($part["begin_date"] == date("Y-m-d")) ? 1 : 0;
		}

		$book["parts"] = $parts;
		
		$device_id = $req->get('device_id');
		$book['interest'] = ($device_id === null) ? false : UserInterest::hasInterestInBook($device_id, $b_id);
		
		return $app->json($book); 
	}
	

	public function userInterestSet(Application $app, $device_id, $b_id) {
		$r = UserInterest::set($device_id, $b_id);
		return $app->json(array('success' => $r));
	}
	
	public function userInterestClear(Application $app, $device_id, $b_id) {
		$r = UserInterest::clear($device_id, $b_id);
		return $app->json(array('success' => $r));
	}
	
	public function userInterestGet(Application $app, $device_id, $b_id) {
		$r = UserInterest::get($device_id, $b_id);
		return $app->json(array('success' => $r));
	}

	public function userInterestList(Application $app, $device_id) {
		$b_ids = UserInterest::getList($device_id);
		
		$list = Book::getListByIds($b_ids, true);
		return $app->json($list);
	}
	
	
	public function userPartLike(Application $app, $device_id, $p_id) {
		$p = Part::get($p_id);
		if ($p == false) {
			return $app->json(array('success' => false));
		}
		$r = $app['db']->executeUpdate('insert ignore user_part_like (device_id, p_id) values (?, ?)', array($device_id, $p_id));
		return $app->json(array('success' => ($r === 1)));
	}
	
	public function userPartUnlike(Application $app, $device_id, $p_id) {
		$r = $app['db']->delete('user_part_like', compact('device_id', 'p_id'));
		return $app->json(array('success' => ($r === 1)));
	}
	
	
	public function pushDeviceRegister(Application $app, Request $req) {
		$device_id = $req->get('device_id');
		$platform = $req->get('platform');
		$device_token = $req->get('device_token');
		
		if (strlen($device_id) == 0 || strlen($device_token) == 0 ||
			(strcmp($platform, 'iOS') != 0 && strcmp($platform, 'Android') != 0)) {
			return $app->json(array('success' => false, 'reason' => 'invalid parameters'));
		}
			
		if (PushDevice::insertOrUpdate($device_id, $platform, $device_token)) {
			return $app->json(array('success' => true));
		} else {
			return $app->json(array('success' => false, 'reason' => 'Insert or Update error'));
		}
	}

	public function latestVersion(Request $req, Application $app) {
		$platform = $req->get('platform');
		if (strcasecmp($platform, 'android') === 0) {
			$r = array(
				'version' => '1.0',
				'force' => false,
				'update_url' => 'http://play.google.com/store/apps/details?id=com.initialcoms.story',
				'description' => '스토리홀릭 최신 버전으로 업데이트 하시겠습니까?'
			);
			return $app->json($r);
		}
		
		return $app->json(array('error' => 'invalid platform'));
	}
}

