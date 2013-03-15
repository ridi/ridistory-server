<?
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

class AdminControllerProvider implements ControllerProviderInterface
{
	public function connect(Application $app) {
		$admin = $app['controllers_factory'];
		
		$admin->get('/', function() use ($app) {
			return $app->redirect('/admin/book/list');
		});

		$admin->get('/login', function() use ($app) {
			return $app['twig']->render('/admin/login.twig');
		});
		
		$admin->get('/book/list', array($this, 'bookList'));
		$admin->get('/book/add', array($this, 'bookAdd'));
		$admin->get('/book/{id}', array($this, 'bookDetail'));
		$admin->post('/book/{id}/delete', array($this, 'bookDelete'));
		$admin->post('/book/{id}/edit', array($this, 'bookEdit'));
		
		$admin->get('/part/add', array($this, 'partAdd'));
		$admin->get('/part/{id}', array($this, 'partDetail'));
		$admin->get('/part/{id}/delete', array($this, 'partDelete'));
		$admin->post('/part/{id}/edit', array($this, 'partEdit'));
		
		// authority check
		/*
		$admin->before(function (Request $request) use ($app) {
			return new RedirectResponse('/login');
		});
		 */
		
		$admin->before(function (Request $request) use ($app) {
			$alert = $app['session']->get('alert');
			if ($alert) {
				$app['twig']->addGlobal('alert', $alert);
				$app['session']->remove('alert');
			}
		});
		
		$admin->get('/comment/list', array($this, 'commentList'));
		$admin->get('/comment/{c_id}/delete', array($this, 'commentDelete'));
		
		$admin->get('/api_list', function() use ($app) {
			return $app['twig']->render('/admin/api_list.twig');
		});
		
		$admin->get('/test_push', function() {
			
			$message = 'test push';
			$push_type = 0;
			$url = 'http://naver.com';
			
			$notification = array(
				"message" => $message,
				"push_type" => $push_type,
				"url" => $url);
				
			$device_tokens = array('APA91bFROD2RVA6iEWFgQ6v8BWKhcqZMZertE58aND6OHAVQDXxexAcoaBRzZ1Ot31CJL7RppsJ0rhuhxVUhFHHczFAk_m3BJcTssC00oPQS86WkaCNwN5Rtcy-YySuu_iLEGd_7qGdQF0a20J2siGzHKKgpsUUWVQ');
				
			$r = AdminControllerProvider::sendPushNotificationForAndroid($device_tokens, $notification);
			return $r;
		});
		
		return $admin;
	}

	public function bookList(Request $req, Application $app) {
		$books = Book::getWholeList();
		foreach ($books as &$book) {
			$progress = 0;
			if ($book['total_part_count'] > 0) {
				$progress = 100 * $book['num_parts'] / $book['total_part_count'];
			}
			$book['progress'] = $progress . '%';
		}
		return $app['twig']->render('admin/book_list.twig', array('books' => $books));
	}
	
	public function bookDetail(Request $req, Application $app, $id) {
		$book = Book::get($id);
		$parts = Part::getListByBid($id);
		$intro = Book::getIntro($id);
		if ($intro === false) {
			$intro = array('b_id' => $id, 'description' => '', 'about_author' => '');
			Book::createIntro($intro);
		}
		
		return $app['twig']->render('admin/book_detail.twig', array(
			'book' => $book,
			'parts' => $parts,
			'intro' => $intro,
		));
	}
	
	public function bookAdd(Request $req, Application $app) {
		$b_id = Book::create();
		$app['session']->set('alert', array('success' => '책이 추가되었습니다.'));
		return $app->redirect('/admin/book/' . $b_id);
	}

