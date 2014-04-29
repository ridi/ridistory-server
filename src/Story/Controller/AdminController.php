<?php
namespace Story\Controller;

use Doctrine\DBAL\Connection;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Story\Model\Book;
use Story\Model\Buyer;
use Story\Model\CoinProduct;
use Story\Model\InAppBilling;
use Story\Model\PartComment;
use Story\Model\RidiCashBilling;
use Story\Model\TestUser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\Security\Acl\Exception\Exception;

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

        $admin->get('/inapp_sales/list', array($this, 'inAppSalesList'));
        $admin->get('/inapp_sales/{iab_sale_id}', array($this, 'inAppSalesDetail'));
        $admin->post('/inapp_sales/{id}/refund', array($this, 'refundCoinSales'));

        $admin->get('/ridicash_sales/list', array($this, 'ridiCashSalesList'));
        $admin->get('/ridicash_sales/{rcb_sale_id}', array($this, 'ridiCashSalesDetail'));
        $admin->post('/ridicash_sales/{id}/refund', array($this, 'refundCoinSales'));

        $admin->get('/stats', array($this, 'stats'));
        $admin->get('/stats_like', array($this, 'statsLike'));

        $admin->get('/stats_kpi/buy_coin', array($this, 'statsKpiBuyCoin'));
        $admin->get('/stats_kpi/buy_coin/detail', array($this, 'statsKpiBuyCoinDetail'));
        $admin->get('/stats_kpi/use_coin', array($this, 'statsKpiUseCoin'));
        $admin->get('/stats_kpi/use_coin/detail', array($this, 'statsKpiUseCoinDetail'));

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
where b.title like ?
order by id desc
EOT;
                $bind = array('%' . $search_keyword . '%');
            } else if ($search_type == 'nickname') {
                $sql = <<<EOT
select pc.*, p.seq, b.title from part_comment pc
 left join (select id, b_id, seq from part) p on p.id = pc.p_id
 left join (select id, title from book) b on b.id = p.b_id
where pc.nickname like ?
order by id desc
EOT;
                $bind = array('%' . $search_keyword . '%');
            } else if ($search_type == 'ip_addr') {
                $ip_addr = ip2long($search_keyword);
                $sql = <<<EOT
select pc.*, p.seq, b.title from part_comment pc
 left join (select id, b_id, seq from part) p on p.id = pc.p_id
 left join (select id, title from book) b on b.id = p.b_id
where pc.ip = ?
order by id desc
EOT;
                $bind = array($ip_addr);
            } else {
                $sql = null;
                $bind = null;
            }

            $comments = $app['db']->fetchAll($sql, $bind);
        } else {
            $sql = <<<EOT
select pc.*, p.seq, b.title from part_comment pc
 left join (select id, b_id, seq from part) p on p.id = pc.p_id
 left join (select id, title from book) b on b.id = p.b_id
order by id desc limit ?, ?
EOT;
            global $app;
            $stmt = $app['db']->executeQuery($sql,
                array($offset, $limit),
                array(\PDO::PARAM_INT, \PDO::PARAM_INT)
            );
            $comments = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

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
     * InAppBilling Coin Sales
     */
    public static function inAppSalesList(Request $req, Application $app)
    {
        $search_type = $req->get('search_type', null);
        $search_keyword = $req->get('search_keyword', null);
        $cur_page = $req->get('page', 0);

        $limit = 50;
        $offset = $cur_page * $limit;

        if ($search_type && $search_keyword) {
            $inapp_sales = InAppBilling::getInAppBillingSalesListBySearchTypeAndKeyword($search_type, $search_keyword);
        } else {
            $inapp_sales = InAppBilling::getInAppBillingSalesListByOffsetAndSize($offset, $limit);
        }

        return $app['twig']->render(
            '/admin/inapp_sales_list.twig',
            array(
                'search_type' => $search_type,
                'search_keyword' => $search_keyword,
                'inapp_sales' => $inapp_sales,
                'cur_page' => $cur_page
            )
        );
    }

    public static function inAppSalesDetail(Request $req, Application $app, $iab_sale_id)
    {
        $inapp_sale = InAppBilling::getInAppBillingSalesDetail($iab_sale_id);
        return $app['twig']->render('/admin/inapp_sales_detail.twig', array('inapp_sale' => $inapp_sale));
    }

    public static function refundCoinSales(Request $req, Application $app, $id)
    {
        $payment = $req->get('payment', null);
        if ($payment == CoinProduct::TYPE_INAPP) {
            $coin_sales = InAppBilling::getInAppBillingSalesDetail($id);
        } else if ($payment == CoinProduct::TYPE_RIDICASH) {
            $coin_sales = RidiCashBilling::getRidiCashBillingSalesDetail($id);
        } else {
            return $app->json(array('success' => false));
        }

        if ($coin_sales['status'] == InAppBilling::STATUS_OK) {
            $user = Buyer::get($coin_sales['u_id']);
            $product = CoinProduct::getCoinProductBySkuAndType($coin_sales['sku'], $payment);

            $user_coin_balance = $user['coin_balance'];
            $refund_coin_amount = $product['coin_amount'] + $product['bonus_coin_amount'];

            // 환불해줄 코인보다, 사용자의 잔여 코인이 많은지 여부 확인
            if ($user_coin_balance >= $refund_coin_amount) {
                // 리디캐시 결제일 경우에, 리디북스의 결제 취소 API 호출
                if ($payment == CoinProduct::TYPE_RIDICASH) {
                    //TODO: 리디북스 결제 취소 API 호출
                }

                // 트랜잭션 시작
                $app['db']->beginTransaction();
                try {
                    $r = Buyer::useCoin($user['id'], $refund_coin_amount, Buyer::COIN_SOURCE_OUT_COIN_REFUND, null);
                    if ($r) {
                        if ($payment == CoinProduct::TYPE_INAPP) {
                            $r = InAppBilling::changeInAppBillingStatus($id, InAppBilling::STATUS_REFUNDED);
                        } else {
                            $r = RidiCashBilling::changeRidiCashBillingStatusAndTidIfNotNull($id, null, InAppBilling::STATUS_REFUNDED);
                        }

                        if ($r) {
                            $app['db']->commit();
                            $app['session']->getFlashBag()->add('alert', array('success' => '환불되었습니다. (감소 코인: ' . $refund_coin_amount . '개 / 회원 잔여 코인: ' . $user_coin_balance . '개 -> ' . ($user_coin_balance - $refund_coin_amount) . '개)'));
                        } else {
                            throw new Exception('환불 도중 오류가 발생했습니다. (상태 변경 DB 오류)');
                        }
                    } else {
                        throw new Exception('환불 도중 오류가 발생했습니다. (코인 감소 DB 오류)');
                    }
                } catch (\Exception $e) {
                    $app['db']->rollback();
                    $app['session']->getFlashBag()->add('alert', array('error' => $e->getMessage()));
                }
            } else {
                $app['session']->getFlashBag()->add('alert', array('error' => '회원의 잔여 코인이 구입시 충전된 코인보다 적어 환불할 수 없습니다. (현재 회원 잔여 코인: ' . $user_coin_balance . '개)'));
            }
        } else {
            $app['session']->getFlashBag()->add('alert', array('error' => '이미 ' . $coin_sales['refunded_time'] . ' 에 환불 처리 되었습니다.'));
        }

        return $app->json(array('success' => true));
    }

    /*
     * RidiCash Coin Sales
     */
    public static function ridiCashSalesList(Request $req, Application $app)
    {
        $search_type = $req->get('search_type', null);
        $search_keyword = $req->get('search_keyword', null);
        $cur_page = $req->get('page', 0);

        $limit = 50;
        $offset = $cur_page * $limit;

        if ($search_type && $search_keyword) {
            $ridicash_sales = RidiCashBilling::getRidiCashBillingSalesListBySearchTypeAndKeyword($search_type, $search_keyword);
        } else {
            $ridicash_sales = RidiCashBilling::getRidiCashBillingSalesListByOffsetAndSize($offset, $limit);
        }

        return $app['twig']->render(
            '/admin/ridicash_sales_list.twig',
            array(
                'search_type' => $search_type,
                'search_keyword' => $search_keyword,
                'ridicash_sales' => $ridicash_sales,
                'cur_page' => $cur_page
            )
        );
    }

    public static function ridiCashSalesDetail(Request $req, Application $app, $rcb_sale_id)
    {
        $ridicash_sale = RidiCashBilling::getRidiCashBillingSalesDetail($rcb_sale_id);
        return $app['twig']->render('/admin/ridicash_sales_detail.twig', array('ridicash_sale' => $ridicash_sale));
    }

    /*
     * Stats
     */
    public static function stats(Application $app)
    {
        // 기기등록 통계
        $total_registered = $app['db']->fetchColumn('select count(*) from push_devices');
        $sql = <<<EOT
select date, ifnull(A.num_registered_ios, 0) ios, ifnull(B.num_registered_android, 0) android, (ifnull(A.num_registered_ios, 0) + ifnull(B.num_registered_android, 0)) total from
    (select date(reg_date) date, count(*) num_registered_ios from push_devices where datediff(now(), reg_date) < 20 and platform = 'iOS' group by date) A
  natural right outer join
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

    /*
     * Stats for KPI
     */
    public static function statsKpiBuyCoin(Application $app, Request $req)
    {
        $begin_date = $req->get('begin_date');
        $end_date = $req->get('end_date');

        $inapp_buy_coins = null;
        $ridicash_buy_coins = null;

        if ($begin_date && $end_date) {
            $inapp_buy_sql = <<<EOT
select date(ih.purchase_time) purchase_date, count(distinct ih.u_id) user_count, sum(ip.coin_amount) coin_amount, sum(ip.bonus_coin_amount) bonus_coin_amount, 0 refunded_total_coin_amount, sum(ip.price) total_buy_sales, 0 total_refunded_sales from inapp_history ih
 left join (select * from inapp_products where type = 'GOOGLE') ip on ih.sku = ip.sku
where ih.status != 'PENDING' and date(ih.purchase_time) >= ? and date(ih.purchase_time) <= ?
EOT;
            $ridicash_buy_sql = <<<EOT
select date(rh.purchase_time) purchase_date, count(distinct rh.u_id) user_count, sum(ip.coin_amount) coin_amount, sum(ip.bonus_coin_amount) bonus_coin_amount, 0 refunded_total_coin_amount, sum(ip.price) total_buy_sales, 0 total_refunded_sales from ridicash_history rh
 left join (select * from inapp_products where type = 'RIDICASH') ip on rh.sku = ip.sku
where rh.status != 'PENDING' and date(rh.purchase_time) >= ? and date(rh.purchase_time) <= ?
EOT;

            $inapp_refunded_sql = <<<EOT
select date(ih.refunded_time) refunded_date, sum(ip.coin_amount+ip.bonus_coin_amount) refunded_total_coin_amount, sum(ip.price) total_refunded_sales from inapp_history ih
 left join (select * from inapp_products where type = 'GOOGLE') ip on ih.sku = ip.sku
where ih.status = 'REFUNDED' and date(ih.refunded_time) >= ? and date(ih.refunded_time) <= ?
EOT;
            $ridicash_refunded_sql = <<<EOT
select date(rh.refunded_time) refunded_date, sum(ip.coin_amount+ip.bonus_coin_amount) refunded_total_coin_amount, sum(ip.price) total_refunded_sales from ridicash_history rh
 left join (select * from inapp_products where type = 'RIDICASH') ip on rh.sku = ip.sku
where rh.status = 'REFUNDED' and date(rh.refunded_time) >= ? and date(rh.refunded_time) <= ?
EOT;

            $test_users = TestUser::getConcatUidList(true);
            if ($test_users) {
                $inapp_buy_sql .= ' and ih.u_id not in (' . $test_users . ')';
                $ridicash_buy_sql .= ' and rh.u_id not in (' . $test_users . ')';
                $inapp_refunded_sql .= ' and ih.u_id not in (' . $test_users . ')';
                $ridicash_refunded_sql .= ' and rh.u_id not in (' . $test_users . ')';
            }
            $inapp_buy_sql .= ' group by date(ih.purchase_time)';
            $ridicash_buy_sql .= ' group by date(rh.purchase_time)';
            $inapp_refunded_sql .= ' group by date(ih.refunded_time)';
            $ridicash_refunded_sql .= ' group by date(rh.refunded_time)';

            $bind = array($begin_date, $end_date);

            $inapp_buy_coins = $app['db']->fetchAll($inapp_buy_sql, $bind);
            $ridicash_buy_coins = $app['db']->fetchAll($ridicash_buy_sql, $bind);

            $inapp_refunded_coins = $app['db']->fetchAll($inapp_refunded_sql, $bind);
            $ridicash_refunded_coins = $app['db']->fetchAll($ridicash_refunded_sql, $bind);

            foreach ($inapp_refunded_coins as $key => $irc) {
                foreach ($inapp_buy_coins as &$ibc) {
                    if ($irc['refunded_date'] == $ibc['purchase_date']) {
                        $ibc['refunded_total_coin_amount'] = $irc['refunded_total_coin_amount'];
                        $ibc['total_refunded_sales'] = $irc['total_refunded_sales'];
                        unset($inapp_refunded_coins[$key]);
                        break;
                    }
                }
            }
            foreach ($ridicash_refunded_coins as $key => $rrc) {
                foreach ($ridicash_buy_coins as &$rbc) {
                    if ($rrc['refunded_date'] == $rbc['purchase_date']) {
                        $rbc['refunded_total_coin_amount'] = $rrc['refunded_total_coin_amount'];
                        $rbc['total_refunded_sales'] = $rrc['total_refunded_sales'];
                        unset($ridicash_refunded_coins[$key]);
                        break;
                    }
                }
            }

            if (count($inapp_refunded_coins) > 0) {
                foreach ($inapp_refunded_coins as $key => $irc) {
                    array_push($inapp_buy_coins,
                        array(
                            'purchase_date' => $irc['refunded_date'],
                            'user_count' => 0,
                            'coin_amount' => 0,
                            'bonus_coin_amount' => 0,
                            'refunded_total_coin_amount' => $irc['refunded_total_coin_amount'],
                            'total_buy_sales' => 0,
                            'total_refunded_sales' => $irc['total_refunded_sales']
                        )
                    );
                    unset($inapp_refunded_coins[$key]);
                }
            }
            if (count($ridicash_refunded_coins) > 0) {
                foreach ($ridicash_refunded_coins as $key => $rrc) {
                    array_push($ridicash_buy_coins,
                        array(
                            'purchase_date' => $rrc['refunded_date'],
                            'user_count' => 0,
                            'coin_amount' => 0,
                            'bonus_coin_amount' => 0,
                            'refunded_total_coin_amount' => $rrc['refunded_total_coin_amount'],
                            'total_buy_sales' => 0,
                            'total_refunded_sales' => $rrc['total_refunded_sales']
                        )
                    );
                    unset($ridicash_refunded_coins[$key]);
                }
            }

            usort($inapp_buy_coins, function ($a, $b) {
                    $a_time = strtotime($a['purchase_date']);
                    $b_time = strtotime($b['purchase_date']);

                    if ($a_time == $b_time) {
                        return 0;
                    }

                    return ($a_time < $b_time ? -1 : 1);
                }
            );
            usort($ridicash_buy_coins, function ($a, $b) {
                    $a_time = strtotime($a['purchase_date']);
                    $b_time = strtotime($b['purchase_date']);

                    if ($a_time == $b_time) {
                        return 0;
                    }

                    return ($a_time < $b_time ? -1 : 1);
                }
            );
        } else {
            if (!$begin_date) {
                $begin_date = date('Y-m-01');
            }
            if (!$end_date) {
                $year = date('Y');
                $month = date('m');
                $last_day = date('t', mktime(0, 0, 0, $month, 1, $year));
                $end_date = $year . '-' . $month . '-' . $last_day;
            }
        }

        return $app['twig']->render(
            '/admin/stats_kpi/buy_coin.twig',
            array(
                'begin_date' => $begin_date,
                'end_date' => $end_date,
                'inapp_buy_coins' => $inapp_buy_coins,
                'ridicash_buy_coins' => $ridicash_buy_coins
            )
        );
    }

    public static function statsKpiBuyCoinDetail(Application $app, Request $req)
    {
        $begin_date = $req->get('begin_date');
        $end_date = $req->get('end_date');

        $inapp_buy_coins = null;
        $ridicash_buy_coins = null;

        if ($begin_date && $end_date) {
            $inapp_buy_sql = <<<EOT
select date(ih.purchase_time) purchase_date, sum(if(ih.sku='coin_29', 1, 0)) coin_29, sum(if(ih.sku='coin_99', 1, 0)) coin_99, sum(if(ih.sku='coin_139', 1, 0)) coin_139, sum(if(ih.sku='coin_349', 1, 0)) coin_349, sum(ip.coin_amount + ip.bonus_coin_amount) buy_coin_amount, 0 refunded_coin_amount, sum(ip.price) total_buy_sales, 0 total_refunded_sales from inapp_history ih
 left join (select * from inapp_products where type = 'GOOGLE') ip on ih.sku = ip.sku
where ih.status != 'PENDING' and date(ih.purchase_time) >= ? and date(ih.purchase_time) <= ?
EOT;
            $ridicash_buy_sql = <<<EOT
select date(rh.purchase_time) purchase_date, sum(if(rh.sku='coin_29', 1, 0)) coin_29, sum(if(rh.sku='coin_99', 1, 0)) coin_99, sum(if(rh.sku='coin_139', 1, 0)) coin_139, sum(if(rh.sku='coin_349', 1, 0)) coin_349, sum(ip.coin_amount + ip.bonus_coin_amount) buy_coin_amount, 0 refunded_coin_amount, sum(ip.price) total_buy_sales, 0 total_refunded_sales from ridicash_history rh
 left join (select * from inapp_products where type = 'RIDICASH') ip on rh.sku = ip.sku
where rh.status != 'PENDING' and date(rh.purchase_time) >= ? and date(rh.purchase_time) <= ?
EOT;

            $inapp_refunded_sql = <<<EOT
select date(ih.refunded_time) refunded_date, sum(if(ih.sku='coin_29', 1, 0)) coin_29, sum(if(ih.sku='coin_99', 1, 0)) coin_99, sum(if(ih.sku='coin_139', 1, 0)) coin_139, sum(if(ih.sku='coin_349', 1, 0)) coin_349, sum(ip.coin_amount + ip.bonus_coin_amount) refunded_coin_amount, sum(ip.price) total_refunded_sales from inapp_history ih
 left join (select * from inapp_products where type = 'GOOGLE') ip on ih.sku = ip.sku
where ih.status = 'REFUNDED' and date(ih.refunded_time) >= ? and date(ih.refunded_time) <= ?
EOT;
            $ridicash_refunded_sql = <<<EOT
select date(rh.refunded_time) refunded_date, sum(if(rh.sku='coin_29', 1, 0)) coin_29, sum(if(rh.sku='coin_99', 1, 0)) coin_99, sum(if(rh.sku='coin_139', 1, 0)) coin_139, sum(if(rh.sku='coin_349', 1, 0)) coin_349, sum(ip.coin_amount + ip.bonus_coin_amount) refunded_coin_amount, sum(ip.price) total_refunded_sales from ridicash_history rh
 left join (select * from inapp_products where type = 'RIDICASH') ip on rh.sku = ip.sku
where rh.status = 'REFUNDED' and date(rh.refunded_time) >= ? and date(rh.refunded_time) <= ?
EOT;


            $test_users = TestUser::getConcatUidList(true);
            if ($test_users) {
                $inapp_buy_sql .= ' and ih.u_id not in (' . $test_users . ')';
                $ridicash_buy_sql .= ' and rh.u_id not in (' . $test_users . ')';
                $inapp_refunded_sql .= ' and ih.u_id not in (' . $test_users . ')';
                $ridicash_refunded_sql .= ' and rh.u_id not in (' . $test_users . ')';
            }
            $inapp_buy_sql .= ' group by date(ih.purchase_time)';
            $ridicash_buy_sql .= ' group by date(rh.purchase_time)';
            $inapp_refunded_sql .= ' group by date(ih.refunded_time)';
            $ridicash_refunded_sql .= ' group by date(rh.refunded_time)';

            $bind = array($begin_date, $end_date);

            $inapp_buy_coins = $app['db']->fetchAll($inapp_buy_sql, $bind);
            $ridicash_buy_coins = $app['db']->fetchAll($ridicash_buy_sql, $bind);
            $inapp_refunded_coins = $app['db']->fetchAll($inapp_refunded_sql, $bind);
            $ridicash_refunded_coins = $app['db']->fetchAll($ridicash_refunded_sql, $bind);

            foreach ($inapp_refunded_coins as $key => $irc) {
                foreach ($inapp_buy_coins as &$ibc) {
                    if ($irc['refunded_date'] == $ibc['purchase_date']) {
                        $ibc['coin_29'] -= $irc['coin_29'];
                        $ibc['coin_99'] -= $irc['coin_99'];
                        $ibc['coin_139'] -= $irc['coin_139'];
                        $ibc['coin_349'] -= $irc['coin_349'];
                        $ibc['refunded_coin_amount'] = $irc['refunded_coin_amount'];
                        $ibc['total_refunded_sales'] = $irc['total_refunded_sales'];
                        unset($inapp_refunded_coins[$key]);
                        break;
                    }
                }
            }
            foreach ($ridicash_refunded_coins as $key => $rrc) {
                foreach ($ridicash_buy_coins as &$rbc) {
                    if ($rrc['refunded_date'] == $rbc['purchase_date']) {
                        $rbc['coin_29'] -= $rrc['coin_29'];
                        $rbc['coin_99'] -= $rrc['coin_99'];
                        $rbc['coin_139'] -= $rrc['coin_139'];
                        $rbc['coin_349'] -= $rrc['coin_349'];
                        $rbc['refunded_coin_amount'] = $rrc['refunded_coin_amount'];
                        $rbc['total_refunded_sales'] = $rrc['total_refunded_sales'];
                        unset($ridicash_refunded_coins[$key]);
                        break;
                    }
                }
            }

            if (count($inapp_refunded_coins) > 0) {
                foreach ($inapp_refunded_coins as $key => $irc) {
                    array_push($inapp_buy_coins,
                        array(
                            'purchase_date' => $irc['refunded_date'],
                            'coin_29' => -$irc['coin_29'],
                            'coin_99' => -$irc['coin_99'],
                            'coin_139' => -$irc['coin_139'],
                            'coin_349' => -$irc['coin_349'],
                            'buy_coin_amount' => 0,
                            'refunded_coin_amount' => $irc['refunded_coin_amount'],
                            'total_buy_sales' => 0,
                            'total_refunded_sales' => $irc['total_refunded_sales']
                        )
                    );
                    unset($inapp_refunded_coins[$key]);
                }
            }
            if (count($ridicash_refunded_coins) > 0) {
                foreach ($ridicash_refunded_coins as $key => $rrc) {
                    array_push($ridicash_buy_coins,
                        array(
                            'purchase_date' => $rrc['refunded_date'],
                            'coin_29' => -$rrc['coin_29'],
                            'coin_99' => -$rrc['coin_99'],
                            'coin_139' => -$rrc['coin_139'],
                            'coin_349' => -$rrc['coin_349'],
                            'buy_coin_amount' => 0,
                            'refunded_coin_amount' => $rrc['refunded_coin_amount'],
                            'total_buy_sales' => 0,
                            'total_refunded_sales' => $rrc['total_refunded_sales']
                        )
                    );
                    unset($ridicash_refunded_coins[$key]);
                }
            }

            usort($inapp_buy_coins, function ($a, $b) {
                    $a_time = strtotime($a['purchase_date']);
                    $b_time = strtotime($b['purchase_date']);

                    if ($a_time == $b_time) {
                        return 0;
                    }

                    return ($a_time < $b_time ? -1 : 1);
                }
            );
            usort($ridicash_buy_coins, function ($a, $b) {
                    $a_time = strtotime($a['purchase_date']);
                    $b_time = strtotime($b['purchase_date']);

                    if ($a_time == $b_time) {
                        return 0;
                    }

                    return ($a_time < $b_time ? -1 : 1);
                }
            );
        } else {
            if (!$begin_date) {
                $begin_date = date('Y-m-01');
            }
            if (!$end_date) {
                $year = date('Y');
                $month = date('m');
                $last_day = date('t', mktime(0, 0, 0, $month, 1, $year));
                $end_date = $year . '-' . $month . '-' . $last_day;
            }
        }

        return $app['twig']->render(
            '/admin/stats_kpi/buy_coin_detail.twig',
            array(
                'begin_date' => $begin_date,
                'end_date' => $end_date,
                'inapp_buy_coins' => $inapp_buy_coins,
                'ridicash_buy_coins' => $ridicash_buy_coins
            )
        );
    }

    public static function statsKpiUseCoin(Application $app, Request $req)
    {
        $begin_date = $req->get('begin_date');
        $end_date = $req->get('end_date');

        $use_coins = null;

        if ($begin_date && $end_date) {
            $sql = <<<EOT
select date(timestamp) use_date, count(distinct u_id) user_count, abs(sum(amount)) used_coin_amount from coin_history
where source = 'OUT_BUY_PART' and date(timestamp) >= ? and date(timestamp) <= ?
EOT;
            $test_users = TestUser::getConcatUidList(true);
            if ($test_users) {
                $sql .= ' and u_id not in (' . $test_users . ')';
            }
            $sql .= ' group by date(timestamp)';

            $use_coins = $app['db']->fetchAll($sql, array($begin_date, $end_date));
        } else {
            if (!$begin_date) {
                $begin_date = date('Y-m-01');
            }
            if (!$end_date) {
                $year = date('Y');
                $month = date('m');
                $last_day = date('t', mktime(0, 0, 0, $month, 1, $year));
                $end_date = $year . '-' . $month . '-' . $last_day;
            }
        }

        return $app['twig']->render(
            '/admin/stats_kpi/use_coin.twig',
            array(
                'begin_date' => $begin_date,
                'end_date' => $end_date,
                'use_coins' => $use_coins
            )
        );
    }

    public static function statsKpiUseCoinDetail(Application $app, Request $req)
    {
        $bid_list = trim($req->get('bid_list'));
        $begin_date = $req->get('begin_date');
        $end_date = $req->get('end_date');

        $books = null;
        $use_coins = null;

        if ($begin_date && $end_date && $bid_list) {
            $b_ids = explode(PHP_EOL, $bid_list);  // 조회할 책 목록
            foreach ($b_ids as &$b_id) {
                $b_id = trim($b_id);
            }

            $books = Book::getListByIds($b_ids, false);

            if (!empty($books)) {
                $sql = <<<EOT
select date(timestamp) use_date
EOT;
                $i = 0;
                foreach ($books as &$book) {
                    $sql .= ', sum(if(b_id=' . $book['id'] . ', coin_amount, 0)) b_' . $i++;
                }
                $sql .= <<<EOT
 from
(
 select ph.*, b_id from purchase_history ph
 join (
  select b.id b_id, p.id p_id from book b
   join part p on b.id = p.b_id
  where b.id in (?)
 ) b_info on ph.p_id = b_info.p_id
) use_info
where date(timestamp) >= ? and date(timestamp) <= ?
EOT;
                $test_users = TestUser::getConcatUidList(true);
                if ($test_users) {
                    $sql .= ' and u_id not in (' . $test_users . ')';
                }
                $sql .= ' group by date(timestamp)';

                $stmt = $app['db']->executeQuery(
                    $sql,
                    array($b_ids, $begin_date, $end_date),
                    array(Connection::PARAM_INT_ARRAY, \PDO::PARAM_STR, \PDO::PARAM_STR)
                );

                $use_coins = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }
        } else {
            if (!$begin_date) {
                $begin_date = date('Y-m-01');
            }
            if (!$end_date) {
                $year = date('Y');
                $month = date('m');
                $last_day = date('t', mktime(0, 0, 0, $month, 1, $year));
                $end_date = $year . '-' . $month . '-' . $last_day;
            }
        }

        return $app['twig']->render(
            '/admin/stats_kpi/use_coin_detail.twig',
            array(
                'bid_list' => $bid_list,
                'begin_date' => $begin_date,
                'end_date' => $end_date,
                'books' => $books,
                'use_coins' => $use_coins
            )
        );
    }
}