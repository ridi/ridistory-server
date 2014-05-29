<?php
namespace Story\Controller\Admin;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Story\Model\Part;

class PartController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $admin = $app['controllers_factory'];

        $admin->get('add', array($this, 'addPart'));
        $admin->get('{id}', array($this, 'partDetail'));
        $admin->get('{id}/delete', array($this, 'deletePart'));
        $admin->post('{id}/edit', array($this, 'editPart'));

        return $admin;
    }

    public function addPart(Request $req, Application $app)
    {
        $b_id = $req->get('b_id');
        $p_id = Part::create($b_id);
        $app['session']->getFlashBag()->add('alert', array('success' => '파트가 추가되었습니다.'));
        return $app->redirect('/admin/part/' . $p_id);
    }

    public function partDetail(Request $req, Application $app, $id)
    {
        $part = Part::get($id);
        return $app['twig']->render('admin/book/part_detail.twig', array('part' => $part));
    }

    public function deletePart(Request $req, Application $app, $id)
    {
        $part = Part::get($id);
        Part::delete($id);
        $app['session']->getFlashBag()->add('alert', array('info' => '파트가 삭제되었습니다.'));

        // 캐시 삭제
        $app['cache']->delete('book_list_1');
        $app['cache']->delete('book_list_2_0');
        $app['cache']->delete('book_list_2_1');
        $app['cache']->delete('book_list_3_0');
        $app['cache']->delete('book_list_3_1');
        $app['cache']->delete('completed_book_list_0');
        $app['cache']->delete('completed_book_list_1');
        $app['cache']->delete('part_list_0_0_' . $part['b_id']);
        $app['cache']->delete('part_list_0_1_' . $part['b_id']);
        $app['cache']->delete('part_list_1_0_' . $part['b_id']);
        $app['cache']->delete('part_list_1_1_' . $part['b_id']);

        return $app->redirect('/admin/book/' . $part['b_id']);
    }

    public function editPart(Request $req, Application $app, $id)
    {
        $inputs = $req->request->all();
        $part = Part::get($id);
        Part::update($id, $inputs);
        $app['session']->getFlashBag()->add('alert', array('info' => '파트가 수정되었습니다.'));

        // 캐시 삭제
        $app['cache']->delete('book_list_1');
        $app['cache']->delete('book_list_2_0');
        $app['cache']->delete('book_list_2_1');
        $app['cache']->delete('book_list_3_0');
        $app['cache']->delete('book_list_3_1');
        $app['cache']->delete('completed_book_list_0');
        $app['cache']->delete('completed_book_list_1');
        $app['cache']->delete('part_list_0_0_' . $part['b_id']);
        $app['cache']->delete('part_list_0_1_' . $part['b_id']);
        $app['cache']->delete('part_list_1_0_' . $part['b_id']);
        $app['cache']->delete('part_list_1_1_' . $part['b_id']);

        return $app->redirect('/admin/book/' . $part['b_id']);
    }
}
