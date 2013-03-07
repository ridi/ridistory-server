<?
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;

class CommentControllerProvider implements ControllerProviderInterface
{
	public function connect(Application $app) {
		$api = $app['controllers_factory'];
		
		$api->get('/list', array($this, 'commentList'));
		$api->post('/add', array($this, 'commentAdd')); 
		
		return $api;
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
		return $app['twig']->render('/comment/list.twig', array(
			'part' => $part,
			'device_id' => $device_id,
			'num_comments' => $num_comments,
			'comments' => $comments
		));
	}

	public function commentAdd(Request $req, Application $app) {
		$p_id = $req->get('p_id');
		$device_id = $req->get('device_id');
		$nickname = $req->get('nickname');
		$comment = $req->get('comment');
		
		if (empty($nickname) || empty($comment)) {
			return '닉네임이나 댓글이 없다.';
		}
		
		// TODO: abuse detection
		
		$r = PartComment::add($p_id, $device_id, $nickname, $comment);
		return $app->redirect('/comment/list?p_id=' . $p_id);
	}
}

