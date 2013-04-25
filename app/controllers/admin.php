<?

require_once dirname(__FILE__) . "/../utils/push_device_picker.php";
require_once dirname(__FILE__) . "/../utils/push_android.php";
require_once dirname(__FILE__) . "/../utils/push_ios.php";

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
		$admin->get('/push/notify_url', array($this, 'pushNotifyUrl'));
		$admin->get('/push/notify_update_id_range', array($this, 'pushNotifyUpdateUsingIdRange'));
		$admin->get('/push/ios_payload_length.ajax', function(Request $req) use ($app) {
			$b_id = $req->get('b_id');
			$message = $req->get('message');
			
			$notification_ios = IosPush::createPartUpdateNotification($b_id);
			$payload = IosPush::getPayloadInJson($message, $notification_ios);
			$payload_length = strlen($payload);
			
			return $app->json(array("payload_length" => $payload_length));
		});
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
		$admin->get('/push/notify_new_book', array($this, 'pushNotifyNewBook'));
		
		$admin->get('/notice/list', array($this, 'noticeList'));
		$admin->get('/notice/add', array($this, 'noticeAdd'));
		$admin->get('/notice/{n_id}', array($this, 'noticeDetail'));
		$admin->post('/notice/{n_id}/edit', array($this, 'noticeEdit'));
		$admin->post('/notice/{n_id}/delete', array($this, 'noticeDelete'));
		
		$admin->get('/stats', array($this, 'stats'));
		
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
		
		$app['twig']->addFunction(new Twig_SimpleFunction('today', function() {
			return date('Y-m-d');
		}));
		
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
		if (isset($inputs['upload_days'])) {
			foreach ($inputs['upload_days'] as $k => $v) {
				$upload_days += intval($v);
			}
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
		
		$app['twig']->addFilter(new Twig_SimpleFilter('long2ip', function ($ip) {
		    return long2ip($ip);
		}));
		 
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
	
	/**
	 * 새로운 파트가 업데이트 되었을 때, 관심책을 등록한 사용자에게 PUSH
	 */
	public static function pushNotifyUpdate(Request $req, Application $app) {
		$b_id = $req->get('b_id');
		$message = $req->get('message');
		
		if (empty($b_id) || empty($message)) {
			return 'not all required fields are filled';
		}
		
		$pick_result = PushDevicePicker::pickDevicesUsingInterestBook($app['db'], $b_id);
		
		// Android 전송
		$notification_android = AndroidPush::createPartUpdateNotification($b_id, $message);
		$result_android = AndroidPush::sendPush($pick_result->getAndroidDevices(), $notification_android);
		
		// iOS 전송
		$notification_ios = IosPush::createPartUpdateNotification($b_id);
		$result_ios = IosPush::sendPush($pick_result->getIosDevices(), $message, $notification_ios);
		
		// TODO: iOS 결과도 필요
		return $app->json(array("Android" => $result_android,
								"iOS" => $result_ios));
	}

	public static function pushNotifyUrl(Request $req, Application $app) {
		$b_id = $req->get('b_id');
		$url = $req->get('url');
		$message = $req->get('message');
		
		if (empty($b_id) || empty($message)) {
			return 'not all required fields are filled';
		}
		
		$pick_result = PushDevicePicker::pickDevicesUsingInterestBook($app['db'], $b_id);
		
		// Android 전송
		$notification_android = AndroidPush::createUrlNotification($url, $message);
		$result_android = AndroidPush::sendPush($pick_result->getAndroidDevices(), $notification_android);
		
		// iOS 전송
		$notification_ios = IosPush::createUrlNotification($url);
		$result_ios = IosPush::sendPush($pick_result->getIosDevices(), $message, $notification_ios);
		
		// TODO: iOS 결과도 필요
		return $app->json(array("Android" => $result_android,
								"iOS" => $result_ios));
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
	
	
	public static function stats(Application $app) {
		// 기기등록 통계
		$total_registered = $app['db']->fetchColumn('select count(*) from push_devices');
		$register_stat = $app['db']->fetchAll('select date(reg_date) date, count(*) num_registered from push_devices where datediff(now(), reg_date) < 10 group by date order by date desc');
		
		// 다운로드 통계
		$total_downloaded = $app['db']->fetchColumn('select count(*) from stat_download');
		
		$sql = <<<EOT
select part.id p_id, part.title, download_count from part
 join (select p_id, count(p_id) download_count from stat_download
 		group by p_id order by count(p_id) desc limit 10) stat
 on part.id = stat.p_id
 order by download_count desc
EOT;
		$download_stat = $app['db']->fetchAll($sql);
		
		// 댓글 통계
		$sql = <<<EOT
select b.title, seq, part_title, num_comment from book b
 join (select p.b_id, p.seq, p.title part_title, count(*) num_comment from part_comment c join part p on p.id = c.p_id group by p_id) c on c.b_id = b.id 
EOT;

		$most_comment_parts = $app['db']->fetchAll($sql . 'order by num_comment desc limit 10');
		$least_comment_parts = $app['db']->fetchAll($sql . 'order by num_comment limit 10');
		
		return $app['twig']->render('/admin/stats.twig', compact('total_registered', 'register_stat',
																 'total_downloaded', 'download_stat',
																 'most_comment_parts', 'least_comment_parts'));
	}
}

function array_move_keys(&$src, &$dst, array $keys) {
	foreach ($keys as $k1 => $k2) {
		$dst[$k2] = $src[$k1];
		unset($src[$k1]);
	}
}

