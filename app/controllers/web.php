<?
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;

class WebControllerProvider implements ControllerProviderInterface
{
	public function connect(Application $app) {
		$api = $app['controllers_factory'];
		
		//$api->get('/', array($this, 'home'));
		
		$api->get('/book/{b_id}/intro', array($this, 'bookIntro'));
		
		$api->get('/comment/list', array($this, 'commentList'));
		$api->post('/comment/add', array($this, 'commentAdd'));
		$api->get('/comment/{c_id}/delete', array($this, 'commentDelete'));
		
		$api->get('/notice', array($this, 'notice'));
		
		$api->get('/banner', array($this, 'banner'));
		
		$api->get('/preview/{p_id}/{title}', array($this, 'previewPart'));
		
		return $api;
	}
	
	public function notice(Application $app) {
		$notice = $app['db']->fetchAll('select * from notice where is_visible = 1 order by reg_date desc');
		return $app['twig']->render('/notice.twig', array('notice' => $notice));
	}
	
	public function banner(Request $req, Application $app) {
		$platform = $req->get('platform');
		$banners = $app['cache']->fetch('banners', function() use ($app) {
			return $app['db']->fetchAll('select * from banner where is_visible = 1 order by reg_date desc');
		}, 60 * 30);
		return $app['twig']->render('/banner.twig', array(
			'platform' => $platform,
			'banners' => $banners,
		));
	}
	
	public function home(Application $app) {
		$recommended_list = BookList::getRecommendedBooks();
		$designated_list = BookList::getDesignatedBooks();
		foreach ($designated_list as &$book) {
			$begin_date = new DateTime($book['begin_date']);
			$interval = $begin_date->diff(new DateTime());
			$book['d_day'] = $interval->format('D%R%a');
		}
		$today_list = BookList::getTodayBest();
		
		return $app['twig']->render('/main.twig', array(
			'recommended_list' => $recommended_list,
			'designated_list' => $designated_list,
			'today_list' => $today_list,
		));
	}
	
	public function bookIntro(Application $app, $b_id) {
		$book = Book::get($b_id);
		$book['intro'] = Book::getIntro($b_id);
		return $app['twig']->render('/book_intro.twig', array('book' => $book));
	}

	public function commentList(Request $req, Application $app) {
		$p_id = $req->get('p_id');
		$part = Part::get($p_id);
		if ($part === false) {
			return '있지도 않은 스토리다.';
		}
		
		$device_id = $req->get('device_id');
		
		$num_comments = PartComment::getCommentCount($p_id);
		$comments = PartComment::getList($p_id);

		return $app['twig']->render('/comment.twig', array(
			'part' => $part,
			'device_id' => $device_id,
			'num_comments' => $num_comments,
			'comments' => $comments
		));
	}

	public function commentAdd(Request $req, Application $app) {
		$p_id = $req->get('p_id');
		$device_id = $req->get('device_id');
		$nickname = trim($req->get('nickname'));
		$comment = trim($req->get('comment'));
		
		if (empty($nickname) || empty($comment)) {
			return alert_and_back('닉네임이나 댓글이 없다.');
		}
		
		$ip = ip2long($_SERVER['REMOTE_ADDR']);
		
		// TODO: abuse detection
		
		$r = PartComment::add($p_id, $device_id, $nickname, $comment, $ip);
		return $app->redirect($req->headers->get('Referer'));
	}
	
	public function commentDelete(Request $req, Application $app, $c_id) {
		PartComment::delete($c_id);
		return $app->redirect($req->headers->get('Referer'));
	}
	
	public function previewPart(Request $req, Application $app, $p_id, $title) {
		$p = new Part($p_id);
		if (!$p->isOpened()) {
			return $app->abort(404);
		}
		
		return $app->redirect('http://preview.ridibooks.com/' . $p->store_id . '?mobile');
	}
}

function alert_and_back($msg) {
	$r = '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="X-UA-Compatible" content="IE=Edge,chrome=1"></head><body><script>';
	$r .= "alert(" . json_encode($msg) . ");";
	$r .= "history.go(-1);";
	$r .= "</script></body></html>";
	return $r;
}
