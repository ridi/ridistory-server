<?php
namespace Story\Controller\Admin;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Story\Model\TestUser;
use Symfony\Component\HttpFoundation\Request;

class TestUserController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $admin = $app['controllers_factory'];

        $admin->get('add', array($this, 'addTestUser'));
        $admin->get('list', array($this, 'testUserList'));
        $admin->get('{u_id}', array($this, 'testUserDetail'));
        $admin->post('{u_id}/delete', array($this, 'deleteTestUser'));
        $admin->post('{u_id}/edit', array($this, 'editTestUser'));

        return $admin;
    }

    public static function addTestUser(Request $req, Application $app)
    {
        $u_id = $req->get('u_id', null);
        $comment = $req->get('comment', '');
        $is_active = $req->get('is_active', 0);

        if ($u_id != null) {
            $values = array(
                'u_id' => $u_id,
                'comment' => $comment,
                'is_active' => $is_active
            );
            $r = TestUser::add($values);
            if ($r) {
                $app['session']->getFlashBag()->add('alert', array('success' => '테스트 계정이 추가되었습니다.'));
                return $app->redirect('/admin/test_user/' . $u_id);
            } else {
                $app['session']->getFlashBag()->add('alert', array('error' => '테스트 계정을 추가하는 도중 오류가 발생했습니다. (DB 오류)'));
            }
        } else {
            $app['session']->getFlashBag()->add('alert', array('error' => '테스트 계정으로 추가할 유저 ID를 입력해주세요.'));
        }

        return $app->redirect('/admin/test_user/list');
    }

    public static function testUserList(Request $req, Application $app)
    {
        $test_users = TestUser::getWholeList();
        return $app['twig']->render('/admin/test_user_list.twig', array('test_users' => $test_users));
    }

    public static function testUserDetail(Request $req, Application $app, $u_id)
    {
        $test_user = TestUser::get($u_id);
        return $app['twig']->render('/admin/test_user_detail.twig', array('test_user' => $test_user));
    }

    public static function deleteTestUser(Request $req, Application $app, $u_id)
    {
        TestUser::delete($u_id);
        $app['session']->getFlashBag()->add('alert', array('info' => '테스트 계정이 삭제되었습니다.'));
        return $app->json(array('success' => true));
    }

    public static function editTestUser(Request $req, Application $app, $u_id)
    {
        $inputs = $req->request->all();

        TestUser::update($u_id, $inputs);

        $app['session']->getFlashBag()->add('alert', array('info' => '테스트 계정이 수정되었습니다.'));
        return $app->redirect('/admin/test_user/' . $u_id);
    }
}