<?
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;

class WebControllerProvider implements ControllerProviderInterface
{
	public function connect(Application $app) {
		$api = $app['controllers_factory'];
		
		$api->get('/', array($this, 'home'));
		
		$api->get('/book/{b_id}/intro', array($this, 'bookIntro'));
		
		$api->get('/comment/list', array($this, 'commentList'));
		$api->post('/comment/add', array($this, 'commentAdd')); 
		
		return $api;
	}
	
	public function home(Application $app) {
		$recommended_list = BookList::getRecommendedBooks();
		$designated_list = BookList::getDesignatedBooks();
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
		
		// TODO:
		$device_id = 'dd';
		
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
		
		// TODO: abuse detection
		
		$r = PartComment::add($p_id, $device_id, $nickname, $comment);
		return $app->redirect('/comment/list?p_id=' . $p_id);
	}
	
}

function alert_and_back($msg) {
	$r = '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="X-UA-Compatible" content="IE=Edge,chrome=1"></head><body><script>';
	$r .= "alert(" . json_encode($msg) . ");";
	$r .= "history.go(-1);";
	$r .= "</script></body></html>";
	return $r;
}
