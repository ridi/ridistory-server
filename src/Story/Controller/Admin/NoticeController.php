<?php
namespace Story\Controller\Admin;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Story\Model\Notice;
use Symfony\Component\HttpFoundation\Request;

class NoticeController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $admin = $app['controllers_factory'];

        $admin->get('/add', array($this, 'addNotice'));
        $admin->get('/list', array($this, 'noticeList'));
        $admin->get('/{n_id}', array($this, 'noticeDetail'));
        $admin->post('/{n_id}/delete', array($this, 'deleteNotice'));
        $admin->post('/{n_id}/edit', array($this, 'editNotice'));

        return $admin;
    }

    public static function addNotice(Application $app)
    {
        $r = Notice::create();
        $app['session']->getFlashBag()->add('alert', array('success' => '공지사항이 추가되었습니다.'));
        return $app->redirect('/admin/notice/' . $r);
    }

    public static function noticeList(Request $req, Application $app)
    {
        $notice_list = Notice::getList(false);
        return $app['twig']->render('/admin/notice_list.twig', array('notice_list' => $notice_list));
    }

    public static function noticeDetail(Request $req, Application $app, $n_id)
    {
        $notice = Notice::get($n_id, false);
        return $app['twig']->render('/admin/notice_detail.twig', array('notice' => $notice));
    }

    public static function deleteNotice(Request $req, Application $app, $n_id)
    {
        Notice::delete($n_id);
        $app['session']->getFlashBag()->add('alert', array('info' => '공지사항이 삭제되었습니다.'));
        return $app->json(array('success' => true));
    }

    public static function editNotice(Request $req, Application $app, $n_id)
    {
        $inputs = $req->request->all();

        Notice::update($n_id, $inputs);

        $app['session']->getFlashBag()->add('alert', array('info' => '공지사항이 수정되었습니다.'));
        return $app->redirect('/admin/notice/' . $n_id);
    }
}