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

return $api;