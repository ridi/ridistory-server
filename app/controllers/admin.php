<?
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class BookController
{
	// book list
	public function index(Request $req, Application $app) {
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
	
	public function detail(Request $req, Application $app, $id) {
		$book = Book::get($id);
		$parts = Part::getByBid($id);
		return $app['twig']->render('admin/book_detail.twig', array(
			'book' => $book,
			'parts' => $parts,
		));
	}
	
	// add book
	public function add(Request $req, Application $app) {
		$b_id = Book::create();
		$app['session']->set('alert', array('success' => '책이 추가되었습니다.'));
		return $app->redirect('/admin/book/' . $b_id);
	}

	public function edit(Request $req, Application $app, $id) {
		$inputs = $req->request->all();
		Book::update($id, $inputs);
		$app['session']->set('alert', array('info' => '책이 수정되었습니다.'));
		return $app->redirect('/admin/book/' . $id);
	}
	
	public function delete(Request $req, Application $app, $id) {
		$parts = Part::getByBid($id);
		if (count($parts)) {
			return $app->json(array('error' => 'Part가 있으면 책을 삭제할 수 없습니다.'));
		}
		Book::delete($id);
		return $app->json(array('success' => true));
	}
}


class PartController
{
	public function detail(Request $req, Application $app, $id) {
		$part = Part::get($id);
		return $app['twig']->render('admin/part_detail.twig', array('part' => $part));
	}
	
	public function add(Request $req, Application $app) {
		$b_id = $req->get('b_id');
		$p_id = Part::create($b_id);
		$app['session']->set('alert', array('success' => '파트가 추가되었습니다.'));
		return $app->redirect('/admin/part/' . $p_id);
	}
	
	public function edit(Request $req, Application $app, $id) {
		$inputs = $req->request->all();
		$part = Part::get($id);
		Part::update($id, $inputs);
		return $app->redirect('/admin/book/' . $part['b_id']);
	}
	
	public function delete(Request $req, Application $app, $id) {
		$part = Part::get($id);
		Part::delete($id);
		$app['session']->set('alert', array('success' => '파트가 삭제되었습니다.'));
		return $app->redirect('/admin/book/' . $part['b_id']);
	}
}


$admin = $app['controllers_factory'];

$admin->get('/login', function() use ($app) {
	return $app['twig']->render('/admin/login.twig');
});

$admin->get('/book/list', 'BookController::index');
$admin->get('/book/add', 'BookController::add');
$admin->get('/book/{id}', 'BookController::detail');
$admin->post('/book/{id}/delete', 'BookController::delete');
$admin->post('/book/{id}/edit', 'BookController::edit');

$admin->get('/part/add', 'PartController::add');
$admin->get('/part/{id}', 'PartController::detail');
$admin->get('/part/{id}/delete', 'PartController::delete');
$admin->post('/part/{id}/edit', 'PartController::edit');

$admin->before(function (Request $request) use ($app) {
	$alert = $app['session']->get('alert');
	if ($alert) {
		$app['twig']->addGlobal('alert', $alert);
		$app['session']->remove('alert');
	}
});

$admin->get('/comment/list', function (Request $req, Application $app) {
	$comments = $app['db']->fetchAll('select * from user_comment order by id desc limit 100');
	return $app['twig']->render('/admin/comment_list.twig', array('comments' => $comments));
});

return $admin;
