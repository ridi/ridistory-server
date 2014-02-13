<?php

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Story\Model\CpAccount;
use Story\Model\Book;

class AdminCpAccountControllerProvider implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $admin = $app['controllers_factory'];

        $admin->get('list', array($this, 'cpAccountList'));
        $admin->get('add', array($this, 'cpAccountAdd'));
        $admin->get('{id}', array($this, 'cpAccountDetail'));
        $admin->post('{id}/delete', array($this, 'cpAccountDelete'));
        $admin->post('{id}/edit', array($this, 'cpAccountEdit'));

        return $admin;
    }

    public function cpAccountList(Request $req, Application $app)
    {
        $cp_accounts = CpAccount::getWholeList();
        return $app['twig']->render('admin/cp_account_list.twig', array('cp_accounts' => $cp_accounts));
    }

    public function cpAccountDetail(Request $req, Application $app, $id)
    {
        $cp_account = CpAccount::get($id);
        $books = Book::getListByCpId($id);

        return $app['twig']->render(
            'admin/cp_account_detail.twig',
            array(
                'cp_account' => $cp_account,
                'books' => $books
            )
        );
    }

    public function bookAdd(Request $req, Application $app)
    {
        $b_id = Book::create();
        $app['session']->set('alert', array('success' => '책이 추가되었습니다.'));
        return $app->redirect('/admin/book/' . $b_id);
    }

    public function bookEdit(Request $req, Application $app, $id)
    {
        $inputs = $req->request->all();

        // 연재 요일
        $upload_days = 0;
        if (isset($inputs['upload_days'])) {
            foreach ($inputs['upload_days'] as $v) {
                $upload_days += intval($v);
            }
        }
        $inputs['upload_days'] = $upload_days;

        $inputs['adult_only'] = isset($inputs['adult_only']);

        // 상세 정보는 별도 테이블로
        $intro = array('b_id' => $id);
        array_move_keys(
            $inputs,
            $intro,
            array(
                'intro_description' => 'description',
                'intro_about_author' => 'about_author'
            )
        );

        Book::update($id, $inputs);
        Book::updateIntro($id, $intro);

        $app['session']->set('alert', array('info' => '책이 수정되었습니다.'));
        return $app->redirect('/admin/book/' . $id);
    }

    public function bookDelete(Request $req, Application $app, $id)
    {
        $parts = Part::getListByBid($id);
        if (count($parts)) {
            return $app->json(array('error' => 'Part가 있으면 책을 삭제할 수 없습니다.'));
        }
        Book::delete($id);
        $app['session']->set('alert', array('info' => '책이 삭제되었습니다.'));
        return $app->json(array('success' => true));
    }
}
