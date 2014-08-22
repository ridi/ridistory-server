<?php
namespace Story\Controller;

use Doctrine\DBAL\Connection;
use Exception;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Story\Entity\TestUserFactory;
use Story\Model\Book;
use Story\Model\CoinBilling;
use Story\Model\CoinProduct;
use Story\Model\PartComment;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminController implements ControllerProviderInterface
{
    //TODO: 리디스토리 안드로이드 4.17 버전 배너 숨김/보임 옵션 (0: 숨김, 1: 보임)
    const BANNER_VISIBILITY = 0;

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

        $admin->get('/banner/add', array($this, 'addBanner'));
        $admin->get('/banner/list', array($this, 'bannerList'));
        $admin->get('/banner/{banner_id}', array($this, 'bannerDetail'));
        $admin->post('/banner/{banner_id}/delete', array($this, 'deleteBanner'));
        $admin->post('/banner/{banner_id}/edit', array($this, 'editBanner'));

        $admin->get('/inapp_sales/list', array($this, 'coinSalesList'));
        $admin->get('/inapp_sales/{id}', array($this, 'coinSalesDetail'));
        $admin->post('/inapp_sales/{id}/refund', array($this, 'refundCoinSales'));

        $admin->get('/ridicash_sales/list', array($this, 'coinSalesList'));
        $admin->get('/ridicash_sales/{id}', array($this, 'coinSalesDetail'));
        $admin->post('/ridicash_sales/{id}/refund', array($this, 'refundCoinSales'));

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
        $search_type = $req->get('search_type', 'book_title');
        $search_keyword = $req->get('search_keyword', null);
        $cur_page = $req->get('page', 0);

        $limit = 50;
        $offset = $cur_page * $limit;

        if ($search_keyword) {
            $comments = PartComment::getListBySearchTypeAndKeyword($search_type, $search_keyword);
        } else {
            $comments = PartComment::getListByOffsetAndSize($offset, $limit);
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
        return $app['twig']->render('/admin/banner_list.twig', array('banner_list' => $banner_list, 'banner_visibility' => self::BANNER_VISIBILITY));
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
     * Coin Sales
     */
    public static function coinSalesList(Request $req, Application $app)
    {
        // URL로 인앱결제/리디캐시 결제 분리
        $request_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $is_inapp = (strpos($request_url, 'inapp_sales') !== false);
        if ($is_inapp) {
            $payment = CoinProduct::TYPE_INAPP;
        } else {
            $payment = CoinProduct::TYPE_RIDICASH;
        }

        $search_type = $req->get('search_type', ($is_inapp ? 'uid' : 'ridibooks_id'));
        $search_keyword = $req->get('search_keyword', null);
        $cur_page = $req->get('page', 0);

        $limit = 50;
        $offset = $cur_page * $limit;

        if ($search_keyword) {
            $coin_sales = CoinBilling::getBillingSalesListBySearchTypeAndKeyword($payment, $search_type, $search_keyword);
        } else {
            $coin_sales = CoinBilling::getBillingSalesListByOffsetAndSize($payment, $offset, $limit);
        }

        return $app['twig']->render(
            ($is_inapp ? '/admin/inapp_sales_list.twig' : '/admin/ridicash_sales_list.twig'),
            array(
                'search_type' => $search_type,
                'search_keyword' => $search_keyword,
                'coin_sales' => $coin_sales,
                'cur_page' => $cur_page
            )
        );
    }

    public static function coinSalesDetail(Request $req, Application $app, $id)
    {
        // URL로 인앱결제/리디캐시 결제 분리
        $request_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $is_inapp = (strpos($request_url, 'inapp_sales') !== false);
        if ($is_inapp) {
            $payment = CoinProduct::TYPE_INAPP;
        } else {
            $payment = CoinProduct::TYPE_RIDICASH;
        }

        $coin_sale = CoinBilling::getBillingSalesDetail($payment, $id);
        return $app['twig']->render(
            ($is_inapp ? '/admin/inapp_sales_detail.twig' : '/admin/ridicash_sales_detail.twig'),
            array('coin_sale' => $coin_sale)
        );
    }

    /*
     * Refund Coin Sales
     */
    public static function refundCoinSales(Request $req, Application $app, $id)
    {
        $payment = $req->get('payment', null);
        if ($payment) {
            try {
                $user_remain_coin = CoinBilling::refund($payment, $id);
                $app['session']->getFlashBag()->add('alert', array('success' => '환불되었습니다. (회원 잔여 코인: ' . $user_remain_coin . '개)'));
            } catch (Exception $e) {
                $app['session']->getFlashBag()->add('alert', array('error' => $e->getMessage()));
            }
        } else {
            return $app->json(array('success' => false));
        }

        return $app->json(array('success' => true));
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
        $event_coins = null;
        $cs_reward_coins = null;

        $inapp_accumulated_user = 0;
        $ridicash_accumulated_user = 0;
        $event_accumulated_user = 0;
        $cs_reward_accumulated_user = 0;
        $inapp_and_ridicash_accumulated_user = 0;

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
            $event_sql = <<<EOT
select date(eh.timestamp) event_date, count(distinct eh.u_id) user_count, sum(ch.amount) coin_amount, 0 withdraw_user_count, 0 withdraw_coin_amount from event_history eh
 left join coin_history ch on eh.ch_id = ch.id
where date(eh.timestamp) >= ? and date(eh.timestamp) <= ?
EOT;
            $cs_reward_sql = <<<EOT
select date(crh.timestamp) reward_date, count(distinct crh.u_id) user_count, sum(ch.amount) coin_amount from cs_reward_history crh
 left join coin_history ch on crh.ch_id = ch.id
where date(crh.timestamp) >= ? and date(crh.timestamp) <= ?
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
            $event_withdraw_sql = <<<EOT
select date(timestamp) withdraw_date, count(distinct u_id) withdraw_user_count, abs(sum(amount)) withdraw_coin_amount from coin_history
where source = 'OUT_WITHDRAW' and date(timestamp) >= ? and date(timestamp) <= ?
EOT;

            $inapp_accumulated_user_sql = <<<EOT
select count(distinct u_id) from inapp_history
where status != 'PENDING' and date(purchase_time) >= ? and date(purchase_time) <= ?
EOT;
            $ridicash_accumulated_user_sql = <<<EOT
select count(distinct u_id) from ridicash_history
where status != 'PENDING' and date(purchase_time) >= ? and date(purchase_time) <= ?
EOT;
            $event_accumulated_user_sql = <<<EOT
select count(distinct u_id) from event_history
where date(timestamp) >= ? and date(timestamp) <= ?
EOT;
            $cs_reward_accumulated_user_sql = <<<EOT
select count(distinct u_id) from cs_reward_history
where date(timestamp) >= ? and date(timestamp) <= ?
EOT;
            $inapp_and_ridicash_accumulated_user_sql = <<<EOT
select count(*) from (
  select distinct u_id from inapp_history ih
  where ih.status != 'PENDING' and date(ih.purchase_time) >= ? and date(ih.purchase_time) <= ?
EOT;

            $test_users = TestUserFactory::getConcatUidList(true);
            if ($test_users) {
                $inapp_buy_sql .= ' and ih.u_id not in (' . $test_users . ')';
                $ridicash_buy_sql .= ' and rh.u_id not in (' . $test_users . ')';
                $event_sql .= ' and eh.u_id not in (' . $test_users . ')';
                $cs_reward_sql .= ' and crh.u_id not in (' . $test_users . ')';
                $inapp_refunded_sql .= ' and ih.u_id not in (' . $test_users . ')';
                $ridicash_refunded_sql .= ' and rh.u_id not in (' . $test_users . ')';
                $event_withdraw_sql .= ' and u_id not in (' . $test_users . ')';
                $inapp_accumulated_user_sql .= ' and u_id not in (' . $test_users . ')';
                $ridicash_accumulated_user_sql .= ' and u_id not in (' . $test_users . ')';
                $event_accumulated_user_sql .= ' and u_id not in (' . $test_users . ')';
                $cs_reward_accumulated_user_sql .= ' and u_id not in (' . $test_users . ')';

                $inapp_and_ridicash_accumulated_user_sql .= ' and ih.u_id not in (' . $test_users . ')';
                $inapp_and_ridicash_accumulated_user_sql .= <<<EOT
 union
  select distinct u_id from ridicash_history rh
  where rh.status != 'PENDING' and date(rh.purchase_time) >= ? and date(rh.purchase_time) <= ?
EOT;
                $inapp_and_ridicash_accumulated_user_sql .= ' and rh.u_id not in (' . $test_users . ')';
            }
            $inapp_buy_sql .= ' group by date(ih.purchase_time)';
            $ridicash_buy_sql .= ' group by date(rh.purchase_time)';
            $event_sql .= ' group by date(eh.timestamp)';
            $cs_reward_sql .= ' group by date(crh.timestamp) order by date(crh.timestamp) asc';
            $inapp_refunded_sql .= ' group by date(ih.refunded_time)';
            $ridicash_refunded_sql .= ' group by date(rh.refunded_time)';
            $event_withdraw_sql .= ' group by date(timestamp)';
            $inapp_and_ridicash_accumulated_user_sql .= ') x';

            $bind = array($begin_date, $end_date);

            $inapp_buy_coins = $app['db']->fetchAll($inapp_buy_sql, $bind);
            $ridicash_buy_coins = $app['db']->fetchAll($ridicash_buy_sql, $bind);
            $event_coins = $app['db']->fetchAll($event_sql, $bind);
            $cs_reward_coins = $app['db']->fetchAll($cs_reward_sql, $bind);

            $inapp_refunded_coins = $app['db']->fetchAll($inapp_refunded_sql, $bind);
            $ridicash_refunded_coins = $app['db']->fetchAll($ridicash_refunded_sql, $bind);
            $event_withdraw_coins = $app['db']->fetchAll($event_withdraw_sql, $bind);

            $inapp_accumulated_user = $app['db']->fetchColumn($inapp_accumulated_user_sql, $bind);
            $ridicash_accumulated_user = $app['db']->fetchColumn($ridicash_accumulated_user_sql, $bind);
            $event_accumulated_user = $app['db']->fetchColumn($event_accumulated_user_sql, $bind);
            $cs_reward_accumulated_user = $app['db']->fetchColumn($cs_reward_accumulated_user_sql, $bind);
            $inapp_and_ridicash_accumulated_user = $app['db']->fetchColumn($inapp_and_ridicash_accumulated_user_sql, array($begin_date, $end_date, $begin_date, $end_date));

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
            foreach($event_withdraw_coins as $key => $ewc) {
                foreach($event_coins as &$ec) {
                    if ($ewc['withdraw_date'] == $ec['event_date']) {
                        $ec['withdraw_coin_amount'] = $ewc['withdraw_coin_amount'];
                        $ec['withdraw_user_count'] = $ewc['withdraw_user_count'];
                        unset($event_withdraw_coins[$key]);
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
            if (count($event_withdraw_coins) > 0) {
                foreach($event_withdraw_coins as $key => $ewc) {
                    array_push($event_coins,
                        array(
                            'event_date' => $ewc['withdraw_date'],
                            'user_count' => 0,
                            'coin_amount' => 0,
                            'withdraw_user_count' => $ewc['withdraw_user_count'],
                            'withdraw_coin_amount' => $ewc['withdraw_coin_amount']
                        )
                    );
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
            usort($event_coins, function ($a, $b) {
                    $a_time = strtotime($a['event_date']);
                    $b_time = strtotime($b['event_date']);

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
                'ridicash_buy_coins' => $ridicash_buy_coins,
                'event_coins' => $event_coins,
                'cs_reward_coins' => $cs_reward_coins,
                'inapp_accumulated_user' => $inapp_accumulated_user,
                'ridicash_accumulated_user' => $ridicash_accumulated_user,
                'event_accumulated_user' => $event_accumulated_user,
                'cs_reward_accumulated_user' => $cs_reward_accumulated_user,
                'inapp_and_ridicash_accumulated_user' => $inapp_and_ridicash_accumulated_user
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


            $test_users = TestUserFactory::getConcatUidList(true);
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
        $accumulated_user = 0;

        if ($begin_date && $end_date) {
            $sql = <<<EOT
select date(timestamp) use_date, count(distinct u_id) user_count, abs(sum(amount)) used_coin_amount from coin_history
where source = 'OUT_BUY_PART' and date(timestamp) >= ? and date(timestamp) <= ?
EOT;
            $bind = array($begin_date, $end_date);

            $test_users = TestUserFactory::getConcatUidList(true);
            if ($test_users) {
                $sql .= ' and u_id not in (' . $test_users . ')';
            }

            $accumulated_user = $app['db']->fetchAll($sql, $bind);
            $accumulated_user = $accumulated_user[0]['user_count'];

            $sql .= ' group by date(timestamp)';
            $use_coins = $app['db']->fetchAll($sql, $bind);
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
                'use_coins' => $use_coins,
                'accumulated_user' => $accumulated_user
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
        $book_download = null;
        $book_charged_download = null;
        $total_download = 0;
        $total_charged_download = 0;

        if ($begin_date && $end_date && $bid_list) {
            $b_ids = explode(PHP_EOL, $bid_list);  // 조회할 책 목록
            foreach ($b_ids as &$b_id) {
                $b_id = trim($b_id);
            }

            $books = Book::getListByIds($b_ids, false, false);

            if (!empty($books)) {
                $sql = <<<EOT
select date(timestamp) use_date
EOT;
                $i = 0;
                // 책 ID 목록을 비우고, 실제 존재하는 책 ID로만 다시 배열을 만듬.
                unset($b_ids);
                $b_ids = array();
                foreach ($books as &$book) {
                    array_push($b_ids, $book['id']);
                    $sql .= ', sum(if(b_id=' . $book['id'] . ', coin_amount, 0)) b_' . $i++;
                }

                // 책 별 사용한 코인 수
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
                // 책 별 다운로드(유료+무료) 수
                $book_download_sql = <<<EOT
select b_id, count(distinct ph.u_id) count from purchase_history ph
 join (
  select b.id b_id, p.id p_id from book b
   join part p on b.id = p.b_id
  where b.id in (?)
 ) b_info on ph.p_id = b_info.p_id
where date(ph.timestamp) >= ? and date(ph.timestamp) <= ?
EOT;
                // 총 다운로드 수
                $total_download_sql = <<<EOT
select count(distinct u_id) from purchase_history
where p_id in (
 select id from part where b_id in (?)
) and date(timestamp) >= ? and date(timestamp) <= ?
EOT;
                // 테스트 유저 제외
                $test_users = TestUserFactory::getConcatUidList(true);
                if ($test_users) {
                    $sql .= ' and u_id not in (' . $test_users . ')';
                    $book_download_sql .= ' and ph.u_id not in (' . $test_users . ')';
                    $total_download_sql .= ' and u_id not in (' . $test_users . ')';
                }

                // 책 별 유료 다운로드, 총 유료 다운로드 수
                $book_charged_download_sql = $book_download_sql . ' and is_paid = 1';
                $total_charged_download_sql = $total_download_sql . ' and is_paid = 1';

                $sql .= ' group by date(timestamp)';
                $book_download_sql .= ' group by b_id';
                $book_charged_download_sql .= ' group by b_id';

                $bind = array($b_ids, $begin_date, $end_date);

                $stmt = $app['db']->executeQuery(
                    $sql,
                    $bind,
                    array(Connection::PARAM_INT_ARRAY, \PDO::PARAM_STR, \PDO::PARAM_STR)
                );
                $use_coins = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                // Book Download
                $stmt = $app['db']->executeQuery(
                    $book_download_sql,
                    $bind,
                    array(Connection::PARAM_INT_ARRAY, \PDO::PARAM_STR, \PDO::PARAM_STR)
                );
                $book_download = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                $stmt = $app['db']->executeQuery(
                    $book_charged_download_sql,
                    $bind,
                    array(Connection::PARAM_INT_ARRAY, \PDO::PARAM_STR, \PDO::PARAM_STR)
                );
                $book_charged_download = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                // 책 별 유/무료 다운로드 수가 없는 경우에 대한 예외 처리
                if (count($b_ids) != count($book_download)) {
                    $temp_b_ids = $b_ids;
                    foreach ($book_download as $bd) {
                        foreach ($temp_b_ids as $key => $temp_b_id) {
                            if ($bd['b_id'] == $temp_b_id) {
                                unset($temp_b_ids[$key]);
                                break;
                            }
                        }
                    }
                    if (!empty($temp_b_ids)) {
                        foreach ($temp_b_ids as $temp_b_id) {
                            array_push($book_download, array('b_id' => $temp_b_id, 'count' => 0));
                        }
                    }
                }
                if (count($b_ids) != count($book_charged_download)) {
                    $temp_b_ids = $b_ids;
                    foreach ($book_charged_download as $bd) {
                        foreach ($temp_b_ids as $key => $temp_b_id) {
                            if ($bd['b_id'] == $temp_b_id) {
                                unset($temp_b_ids[$key]);
                                break;
                            }
                        }
                    }
                    if (!empty($temp_b_ids)) {
                        foreach ($temp_b_ids as $temp_b_id) {
                            array_push($book_charged_download, array('b_id' => $temp_b_id, 'count' => 0));
                        }
                    }
                }

                // 책 ID로 오름차순 정렬
                usort($book_download, function ($a, $b) {
                        if ($a['b_id'] == $b['b_id']) {
                            return 0;
                        }

                        return ($a['b_id'] < $b['b_id'] ? -1 : 1);
                    }
                );
                usort($book_charged_download, function ($a, $b) {
                        if ($a['b_id'] == $b['b_id']) {
                            return 0;
                        }

                        return ($a['b_id'] < $b['b_id'] ? -1 : 1);
                    }
                );

                // 총 다운로드 수
                $stmt = $app['db']->executeQuery(
                    $total_download_sql,
                    $bind,
                    array(Connection::PARAM_INT_ARRAY, \PDO::PARAM_STR, \PDO::PARAM_STR)
                );
                $total_download = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                $total_download = $total_download[0];

                // 총 유료 다운로드 수
                $stmt = $app['db']->executeQuery(
                    $total_charged_download_sql,
                    $bind,
                    array(Connection::PARAM_INT_ARRAY, \PDO::PARAM_STR, \PDO::PARAM_STR)
                );
                $total_charged_download= $stmt->fetchAll(\PDO::FETCH_COLUMN);
                $total_charged_download = $total_charged_download[0];
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
                'use_coins' => $use_coins,
                'book_download' => $book_download,
                'book_charged_download' => $book_charged_download,
                'total_download' => $total_download,
                'total_charged_download' => $total_charged_download
            )
        );
    }
}