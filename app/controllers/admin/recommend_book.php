<?php

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Story\Model\RecommendBook;

class AdminRecommendBookControllerProvider implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $admin = $app['controllers_factory'];

        $admin->before(
            function (Request $req) use ($app) {
                $alert = $app['session']->get('alert');
                if ($alert) {
                    $app['twig']->addGlobal('alert', $alert);
                    $app['session']->remove('alert');
                }
            }
        );

        $admin->get('add', array($this, 'recommendBookAdd'));
        $admin->get('{id}', array($this, 'recommendBookDetail'));
        $admin->get('{id}/delete', array($this, 'recommendBookDelete'));
        $admin->post('{id}/edit', array($this, 'recommendBookEdit'));

        return $admin;
    }

    public function recommendBookDetail(Request $req, Application $app, $id)
    {
        $recommend_book = RecommendBook::get($id);
        return $app['twig']->render('admin/recommend_book_detail.twig', array('recommend_book' => $recommend_book));
    }

    public function recommendBookAdd(Request $req, Application $app)
    {
        $b_id = $req->get('b_id');
        $rb_id = RecommendBook::create($b_id);
        $app['session']->getFlashBag()->add('alert', array('success' => '작가의 다른 작품이 추가되었습니다.'));
        return $app->redirect('/admin/recommend_book/' . $rb_id);
    }

    public function recommendBookEdit(Request $req, Application $app, $id)
    {
        $inputs = $req->request->all();
        $recommend_book = RecommendBook::get($id);
        RecommendBook::update($id, $inputs);
        $app['session']->getFlashBag()->add('alert', array('info' => '작가의 다른 작품이 수정되었습니다.'));
        return $app->redirect('/admin/book/' . $recommend_book['b_id']);
    }

    public function recommendBookDelete(Request $req, Application $app, $id)
    {
        $recommend_book = RecommendBook::get($id);
        RecommendBook::delete($id);
        $app['session']->getFlashBag()->add('alert', array('info' => '작가의 다른 작품이 삭제되었습니다.'));
        return $app->redirect('/admin/book/' . $recommend_book['b_id']);
    }
}
