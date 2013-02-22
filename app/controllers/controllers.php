<?
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class BaseController
{
	function __construct() {
	}
}

class BookController extends BaseController
{
	// book list
	public function index(Request $req, Application $app) {
		$books = Book::getWholeList();
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
		return $app->redirect('/admin/book/' . $b_id);
	}

	public function edit(Request $req, Application $app, $id) {
		$inputs = $req->request->all();
		Book::update($id, $inputs);
		return $app->redirect('/admin/book/' . $id);
	}
}

class PartController extends BaseController
{
	public function detail(Request $req, Application $app, $id) {
		$part = Part::get($id);
		return $app['twig']->render('admin/part_detail.twig', array('part' => $part));
	}
	
	public function add(Request $req, Application $app) {
		$b_id = $req->get('b_id');
		$p_id = Part::create($b_id);
		return $app->redirect('/admin/part/' . $p_id);
	}
	
	public function edit(Request $req, Application $app, $id) {
		$inputs = $req->request->all();
		Part::update($id, $inputs);
		return $app->redirect('/admin/book/' . $id);
	}
	
	public function delete(Request $req, Application $app, $id) {
		$part = Part::get($id);
		Part::delete($id);
		return $app->redirect('/admin/book/' . $part['b_id']);
	}
}
