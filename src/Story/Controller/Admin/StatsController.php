<?php
namespace Story\Controller\Admin;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;

class StatsController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $admin = $app['controllers_factory'];

        $admin->get('/register_device', array($this, 'registerDeviceStats'));
        $admin->get('/notify_part_update', array($this, 'notifyPartUpdateStats'));
        $admin->get('/like', array($this, 'likeStats'));

        return $admin;
    }

    public static function registerDeviceStats(Request $req, Application $app)
    {
        $begin_date = $temp_begin_date = $req->get('begin_date');
        $end_date = $temp_end_date = $req->get('end_date');

        $register_stat = null;

        if (!$temp_begin_date) {
            $temp_begin_date = '0000-00-00';
        }
        if (!$temp_end_date) {
            $temp_end_date = date('Y-m-d');
        }
        $temp_end_date = date('Y-m-d', strtotime($temp_end_date . ' + 1 day'));

        // 시작일, 종료일 둘 중 하나는 입력해야함.
        if ($begin_date || $end_date) {
            $sql = <<<EOT
    select date, ifnull(A.num_registered_ios, 0) ios, ifnull(B.num_registered_android, 0) android, (ifnull(A.num_registered_ios, 0) + ifnull(B.num_registered_android, 0)) total from
        (select date(reg_date) date, count(*) num_registered_ios from push_devices where reg_date >= ? and reg_date < ? and platform = 'iOS' group by date) A
      natural right outer join
        (select date(reg_date) date, count(*) num_registered_android from push_devices where reg_date >= ? and reg_date < ? and platform = 'android' group by date) B
      order by date desc
EOT;
            $bind = array($temp_begin_date, $temp_end_date, $temp_begin_date, $temp_end_date);
            $register_stat = $app['db']->fetchAll($sql, $bind);
        }

        $total_registered = $app['db']->fetchColumn('select count(*) from push_devices');

        return $app['twig']->render(
            '/admin/stats/register_device.twig',
            array(
                'begin_date' => $begin_date,
                'end_date' => $end_date,
                'total_registered' => $total_registered,
                'register_stat' => $register_stat
            )
        );
    }

    public static function notifyPartUpdateStats(Request $req, Application $app)
    {
        return $app['twig']->render('/admin/stats/notify_part_update.twig');
    }

    public static function likeStats(Request $req, Application $app)
    {
        return $app['twig']->render('/admin/stats/like.twig');
    }
}