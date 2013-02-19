<?
namespace Admin;

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
		$books = $app['db']->fetchAll('select * from book');

		return $app['twig']->render('admin/index.twig', array('books' => $books));
	}
	
	public function detail(Request $request, Application $app, $id) {
		$book = $app['db']->fetchAssoc('select * from book where id = ?', array($id));
		//return var_dump($book);
		return $app['twig']->render('admin/book_detail.twig', array('book' => $book));
	}
	
	// add book
	public function add(Request $req, Application $app) {
	}

	public function edit(Request $req, Application $app, $id) {
		return $id;
	}

}
