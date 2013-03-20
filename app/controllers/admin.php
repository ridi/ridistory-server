<?
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminControllerProvider implements ControllerProviderInterface
{
	public function connect(Application $app) {
		$admin = $app['controllers_factory'];
		
		$admin->get('/', function() use ($app) {
			return $app->redirect('/admin/book/list');
		});

		$admin->get('/logout', function() use ($app) {
			// HTTP basic authentication logout 에는 이거밖에 없다..
			session_destroy();
			
			$response = new Response();
			$response->setStatusCode(401, 'Unauthorized.');
			return $response;
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
		
		$admin->get('/push/dashboard', array($this, 'pushDashboard'));
		$admin->get('/push/notify_update', array($this, 'pushNotifyUpdate'));
		$admin->get('/push/target_count.ajax', function(Request $req) use ($app) {
			$b_id = $req->get('b_id');
			$sql = <<<EOT
select platform, count(*) count from user_interest i
 join push_devices p on p.device_id = i.device_id
where b_id = ?
group by platform
EOT;
			$r = $app['db']->fetchAssoc($sql, array($b_id));
			return $app->json($r);
		});
		
		$admin->get('/notice/list', array($this, 'noticeList'));
		$admin->get('/notice/add', array($this, 'noticeAdd'));
		$admin->get('/notice/{n_id}', array($this, 'noticeDetail'));
		$admin->post('/notice/{n_id}/edit', array($this, 'noticeEdit'));
		$admin->post('/notice/{n_id}/delete', array($this, 'noticeDelete'));
		
		return $admin;
	}

	public function bookList(Request $req, Application $app) {
		$books = Book::getWholeList();
		foreach ($books as &$book) {
			$progress = 0;
			$progress2 = 0;
			if ($book['total_part_count'] > 0) {
				$progress = 100 * $book['open_part_count'] / $book['total_part_count'];
				$progress2 = 100 * ($book['uploaded_part_count'] - $book['open_part_count']) / $book['total_part_count'];
			}
			$book['progress'] = $progress . '%';
			$book['progress2'] = $progress2 . '%';
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
	
	
	public static function pushDashboard(Request $req, Application $app) {
		return $app['twig']->render('/admin/dashboard.twig');
	}
	
	public static function pushNotifyUpdate(Request $req, Application $app) {
		$b_id = $req->get('b_id');
		$title = $req->get('title');
		$message = $req->get('message');
		
		if (empty($b_id)) {
			return 'no b_id';
		}
		
		$sql = <<<EOT
select device_token from user_interest u
 join push_devices p on u.device_id = p.device_id
where b_id = ? and p.is_active = 1
EOT;
		$params = array($b_id);
		
		$device_tokens = $app['db']->executeQuery($sql, $params)->fetchAll(PDO::FETCH_COLUMN, 0);
		$notification = AndroidNotification::createPartUpdateNotification($b_id, $title, $message);
		//$notification = AndroidNotification::createUrlNotofication('http://naver.com', $title, $message);

		$r = sendPushNotificationForAndroid($device_tokens, $notification);
		return $r;
	}
	
	
	public static function noticeList(Request $req, Application $app) {
		$notice_list = $app['db']->fetchAll('select * from notice');
		return $app['twig']->render('/admin/notice_list.twig', array('notice_list' => $notice_list));
	}
	
	public static function noticeDetail(Request $req, Application $app, $n_id) {
		$notice = $app['db']->fetchAssoc('select * from notice where id = ?', array($n_id));
		return $app['twig']->render('/admin/notice_detail.twig', array('notice' => $notice));
	}
	
	public static function noticeEdit(Request $req, Application $app, $n_id) {
		$inputs = $req->request->all();

		$r = $app['db']->update('notice', $inputs, array('id' => $n_id));
		
		$app['session']->set('alert', array('info' => '공지사항이 수정되었습니다.'));
		$redirect_url = $req->headers->get('referer', '/admin/notice/list');
		return $app->redirect($redirect_url); 
	}
	
	public static function noticeAdd(Application $app) {
		$app['db']->insert('notice', array('title' => '제목이 없습니다.', 'is_visible' => 0));
		$r = $app['db']->lastInsertId();
		return $app->redirect('/admin/notice/' . $r);
	}
	
	public static function noticeDelete(Application $app, $n_id) {
		$r = $app['db']->delete('notice', array('id' => $n_id));
		$app['session']->set('alert', array('warning' => '공지사항이 삭제되었습니다.'));
		$redirect_url = $req->headers->get('referer', '/admin/notice/list');
		return $app->redirect($redirect_url);
	}
}

function array_move_keys(&$src, &$dst, array $keys) {
	foreach ($keys as $k1 => $k2) {
		$dst[$k2] = $src[$k1];
		unset($src[$k1]);
	}
}


class AndroidNotification
{
	static function createPartUpdateNotification($b_id, $title, $message) {
		return array(
			'type' => 'part_update',
			'book_id' => $b_id,
			'title' => $title,
			'message' => $message,
		);
	}
	
	static function createUrlNotofication($url, $title, $message) {
		return array(
			'type' => 'url',
			'title' => $title,
			'message' => $message,
			'url' => $url);
	}
}

function sendPushNotificationForAndroid($device_tokens, $notification) {
    static $GOOGLE_API_KEY_FOR_GCM = "AIzaSyAS4rjn2l4oR4RveqUWZ2NQnWqlbBoFKic";
				  
    $headers = array('Authorization: key=' . $GOOGLE_API_KEY_FOR_GCM,
    				 'Content-Type: application/json');
	
    $post_data = array('data' => $notification,
                  'registration_ids' => $device_tokens);
    
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


