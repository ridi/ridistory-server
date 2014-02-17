<?php

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Story\Model\Part;

class AdminPartControllerProvider implements ControllerProviderInterface
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

        $admin->get('add', array($this, 'partAdd'));
        $admin->get('{id}', array($this, 'partDetail'));
        $admin->get('{id}/delete', array($this, 'partDelete'));
        $admin->post('{id}/edit', array($this, 'partEdit'));

        return $admin;
    }

    public function partDetail(Request $req, Application $app, $id)
    {
        $part = Part::get($id);
        return $app['twig']->render('admin/part_detail.twig', array('part' => $part));
    }

    public function partAdd(Request $req, Application $app)
    {
        $b_id = $req->get('b_id');
        $p_id = Part::create($b_id);
        $app['session']->getFlashBag()->add('alert', array('success' => '파트가 추가되었습니다.'));
        return $app->redirect('/admin/part/' . $p_id);
    }

    public function partEdit(Request $req, Application $app, $id)
    {
        $inputs = $req->request->all();
        $part = Part::get($id);
        Part::update($id, $inputs);
        $app['session']->getFlashBag()->add('alert', array('info' => '파트가 수정되었습니다.'));
        return $app->redirect('/admin/book/' . $part['b_id']);
    }

    public function partDelete(Request $req, Application $app, $id)
    {
        $part = Part::get($id);
        Part::delete($id);
        $app['session']->getFlashBag()->add('alert', array('info' => '파트가 삭제되었습니다.'));
        return $app->redirect('/admin/book/' . $part['b_id']);
    }
}
