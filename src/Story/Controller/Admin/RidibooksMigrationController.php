<?php
namespace Story\Controller\Admin;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Story\Model\Buyer;
use Story\Model\RidibooksMigration;
use Symfony\Component\HttpFoundation\Request;

class RidibooksMigrationController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $admin = $app['controllers_factory'];

        $admin->post('add', array($this, 'addRidibooksMigrationHistory'));
        $admin->get('list', array($this, 'ridibooksMigrationHistoryList'));
        $admin->post('{u_id}/delete', array($this, 'deleteRidibooksMigrationHistory'));

        return $admin;
    }

    public function addRidibooksMigrationHistory(Request $req, Application $app)
    {
        $u_id = $req->get('u_id', null);
        $ridibooks_id = $req->get('ridibooks_id', null);

        try {
            if ($u_id == null || $ridibooks_id == null) {
                throw new \Exception('유저 ID와 리디북스 계정을 모두 입력해주셔야 합니다.');
            }
            if (!Buyer::isValidUid($u_id)) {
                throw new \Exception('잘못된 유저 ID 입니다.');
            }

            $r = RidibooksMigration::add($u_id, $ridibooks_id);
            if (!$r) {
                throw new \Exception('리디북스 계정이전 정보를 추가하지 못했습니다.');
            }
            $app['session']->getFlashBag()->add('alert', array('success' => '리디북스 계정이전 정보가 추가되었습니다.'));
        } catch (\Exception $e) {
            $app['session']->getFlashBag()->add('alert', array('error' => $e->getMessage()));
        }
        return $app->redirect('/admin/ridibooks_migration/list');
    }

    public function ridibooksMigrationHistoryList(Request $req, Application $app)
    {
        $search_type = $req->get('search_type', 'uid');
        $search_keyword = $req->get('search_keyword', null);
        $cur_page = $req->get('page', 0);

        $size = 50;
        $offset = $cur_page * $size;

        if ($search_keyword) {
            $migrations = RidibooksMigration::getListBySearchTypeAndKeyword($search_type, $search_keyword);
        } else {
            $migrations = RidibooksMigration::getListByOffsetAndSize($offset, $size);
        }

        $migrated_count = RidibooksMigration::getMigratedCount();

        return $app['twig']->render(
            'admin/buyer/buyer_ridibooks_migration_history_list.twig',
            array(
                'search_type' => $search_type,
                'search_keyword' => $search_keyword,
                'migrated_count' => $migrated_count,
                'migrations' => $migrations,
                'cur_page' => $cur_page
            )
        );
    }

    public function deleteRidibooksMigrationHistory(Request $req, Application $app, $u_id)
    {
        RidibooksMigration::delete($u_id);
        $app['session']->getFlashBag()->add('alert', array('info' => '리디북스 계정이전 정보가 삭제되었습니다.'));
        return $app->json(array('success' => true));
    }
}
