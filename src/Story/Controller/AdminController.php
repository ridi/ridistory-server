<?php
namespace Story\Controller;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Story\Model\InAppBilling;
use Story\Model\PartComment;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Story\Util\IosPush;
use Story\Util\AndroidPush;
use Story\Util\PushDevicePicker;
use Story\Util\PickDeviceResult;

class AdminController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        /**
         * @var $admin \Silex\ControllerCollection
         */
        $admin = $app['controllers_factory'];

        $admin->get(
            '/',
            function () use ($app) {
                return $app->redirect('/admin/book/list');
            }
        );

        $admin->get(
            '/logout',
            function () use ($app) {
                // HTTP basic authentication logout 에는 이거밖에 없다..
                session_destroy();

                $response = new Response();
                $response->setStatusCode(401, 'Unauthorized.');
                return $response;
            }
        );

        $admin->get(
            '/api_list',
            function () use ($app) {
                return $app['twig']->render('/admin/api_list.twig');
            }
        );

        $admin->get('/comment/list', array($this, 'commentList'));
        $admin->get('/comment/{c_id}/delete', array($this, 'deleteComment'));

        $admin->get('/push/dashboard', array($this, 'pushDashboard'));
        $admin->get('/push/notify_update', array($this, 'pushNotifyUpdate'));
        $admin->get('/push/notify_url', array($this, 'pushNotifyUrl'));
        $admin->get('/push/notify_new_book', array($this, 'pushNotifyNewBook'));
        $admin->get('/push/notify_remind', array($this, 'pushNotifyRemind'));
        $admin->get('/push/notify_update_id_range', array($this, 'pushNotifyUpdateUsingIdRange'));
        $admin->get(
            '/push/ios_payload_length.ajax',
            function (Request $req) use ($app) {
                $b_id = $req->get('b_id');
                $message = $req->get('message');

                $notification_ios = IosPush::createPartUpdateNotification($b_id);
                $payload = IosPush::getPayloadInJson($message, $notification_ios);
                $payload_length = strlen($payload);

                return $app->json(array("payload_length" => $payload_length));
            }
        );
        $admin->get(
            '/push/target_count.ajax',
            function (Request $req) use ($app) {
                $b_id = $req->get('b_id');
                $sql = <<<EOT
select platform, count(*) count from user_interest i
 join push_devices p on p.device_id = i.device_id
where b_id = ? and i.cancel = 0
group by platform
EOT;
                $r = $app['db']->fetchAssoc($sql, array($b_id));
                return $app->json($r);
            }
        );

        $admin->get('/notice/add', array($this, 'addNotice'));
        $admin->get('/notice/list', array($this, 'noticeList'));
        $admin->get('/notice/{n_id}', array($this, 'noticeDetail'));
        $admin->post('/notice/{n_id}/delete', array($this, 'deleteNotice'));
        $admin->post('/notice/{n_id}/edit', array($this, 'editNotice'));

        $admin->get('/banner/add', array($this, 'addBanner'));
        $admin->get('/banner/list', array($this, 'bannerList'));
        $admin->get('/banner/{banner_id}', array($this, 'bannerDetail'));
        $admin->post('/banner/{banner_id}/delete', array($this, 'deleteBanner'));
        $admin->post('/banner/{banner_id}/edit', array($this, 'editBanner'));

        $admin->get('/inapp_product/add', array($this, 'addInAppProduct'));
        $admin->get('/inapp_product/list', array($this, 'inAppProductList'));
        $admin->get('/inapp_product/{iab_id}', array($this, 'inAppProductDetail'));
        $admin->post('/inapp_product/{iab_id}/delete', array($this, 'deleteInAppProduct'));
        $admin->post('/inapp_product/{iab_id}/edit', array($this, 'editInAppProduct'));

        $admin->get('/coin_sales/list', array($this, 'coinSalesList'));
        $admin->get('/coin_sales/{coin_sale_id}', array($this, 'coinSalesDetail'));

        $admin->get('/stats', array($this, 'stats'));
        $admin->get('/stats_like', array($this, 'statsLike'));

        return $admin;
    }

    /*
     * Comment
     */
    public static function commentList(Request $req, Application $app)
    {
        $search_type = $req->get('search_type', null);
        $search_keyword = $req->get('search_keyword', null);
        $cur_page = $req->get('page', 0);

        $limit = 50;
        $offset = $cur_page * $limit;

        if ($search_type && $search_keyword) {
            if ($search_type == 'book_title') {
                $sql = <<<EOT
select pc.*, p.seq, b.title from part_comment pc
 left join (select id, b_id, seq from part) p on p.id = pc.p_id
 left join (select id, title from book) b on b.id = p.b_id
where b.title like '%{$search_keyword}%'
order by id desc limit {$offset}, {$limit}
EOT;
            } else if ($search_type == 'nickname') {
                $sql = <<<EOT
select pc.*, p.seq, b.title from part_comment pc
 left join (select id, b_id, seq from part) p on p.id = pc.p_id
 left join (select id, title from book) b on b.id = p.b_id
where pc.nickname like '%{$search_keyword}%'
order by id desc limit {$offset}, {$limit}
EOT;
            } else if ($search_type == 'ip_addr') {
                $search_keyword = ip2long($search_keyword);
                $sql = <<<EOT
select pc.*, p.seq, b.title from part_comment pc
 left join (select id, b_id, seq from part) p on p.id = pc.p_id
 left join (select id, title from book) b on b.id = p.b_id
where pc.ip = '{$search_keyword}'
order by id desc limit {$offset}, {$limit}
EOT;
            }
        } else {
            $sql = <<<EOT
select pc.*, p.seq, b.title from part_comment pc
 left join (select id, b_id, seq from part) p on p.id = pc.p_id
 left join (select id, title from book) b on b.id = p.b_id
order by id desc limit {$offset}, {$limit}
EOT;
        }

        $comments = $app['db']->fetchAll($sql);

        $app['twig']->addFilter(
            new \Twig_SimpleFilter('long2ip', function($ip) {
                return long2ip($ip);
            })
        );

        return $app['twig']->render(
            '/admin/comment_list.twig',
            array(
                'search_type' => $search_type,
                'search_keyword' => $search_keyword,
                'comments' => $comments,
                'cur_page' => $cur_page
            )
        );
    }

    public static function deleteComment(Request $req, Application $app, $c_id)
    {
        PartComment::delete($c_id);
        $app['session']->getFlashBag()->add('alert', array('info' => '댓글이 삭제되었습니다.'));
        $redirect_url = $req->headers->get('referer', '/admin/comment/list');
        return $app->redirect($redirect_url);
    }

    /*
     * Push
     */
    public static function pushDashboard(Request $req, Application $app)
    {
        return $app['twig']->render('/admin/dashboard.twig');
    }

    /**
     * PUSH NOTIFICATION
     */
    public static function pushNotifyUpdate(Request $req, Application $app)
    {
        $recipient = $req->get('recipient');
        $b_id = $req->get('b_id');
        $message = $req->get('message');

        if (empty($b_id) || empty($message)) {
            return 'not all required fields are filled';
        }

        $pick_result = PushDevicePicker::pickDevicesUsingInterestBook($app['db'], $recipient);
        $notification_android = AndroidPush::createPartUpdateNotification($b_id, $message);
        $notification_ios = IosPush::createPartUpdateNotification($b_id);

        $result = self::_push($pick_result, $message, $notification_ios, $notification_android);

        return $app->json($result);
    }

    public static function pushNotifyUrl(Request $req, Application $app)
    {
        $b_id = $req->get('b_id');
        $url = $req->get('url');
        $message = $req->get('message');

        if (empty($b_id) || empty($message)) {
            return 'not all required fields are filled';
        }

        $pick_result = PushDevicePicker::pickDevicesUsingInterestBook($app['db'], $b_id);
        $notification_android = AndroidPush::createUrlNotification($url, $message);
        $notification_ios = IosPush::createUrlNotification($url);

        $result = self::_push($pick_result, $message, $notification_ios, $notification_android);

        return $app->json($result);
    }

    public static function pushNotifyRemind(Request $req, Application $app)
    {
        $range_begin = $req->get('range_begin');
        $range_end = $req->get('range_end');
        $message = $req->get('message');
        if (empty($message)) {
            return 'not all required fields are filled';
        }

        $pick_result = PushDevicePicker::pickDevicesUsingIdRange($app['db'], $range_begin, $range_end);
        $notification_android = AndroidPush::createLaunchAppNotification($message);
        $notification_ios = IosPush::createLaunchAppNotification();

        $result = self::_push($pick_result, $message, $notification_ios, $notification_android);

        return $app->json($result);
    }

    public static function _push(PickDeviceResult $pick_result, $message, $notification_ios, $notification_android)
    {
        $result_android = AndroidPush::sendPush($pick_result->getAndroidDevices(), $notification_android);
        $result_ios = IosPush::sendPush($pick_result->getIosDevices(), $message, $notification_ios);

        return array(
            "Android" => $result_android,
            "iOS" => $result_ios
        );
    }

    /*
     * Notice
     */
    public static function addNotice(Application $app)
    {
        $app['db']->insert('notice', array('title' => '제목이 없습니다.', 'is_visible' => 0));
        $r = $app['db']->lastInsertId();
        $app['session']->getFlashBag()->add('alert', array('success' => '공지사항이 추가되었습니다.'));
        return $app->redirect('/admin/notice/' . $r);
    }

    public static function noticeList(Request $req, Application $app)
    {
        $notice_list = $app['db']->fetchAll('select * from notice');
        return $app['twig']->render('/admin/notice_list.twig', array('notice_list' => $notice_list));
    }

    public static function noticeDetail(Request $req, Application $app, $n_id)
    {
        $notice = $app['db']->fetchAssoc('select * from notice where id = ?', array($n_id));
        return $app['twig']->render('/admin/notice_detail.twig', array('notice' => $notice));
    }

    public static function deleteNotice(Request $req, Application $app, $n_id)
    {
        $app['db']->delete('notice', array('id' => $n_id));
        $app['session']->getFlashBag()->add('alert', array('info' => '공지사항이 삭제되었습니다.'));
        $redirect_url = $req->headers->get('referer', '/admin/notice/list');
        return $app->redirect($redirect_url);
    }

    public static function editNotice(Request $req, Application $app, $n_id)
    {
        $inputs = $req->request->all();

        $app['db']->update('notice', $inputs, array('id' => $n_id));

        $app['session']->getFlashBag()->add('alert', array('info' => '공지사항이 수정되었습니다.'));
        $redirect_url = $req->headers->get('referer', '/admin/notice/list');
        return $app->redirect($redirect_url);
    }

    /*
     * Banner
     */
    public static function addBanner(Application $app)
    {
        $app['db']->insert('banner', array('is_visible' => 0));
        $r = $app['db']->lastInsertId();

        $app['session']->getFlashBag()->add('alert', array('success' => '배너가 추가되었습니다.'));
        return $app->redirect('/admin/banner/' . $r);
    }

    public static function bannerList(Request $req, Application $app)
    {
        $banner_list = $app['db']->fetchAll('select * from banner');
        return $app['twig']->render('/admin/banner_list.twig', array('banner_list' => $banner_list));
    }

    public static function bannerDetail(Request $req, Application $app, $banner_id)
    {
        $banner = $app['db']->fetchAssoc('select * from banner where id = ?', array($banner_id));
        return $app['twig']->render('/admin/banner_detail.twig', array('banner' => $banner));
    }

    public static function deleteBanner(Request $req, Application $app, $banner_id)
    {
        $app['db']->delete('banner', array('id' => $banner_id));
        $app['session']->getFlashBag()->add('alert', array('info' => '배너가 삭제되었습니다.'));
        return $app->redirect('/admin/banner/list');
    }

    public static function editBanner(Request $req, Application $app, $banner_id)
    {
        $inputs = $req->request->all();

        $app['db']->update('banner', $inputs, array('id' => $banner_id));

        $app['session']->getFlashBag()->add('alert', array('info' => '배너가 수정되었습니다.'));
        $redirect_url = $req->headers->get('referer', '/admin/banner/list');
        return $app->redirect($redirect_url);
    }

    /*
     * In App Billing Product
     */
    public static function addInAppProduct(Application $app)
    {
        $iab_id = InAppBilling::createInAppProduct();

        $app['session']->getFlashBag()->add('alert', array('success' => '인앱 상품이 추가되었습니다.'));
        return $app->redirect('/admin/inapp_product/' . $iab_id);
    }

    public static function inAppProductList(Application $app)
    {
        $inapp_list = InAppBilling::getInAppProductListWithTotalSales();
        return $app['twig']->render('/admin/inapp_product_list.twig', array('inapp_list' => $inapp_list));
    }

    public static function inAppProductDetail(Request $req, Application $app, $iab_id)
    {
        $inapp_product = InAppBilling::getInAppProduct($iab_id);
        return $app['twig']->render('/admin/inapp_product_detail.twig', array('inapp_product' => $inapp_product));
    }

    public static function deleteInAppProduct(Request $req, Application $app, $iab_id)
    {
        InAppBilling::deleteInAppProduct($iab_id);
        $app['session']->getFlashBag()->add('alert', array('info' => '인앱 상품이 삭제되었습니다.'));
        return $app->json(array('success' => true));
    }

    public static function editInAppProduct(Request $req, Application $app, $iab_id)
    {
        $inputs = $req->request->all();

        InAppBilling::updateInAppProduct($iab_id, $inputs);
        $app['session']->getFlashBag()->add('alert', array('info' => '인앱 상품 정보가 수정되었습니다.'));
        return $app->redirect('/admin/inapp_product/' . $iab_id);
    }

    /*
     * Coin Sales
     */
    public static function coinSalesList(Request $req, Application $app)
    {
        $cur_page = $req->get('page', 0);

        $limit = 50;
        $offset = $cur_page * $limit;

        $begin_date = $req->get('begin_date');
        $end_date = $req->get('end_date');
        $search_date = array(
            'begin_date' => $begin_date,
            'end_date' => $end_date
        );

        //TODO: 임시. 코인 관련 통계 페이지 제작 후, 아래 Coin Sales Stat 관련 코드 제거.
        $sql = <<<EOT
select date(a.purchase_time) purchase_date, count(distinct a.u_id) user_count, sum(b.coin_amount) coin_amount, sum(b.bonus_coin_amount) bonus_coin_amount, sum(b.price) total_sales from inapp_history a
 join inapp_products b on a.sku = b.sku
group by date(a.purchase_time)
EOT;
        $coin_sales_stats = $app['db']->fetchAll($sql);
        foreach ($coin_sales_stats as &$css) {
            $css['total_coin_amount'] = $css['coin_amount'] + $css['bonus_coin_amount'];
        }

        $coin_sales = InAppBilling::getInAppBillingSalesListByOffsetAndSize($offset, $limit, $begin_date, $end_date);
        return $app['twig']->render(
            '/admin/coin_sales_list.twig',
            array(
                'search_date' => $search_date,
                'coin_sales_stats' => $coin_sales_stats,
                'coin_sales' => $coin_sales,
                'cur_page' => $cur_page
            )
        );
    }

    public static function coinSalesDetail(Request $req, Application $app, $coin_sale_id)
    {
        $coin_sale = InAppBilling::getInAppBillingSalesDetail($coin_sale_id);
        return $app['twig']->render('/admin/coin_sales_detail.twig', array('coin_sale' => $coin_sale));
    }

    /*
     * Stats
     */
    public static function stats(Application $app)
    {
        // 기기등록 통계
        $total_registered = $app['db']->fetchColumn('select count(*) from push_devices');
        $sql = <<<EOT
select date, A.num_registered_ios ios, B.num_registered_android android, (A.num_registered_ios + B.num_registered_android) total from
    (select date(reg_date) date, count(*) num_registered_ios from push_devices where datediff(now(), reg_date) < 20 and platform = 'iOS' group by date) A
  natural left join
    (select date(reg_date) date, count(*) num_registered_android from push_devices where datediff(now(), reg_date) < 20 and platform = 'android' group by date) B
  order by date desc
EOT;
        $register_stat = $app['db']->fetchAll($sql);

        // 다운로드 통계
        $total_downloaded = $app['db']->fetchColumn('select count(*) from stat_download');

        $sql = <<<EOT
select part.id p_id, b.title b_title, part.title p_title, download_count from part
 join (select p_id, count(p_id) download_count from stat_download
 		group by p_id order by count(p_id) desc limit 20) stat on part.id = stat.p_id
 left join (select id, title from book) b on b.id = part.b_id
 order by download_count desc
EOT;
        $download_stat = $app['db']->fetchAll($sql);

        // 책별 알림 설정수
        $sql = <<<EOT
select A.title,
  ifnull(interested_d6, 0) interested_d6, ifnull(interested_d5, 0) interested_d5, ifnull(interested_d4, 0) interested_d4, ifnull(interested_d3, 0) interested_d3,
  ifnull(interested_d2, 0) interested_d2, ifnull(interested_d1, 0) interested_d1, ifnull(interested_d0, 0) interested_d0,
  (ifnull(interested_d6, 0) + ifnull(interested_d5, 0) + ifnull(interested_d4, 0) + ifnull(interested_d3, 0) + ifnull(interested_d2, 0) + ifnull(interested_d1, 0) + ifnull(interested_d0, 0)) as interested_sum,
  interested_total from book A
    left join (select b_id, count(b_id) interested_d6 from user_interest where datediff(now(), `timestamp`) = 6 group by b_id, date(`timestamp`)) D6 on A.id = D6.b_id
    left join (select b_id, count(b_id) interested_d5 from user_interest where datediff(now(), `timestamp`) = 5 group by b_id, date(`timestamp`)) D5 on A.id = D5.b_id
    left join (select b_id, count(b_id) interested_d4 from user_interest where datediff(now(), `timestamp`) = 4 group by b_id, date(`timestamp`)) D4 on A.id = D4.b_id
    left join (select b_id, count(b_id) interested_d3 from user_interest where datediff(now(), `timestamp`) = 3 group by b_id, date(`timestamp`)) D3 on A.id = D3.b_id
    left join (select b_id, count(b_id) interested_d2 from user_interest where datediff(now(), `timestamp`) = 2 group by b_id, date(`timestamp`)) D2 on A.id = D2.b_id
    left join (select b_id, count(b_id) interested_d1 from user_interest where datediff(now(), `timestamp`) = 1 group by b_id, date(`timestamp`)) D1 on A.id = D1.b_id
    left join (select b_id, count(b_id) interested_d0 from user_interest where datediff(now(), `timestamp`) = 0 group by b_id, date(`timestamp`)) D0 on A.id = D0.b_id
    left join (select b_id, count(b_id) interested_total from user_interest group by b_id) TOTAL on A.id = TOTAL.b_id
order by title
EOT;
        $interest_stat = $app['db']->fetchAll($sql);

        // 스토리+ 책 다운로드 통계
        $sql = <<<EOT
select storyplusbook.title, ifnull(download_count, 0) download_count from storyplusbook
	left join (select storyplusbook_id, count(storyplusbook_id) download_count from stat_download_storyplusbook group by storyplusbook_id) A
	on storyplusbook.id = A.storyplusbook_id
	order by download_count desc
EOT;
        $download_stat_storyplusbook = $app['db']->fetchAll($sql);

        // 댓글 통계
        $sql = <<<EOT
select b.title book_title, seq, part_title, num_comment from book b
 join (select p.b_id, p.seq, p.title part_title, count(*) num_comment from part_comment c join part p on p.id = c.p_id group by p_id) c on c.b_id = b.id 
EOT;

        $most_comment_parts = $app['db']->fetchAll($sql . 'order by num_comment desc limit 10');
        $least_comment_parts = $app['db']->fetchAll($sql . 'order by num_comment limit 10');

        return $app['twig']->render(
            '/admin/stats.twig',
            compact(
                'total_registered',
                'register_stat',
                'total_downloaded',
                'download_stat',
                'interest_stat',
                'download_stat_storyplusbook',
                'most_comment_parts',
                'least_comment_parts'
            )
        );
    }

    public static function statsLike(Application $app, Request $req)
    {
        $begin_date = $req->get('begin_date');
        $end_date = $req->get('end_date');

        $twig_var = array();
        $twig_var['begin_date'] = $begin_date;

        if ($end_date) {
            $sql_interests = <<<EOT
    select a.b_id, b.title, count(*) count from user_interest a
    join book b on a.b_id = b.id
    where a.timestamp <= ?
    group by a.b_id
EOT;

            $sql_likes = <<<EOT
    select part.b_id, book.title, count(distinct part.id) count, sum(M.CNT) sum, sum(M.CNT) / count(distinct part.id) avg from
    (
      select p_id, count(device_id) CNT from user_part_like
      where timestamp <= ?
      group by p_id
    ) M join part on M.p_id = part.id
    left outer join book on part.b_id = book.id
    group by part.b_id;
EOT;

            $twig_var['end_date'] = $end_date;
            $twig_var['interests_per_book'] = $app['db']->fetchAll($sql_interests, array($end_date));
            $twig_var['likes_per_book'] = $app['db']->fetchAll($sql_likes, array($end_date));
        } else {
            $twig_var['end_date'] = date('Y-m-d');
            $twig_var['interests_per_book'] = null;
            $twig_var['likes_per_book'] = null;
        }

        return $app['twig']->render(
            '/admin/stats_like.twig',
            $twig_var
        );
    }
}