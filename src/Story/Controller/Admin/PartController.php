<?php
namespace Story\Controller\Admin;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Story\Model\Book;
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
        Book::deleteCache();
        Part::deleteCache($part['b_id']);

        return $app->redirect('/admin/book/' . $part['b_id']);
    }

    public function editPart(Request $req, Application $app, $id)
    {
        $inputs = $req->request->all();

        // 스토어 ID 입력시에, 공백 제거해달라는 요청이 있어서 반영.
        $inputs['store_id'] = trim($inputs['store_id']);

        $part = Part::get($id);
        Part::update($id, $inputs);
        $app['session']->getFlashBag()->add('alert', array('info' => '파트가 수정되었습니다.'));

        // 캐시 삭제
        Book::deleteCache();
        Part::deleteCache($part['b_id']);

        return $app->redirect('/admin/book/' . $part['b_id']);
    }
}
