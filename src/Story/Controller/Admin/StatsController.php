<?php
namespace Story\Controller\Admin;

use Doctrine\DBAL\Connection;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;

class StatsController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $admin = $app['controllers_factory'];

        $admin->get('/register_device', array($this, 'registerDeviceStats'));
        $admin->get('/user_interests', array($this, 'userInterestsStats'));
        $admin->get('/user_likes', array($this, 'userLikesStats'));

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

    public static function userInterestsStats(Request $req, Application $app)
    {
        $bid_list = trim($req->get('bid_list'));
        $search_all = $req->get('search_all', false);

        $user_interests = null;

        if ($bid_list || $search_all) {
            $sql = <<<EOT
select b.id, b.title,
 ifnull(interested_d6, 0) interested_d6, ifnull(interested_d5, 0) interested_d5, ifnull(interested_d4, 0) interested_d4, ifnull(interested_d3, 0) interested_d3, ifnull(interested_d2, 0) interested_d2, ifnull(interested_d1, 0) interested_d1, ifnull(interested_d0, 0) interested_d0,
 (ifnull(interested_d6, 0) + ifnull(interested_d5, 0) + ifnull(interested_d4, 0) + ifnull(interested_d3, 0) + ifnull(interested_d2, 0) + ifnull(interested_d1, 0) + ifnull(interested_d0, 0)) as interested_sum,
 interested_total from book b
    left join (select b_id, count(b_id) interested_d6 from user_interest where datediff(now(), `timestamp`) = 6 group by b_id, date(`timestamp`)) D6 on b.id = D6.b_id
    left join (select b_id, count(b_id) interested_d5 from user_interest where datediff(now(), `timestamp`) = 5 group by b_id, date(`timestamp`)) D5 on b.id = D5.b_id
    left join (select b_id, count(b_id) interested_d4 from user_interest where datediff(now(), `timestamp`) = 4 group by b_id, date(`timestamp`)) D4 on b.id = D4.b_id
    left join (select b_id, count(b_id) interested_d3 from user_interest where datediff(now(), `timestamp`) = 3 group by b_id, date(`timestamp`)) D3 on b.id = D3.b_id
    left join (select b_id, count(b_id) interested_d2 from user_interest where datediff(now(), `timestamp`) = 2 group by b_id, date(`timestamp`)) D2 on b.id = D2.b_id
    left join (select b_id, count(b_id) interested_d1 from user_interest where datediff(now(), `timestamp`) = 1 group by b_id, date(`timestamp`)) D1 on b.id = D1.b_id
    left join (select b_id, count(b_id) interested_d0 from user_interest where datediff(now(), `timestamp`) = 0 group by b_id, date(`timestamp`)) D0 on b.id = D0.b_id
    left join (select b_id, count(b_id) interested_total from user_interest group by b_id) TOTAL on b.id = TOTAL.b_id
EOT;
            if (!$search_all) {
                $b_ids = explode(PHP_EOL, $bid_list);  // 조회할 책 목록
                foreach ($b_ids as &$b_id) {
                    $b_id = trim($b_id);
                }

                $sql .= ' where b.id in (?) order by b.title';

                $stmt = $app['db']->executeQuery(
                    $sql,
                    array($b_ids),
                    array(Connection::PARAM_INT_ARRAY)
                );
                $user_interests = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } else {
                $sql .= ' order by b.title';

                $user_interests = $app['db']->fetchAll($sql);
            }
        }

        return $app['twig']->render(
            '/admin/stats/user_interests.twig',
            array(
                'bid_list' => $bid_list,
                'search_all' => $search_all,
                'user_interests' => $user_interests
            )
        );
    }

    public static function userLikesStats(Request $req, Application $app)
    {
        $begin_date = $temp_begin_date = $req->get('begin_date');
        $end_date = $temp_end_date = $req->get('end_date');

        $user_likes = null;

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
select p.b_id, b.title, count(distinct p.id) count, sum(T.CNT) sum, sum(T.CNT) / count(distinct p.id) avg from
 (
  select p_id, count(device_id) CNT from user_part_like
  where timestamp >= ? and timestamp < ? group by p_id
 ) T
 join part p on T.p_id = p.id
 left outer join book b on p.b_id = b.id
group by p.b_id;
EOT;
            $bind = array($temp_begin_date, $temp_end_date);
            $user_likes = $app['db']->fetchAll($sql, $bind);
        }

        return $app['twig']->render(
            '/admin/stats/user_likes.twig',
            array(
                'begin_date' => $begin_date,
                'end_date' => $end_date,
                'user_likes' => $user_likes
            )
        );
    }
}