	public function bookEdit(Request $req, Application $app, $id) {
		$inputs = $req->request->all();
		
		// 연재 요일
		$upload_days = 0;
		foreach ($inputs['upload_days'] as $k => $v) {
			$upload_days += intval($v);
		}
		$inputs['upload_days'] = $upload_days;
		
		// 상세 정보는 별도 테이블로
		$intro = array('b_id' => $id);
		array_move_keys($inputs, $intro, array(
			'intro_description' => 'description',
			'intro_about_author' => 'about_author'
		));
		
		Book::update($id, $inputs);
		Book::updateIntro($id, $intro);
		
		$app['session']->set('alert', array('info' => '책이 수정되었습니다.'));
		return $app->redirect('/admin/book/' . $id);
	}
	
	public function bookDelete(Request $req, Application $app, $id) {
		$parts = Part::getListByBid($id);
		if (count($parts)) {
			return $app->json(array('error' => 'Part가 있으면 책을 삭제할 수 없습니다.'));
		}
		Book::delete($id);
		$app['session']->set('alert', array('info' => '책이 삭제되었습니다.'));
		return $app->json(array('success' => true));
	}
	
	
	public function partDetail(Request $req, Application $app, $id) {
		$part = Part::get($id);
		return $app['twig']->render('admin/part_detail.twig', array('part' => $part));
	}
	
	public function partAdd(Request $req, Application $app) {
		$b_id = $req->get('b_id');
		$p_id = Part::create($b_id);
		$app['session']->set('alert', array('success' => '파트가 추가되었습니다.'));
		return $app->redirect('/admin/part/' . $p_id);
	}
	
	public function partEdit(Request $req, Application $app, $id) {
		$inputs = $req->request->all();
		$part = Part::get($id);
		Part::update($id, $inputs);
		$app['session']->set('alert', array('info' => '파트가 수정되었습니다.'));
		return $app->redirect('/admin/book/' . $part['b_id']);
	}
	
	public function partDelete(Request $req, Application $app, $id) {
		$part = Part::get($id);
		Part::delete($id);
		$app['session']->set('alert', array('info' => '파트가 삭제되었습니다.'));
		return $app->redirect('/admin/book/' . $part['b_id']);
	}


	public static function sendPushNotificationForAndroid($device_tokens, $notification) {
	    static $GOOGLE_API_KEY_FOR_GCM = "AIzaSyCMwJbi_uk4UI8WJB1spxld4TDVtFbhYpc";
		
	    $post_data = array('data' => $notification,
	                  'registration_ids' => $device_tokens);
	    
	    $headers = array('Content-Type:application/json',
	                     'Authorization:key=' . $GOOGLE_API_KEY_FOR_GCM);
	    
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_URL, 'https://android.googleapis.com/gcm/send');
	    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	    curl_setopt($ch, CURLOPT_POST, true);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
	    
		/* 테스트 진행 위해 구글에서 주는 결과값 로그에 찍음 */
	    $result = curl_exec($ch);
		
	    curl_close($ch);
		
		return $result;
	}
	
	public static function commentList(Request $req, Application $app) {
		$cur_page = $req->get('page', 0);
		
		$limit = 50;
		$offset = $cur_page * $limit;
		 
		$comments = $app['db']->fetchAll("select * from part_comment order by id desc limit {$offset}, {$limit}");
		$num_comments = $app['db']->fetchColumn('select count(*) from part_comment');
		return $app['twig']->render('/admin/comment_list.twig', array(
			'comments' => $comments,
			'num_comments' => $num_comments,
			'cur_page' => $cur_page,
			'num_pages' => $num_comments / $limit,
		));
	}
	
	public static function commentDelete(Request $req, Application $app, $c_id) {
		$r = PartComment::delete($c_id);
		$app['session']->set('alert', array('info' => '댓글이 삭제되었습니다.'));
		$redirect_url = $req->headers->get('referer', '/admin/comment/list');
		return $app->redirect($redirect_url); 
	}
}

function array_move_keys(&$src, &$dst, array $keys) {
	foreach ($keys as $k1 => $k2) {
		$dst[$k2] = $src[$k1];
		unset($src[$k1]);
	}
}


