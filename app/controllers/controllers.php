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
	public function index(Request $request, Application $app) {
		$books = Book::getWholeList();
		return $app['twig']->render('admin/index.twig', array('books' => $books));
	}
	
	public function detail(Request $request, Application $app, $id) {
		$book = Book::get($id);
		
		return $app['twig']->render('admin/book_detail.twig', array('book' => $book));
	}
	
	// add book
	public function add(Request $req, Application $app) {
	}

	public function edit(Request $req, Application $app, $id) {
		$store_id = $req->get('store_id'); 
		$app['db']->update('book', array('store_id' => $store_id), array('id' => $id)); 
		return $app->redirect("/admin/book/$id/");
	}

}

class PartController extends BaseController
{
	public function detail(Request $req, Application $app, $id) {
		$part = $app->json(Part::get($id));
		return $part;
		//return $app['twig']->render('admin/part_detail.twig', array('part' => $part));
	}
}
