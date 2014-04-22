<?php
namespace Story\Controller\Admin;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Story\Model\CpAccount;
use Story\Model\Book;

class CpAccountController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $admin = $app['controllers_factory'];

        $admin->get('add', array($this, 'addCpAccount'));
        $admin->get('list', array($this, 'cpAccountList'));
        $admin->get('{id}', array($this, 'cpAccountDetail'));
        $admin->post('{id}/delete', array($this, 'deleteCpAccount'));
        $admin->post('{id}/edit', array($this, 'editCpAccount'));

        return $admin;
    }

    public function addCpAccount(Request $req, Application $app)
    {
        $cp_id = CpAccount::create();
        $app['session']->getFlashBag()->add('alert', array('success' => 'CP 회원이 추가되었습니다.'));
        return $app->redirect('/admin/cp_account/' . $cp_id);
    }

    public function cpAccountList(Request $req, Application $app)
    {
        $cp_accounts = CpAccount::getWholeList();

        $app['twig']->addFunction(
            new Twig_SimpleFunction('date_format', function ($date) {
                return date('Y-m-d', strtotime($date));
            })
        );

        $app['twig']->addFunction(
            new Twig_SimpleFunction('get_cp_type', function ($cp_type) {
                switch($cp_type) {
                    case CpAccount::TYPE_PUBLISHER:
                        return '출판사';
                    case CpAccount::TYPE_PRIVATE:
                        return '개인';
                    case CpAccount::TYPE_AG:
                        return 'AG';
                    case CpAccount::TYPE_CLOSED:
                        return '폐업';
                    case CpAccount::TYPE_ETC:
                        return 'ETC';
                    default:
                        return '';
                }
            })
        );

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

    public function deleteCpAccount(Request $req, Application $app, $id)
    {
        $books = Book::getListByCpId($id);
        if (count($books)) {
            return $app->json(array('error' => '해당 CP 회원의 책이 있으면 CP 회원을 삭제할 수 없습니다.'));
        }
        CpAccount::delete($id);
        $app['session']->getFlashBag()->add('alert', array('info' => 'CP 회원이 삭제되었습니다.'));
        return $app->json(array('success' => true));
    }

    public function editCpAccount(Request $req, Application $app, $id)
    {
        $inputs = $req->request->all();
        $inputs['is_ridibooks_cp'] = isset($inputs['is_ridibooks_cp']);

        CpAccount::update($id, $inputs);

        $app['session']->getFlashBag()->add('alert', array('info' => 'CP 회원 정보가 수정되었습니다.'));
        return $app->redirect('/admin/cp_account/' . $id);
    }
}
