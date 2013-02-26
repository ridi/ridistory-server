<?
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

$api = $app['controllers_factory'];

$api->get('/book/list', function () use ($app) {
	$book = Book::getOpenedBookList();
	return $app->json($book);
});

$api->get('/book/{id}', function ($id) use ($app) {
	$book = Book::get($id);
	return $app->json($book); 
});

$api->get('/book/{id}/parts', function ($id) use ($app) {
	$parts = Part::getByBid($id);
	return $app->json($parts);
});


class CommentController
{
	public function add(Request $req, Application $app) {
		$p_id = $req->get('p_id');
		$device_id = $req->get('device_id');
		$comment = $req->get('comment');
		
		// TODO: abuse detection

		$app['db']->insert('user_comment', compact('p_id', 'device_id', 'comment'));
		return $app->json(array('success' => true));
	}
}


$api->get('/comment/add', 'CommentController::add');

return $api;