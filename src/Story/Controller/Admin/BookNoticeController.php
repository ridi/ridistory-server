<?php
namespace Story\Controller\Admin;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Story\Entity\BookNoticeFactory;
use Symfony\Component\HttpFoundation\Request;

class BookNoticeController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $admin = $app['controllers_factory'];

        $admin->get('add', array($this, 'addBookNotice'));
        $admin->get('{id}', array($this, 'bookNoticeDetail'));
        $admin->get('{id}/delete', array($this, 'deleteBookNotice'));
        $admin->post('{id}/edit', array($this, 'editBookNotice'));

        return $admin;
    }

    public function addBookNotice(Request $req, Application $app)
    {
        $b_id = $req->get('b_id');
        $n_id = BookNoticeFactory::create($b_id);
        $app['session']->getFlashBag()->add('alert', array('success' => '안내글이 추가되었습니다.'));
        return $app->redirect('/admin/book/notice/' . $n_id);
    }

    public function bookNoticeDetail(Request $req, Application $app, $id)
    {
        $book_notice = BookNoticeFactory::get($id, false);
        return $app['twig']->render('admin/book/book_notice_detail.twig', array('book_notice' => $book_notice));
    }

    public function deleteBookNotice(Request $req, Application $app, $id)
    {
        $book_notice = BookNoticeFactory::get($id, false);
        BookNoticeFactory::delete($id);
        $app['session']->getFlashBag()->add('alert', array('info' => '안내글이 삭제되었습니다.'));

        // 캐시 삭제
        $app['cache']->delete('book_notice_list_' . $book_notice->b_id);

        return $app->redirect('/admin/book/' . $book_notice->b_id);
    }

    public function editBookNotice(Request $req, Application $app, $id)
    {
        $inputs = $req->request->all();
        $book_notice = BookNoticeFactory::get($id, false);
        $r = BookNoticeFactory::update($id, $inputs);
        if ($r) {
            $app['session']->getFlashBag()->add('alert', array('info' => '안내글이 수정되었습니다.'));
        } else {
            $app['session']->getFlashBag()->add('alert', array('error' => '안내글을 수정하지 못했습니다. (내용 중복 오류 등)'));
        }

        // 캐시 삭제
        $app['cache']->delete('book_notice_list_' . $book_notice->b_id);

        return $app->redirect('/admin/book/' . $book_notice->b_id);
    }
}
