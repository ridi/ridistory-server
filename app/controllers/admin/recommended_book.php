<?php

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Story\Model\RecommendedBook;

class AdminRecommendedBookControllerProvider implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $admin = $app['controllers_factory'];

        $admin->get('add', array($this, 'addRecommendedBook'));
        $admin->get('{id}', array($this, 'recommendedBookDetail'));
        $admin->get('{id}/delete', array($this, 'deleteRecommendedBook'));
        $admin->post('{id}/edit', array($this, 'editRecommendedBook'));

        return $admin;
    }

    public function addRecommendedBook(Request $req, Application $app)
    {
        $b_id = $req->get('b_id');
        $rb_id = RecommendedBook::create($b_id);
        $app['session']->getFlashBag()->add('alert', array('success' => '작가의 다른 작품이 추가되었습니다.'));
        return $app->redirect('/admin/recommended_book/' . $rb_id);
    }

    public function recommendedBookDetail(Request $req, Application $app, $id)
    {
        $recommended_book = RecommendedBook::get($id);
        return $app['twig']->render('admin/recommended_book_detail.twig', array('recommended_book' => $recommended_book));
    }

    public function deleteRecommendedBook(Request $req, Application $app, $id)
    {
        $recommended_book = RecommendedBook::get($id);
        RecommendedBook::delete($id);
        $app['session']->getFlashBag()->add('alert', array('info' => '작가의 다른 작품이 삭제되었습니다.'));
        return $app->redirect('/admin/book/' . $recommended_book['b_id']);
    }

    public function editRecommendedBook(Request $req, Application $app, $id)
    {
        $inputs = $req->request->all();
        $recommended_book = RecommendedBook::get($id);
        RecommendedBook::update($id, $inputs);
        $app['session']->getFlashBag()->add('alert', array('info' => '작가의 다른 작품이 수정되었습니다.'));
        return $app->redirect('/admin/book/' . $recommended_book['b_id']);
    }
}
