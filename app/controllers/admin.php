<?
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

class AdminControllerProvider implements ControllerProviderInterface
{
	public function connect(Application $app) {
		$admin = $app['controllers_factory'];
		
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
		
		$admin->get('/comment/list', function (Request $req, Application $app) {
			$comments = $app['db']->fetchAll('select * from part_comment order by id desc limit 100');
			return $app['twig']->render('/admin/comment_list.twig', array('comments' => $comments));
		});
		
		$admin->get('/api_list', function() use ($app) {
			return $app['twig']->render('/admin/api_list.twig');
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
		return $app['twig']->render('admin/book_detail.twig', array(
			'book' => $book,
			'parts' => $parts,
		));
	}
	
	public function bookAdd(Request $req, Application $app) {
		$b_id = Book::create();
		$app['session']->set('alert', array('success' => '책이 추가되었습니다.'));
		return $app->redirect('/admin/book/' . $b_id);
	}

	public function bookEdit(Request $req, Application $app, $id) {
		$inputs = $req->request->all();
		$upload_days = 0;
		foreach ($inputs['upload_days'] as $k => $v) {
			$upload_days += intval($v);
		}
		$inputs['upload_days'] = $upload_days;
		Book::update($id, $inputs);
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
}


