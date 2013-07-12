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
		
		$api->get('/storyplusbook/list', array($this, 'storyPlusBookList'));
		$api->get('/storyplusbook/{b_id}', array($this, 'storyPlusBook'));
		
		$api->get('/user/{device_id}/interest/list', array($this, 'userInterestList'));
		$api->get('/user/{device_id}/interest/{b_id}/set', array($this, 'userInterestSet'));
		$api->get('/user/{device_id}/interest/{b_id}/clear', array($this, 'userInterestClear'));
		$api->get('/user/{device_id}/interest/{b_id}', array($this, 'userInterestGet'));
		
		$api->get('/user/{device_id}/part/{p_id}/like', array($this, 'userPartLike'));
		$api->get('/user/{device_id}/part/{p_id}/unlike', array($this, 'userPartUnlike'));

		$api->get('/user/{device_id}/storyplusbook/{b_id}/like', array($this, 'userStoryPlusBookLike'));
		$api->get('/user/{device_id}/storyplusbook/{b_id}/unlike', array($this, 'userStoryPlusBookUnlike'));

		$api->get('/storyplusbook/{b_id}/comment/list', array($this, 'storyPlusBookCommentList'));
		$api->get('/storyplusbook/{b_id}/comment/add', array($this, 'storyPlusBookCommentAdd'));
		
		$api->get('/push_device/register', array($this, 'pushDeviceRegister'));
		
		$api->get('/latest_version', array($this, 'latestVersion'));
		
		$api->get('/validate_part_download', array($this, 'validatePartDownload'));
		$api->get('/validate_storyplusbook_download', array($this, 'validateStoryPlusBookDownload'));
		
		$api->get('/shorten_url/{p_id}', array($this, 'shortenUrl'));
		
		return $api;
	}

	public function storyPlusBookCommentAdd(Request $req, Application $app, $b_id) {
		$device_id = $req->get('device_id');
		$comment = trim($req->get('comment'));
		$ip = ip2long($_SERVER['REMOTE_ADDR']);
		
		StoryPlusBookComment::add($b_id, $device_id, $comment, $ip);
		return $app->json(array('success' => 'true'));
	}

	public function storyPlusBookCommentList(Application $app, $b_id) {
		$comments = StoryPlusBookComment::getList($b_id);
		return $app->json($comments);
	}

	public function storyPlusBookList(Application $app) {
		$book = $app['cache']->fetch('storyplusbook_list', function() {
			return StoryPlusBook::getOpenedBookList();
		}, 60 * 10);
		return $app->json($book);
	}

	public function storyPlusBook(Request $req, Application $app, $b_id) {
		$book = StoryPlusBook::get($b_id);
		$intro = StoryPlusBookIntro::getListByBid($b_id);
		$comment = StoryPlusBookComment::getList($b_id);
		
		$info = array('book_detail' => $book,
					'intro' => $intro,
					'comment' => $comment);
		return $app->json($info);
	}

	public function bookList(Application $app) {
		$book = $app['cache']->fetch('book_list', function() {
			return Book::getOpenedBookList();
		}, 60 * 10);
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
		
		$parts = $app['cache']->fetch('part_list_' . $b_id, function() use ($b_id) {
			return Part::getListByBid($b_id, true);
		}, 60 * 10);

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
		
		$r = UserPartLike::like($device_id, $p_id);
		$like_count = UserPartLike::getLikeCount($p_id);
		return $app->json(array('success' => ($r === 1), 'like_count' => $like_count));
	}
	
	public function userPartUnlike(Application $app, $device_id, $p_id) {
		$r = UserPartLike::unlike($device_id, $p_id);
		$like_count = UserPartLike::getLikeCount($p_id);
		return $app->json(array('success' => ($r === 1), 'like_count' => $like_count));
	}
	
	public function userStoryPlusBookLike(Application $app, $device_id, $b_id) {
		$b = StoryPlusBook::get($b_id);
		if ($b == false) {
			return $app->json(array('success' => false));
		}
		
		$r = UserStoryPlusBookLike::like($device_id, $b_id);
		$like_count = UserStoryPlusBookLike::getLikeCount($b_id);
		return $app->json(array('success' => ($r === 1), 'like_count' => $like_count));
	}
	
	public function userStoryPlusBookUnlike(Application $app, $device_id, $b_id) {
		$r = UserStoryPlusBookLike::unlike($device_id, $b_id);
		$like_count = UserStoryPlusBookLike::getLikeCount($b_id);
		return $app->json(array('success' => ($r === 1), 'like_count' => $like_count));
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
				'version' => '0.9',
				'force' => false,
				'update_url' => 'http://play.google.com/store/apps/details?id=com.initialcoms.story',
				'description' => '리디스토리 최신 버전으로 업데이트 하시겠습니까?'
			);
			return $app->json($r);
		}
		
		return $app->json(array('error' => 'invalid platform'));
	}

	public function validatePartDownload(Request $req, Application $app) {
		$p_id = $req->get('p_id');
		$store_id = $req->get('store_id');
		
		$valid = Part::isOpenedPart($p_id, $store_id);

		// log
		$app['db']->insert('stat_download', array('p_id' => $p_id, 'is_success' => ($valid ? 1 : 0)));
		
		return $app->json(array('success' => $valid));
	} 

	public function validateStoryPlusBookDownload(Request $req, Application $app) {
		$storyplusbook_id = $req->get('storyplusbook_id');
		$store_id = $req->get('store_id');
		
		// TODO: 더 strict하게 구현
		$book = StoryPlusBook::get($storyplusbook_id);
		$valid = ($book['store_id'] == $store_id);

		// log
		$app['db']->insert('stat_download_storyplusbook', array('storyplusbook_id' => $storyplusbook_id, 'is_success' => ($valid ? 1 : 0)));
		
		return $app->json(array('success' => $valid));
	} 
	
	public function shortenUrl(Request $req, Application $app, $p_id) {
		$p = new Part($p_id);
		if ($p->isOpened()) {
			$preview_url = 'http://preview.ridibooks.com/' . $p->store_id . '?mobile';
			$shorten_url = $this->_getShortenUrl($preview_url);
			return $app->json(array('url' => $shorten_url));
		}
		
		return $app->json(array('error' => 'unable to get shorten url')); 
	}
	
	private function _getShortenUrl($target_url)
	{
		$url = "http://ridi.kr/yourls-api.php";
		$attachment =  array(
			'signature' => 'bbd2b597f6',
			'action' => 'shorturl',
			'format' => 'json',
			'url' => $target_url,
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $attachment);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  //to suppress the curl output
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		
		$result = curl_exec($ch);
		curl_close ($ch);
		
		$json_result = json_decode($result, true);
		
		return $json_result['shorturl'];
	}
}

