<?php
namespace Story\Controller\Admin;

use Exception;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Story\Model\Buyer;
use Story\Model\Part;
use Symfony\Component\HttpFoundation\Request;

class BuyerController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $admin = $app['controllers_factory'];

        $admin->get('list', array($this, 'buyerList'));

        $admin->get('list/coin', array($this, 'buyerListCoin'));
        $admin->post('list/coin/add', array($this, 'addBuyerListCoin'));

        $admin->get('{id}', array($this, 'buyerDetail'));
        $admin->post('{id}/coin/add', array($this, 'addBuyerCoin'));
        $admin->post('{id}/coin/reduce', array($this, 'reduceBuyerCoin'));
        $admin->post('{id}/purchase_history/delete', array($this, 'deletePurchasedHistory'));

        return $admin;
    }

    /*
     * Buyer User
     */
    public function buyerList(Request $req, Application $app)
    {
        $search_type = $req->get('search_type', null);
        $search_keyword = $req->get('search_keyword', null);
        $cur_page = $req->get('page', 0);

        $limit = 50;
        $offset = $cur_page * $limit;

        if ($search_type && $search_keyword) {
            $buyers = Buyer::getListBySearchTypeAndKeyword($search_type, $search_keyword);
        } else {
            $buyers = Buyer::getListByOffsetAndSize($offset, $limit);
        }

        $buyer_count = Buyer::getTotalUserCount();

        return $app['twig']->render(
            'admin/buyer_list.twig',
            array(
                'search_type' => $search_type,
                'search_keyword' => $search_keyword,
                'buyer_count' => $buyer_count,
                'buyers' => $buyers,
                'cur_page' => $cur_page
            )
        );
    }

    public function buyerDetail(Request $req, Application $app, $id)
    {
        $buyer = Buyer::get($id);

        $coin_in = Buyer::getCoinInList($id);
        $total_coin_in = 0;
        foreach ($coin_in as $in) {
            $total_coin_in += $in['amount'];
        }

        $coin_out = Buyer::getCoinOutList($id);
        $total_coin_out = 0;
        foreach ($coin_out as $out) {
            $total_coin_out += $out['amount'];
        }

        $purchases = Buyer::getWholePurchasedPartList($id);

        return $app['twig']->render(
            'admin/buyer_detail.twig',
            array(
                'buyer' => $buyer,
                'coin_in' => $coin_in,
                'coin_out' => $coin_out,
                'total_coin_count' => (array('in' => $total_coin_in, 'out' => $total_coin_out)),
                'purchases' => $purchases
            )
        );
    }

    /*
     * Coin
     */
    public function buyerListCoin(Request $req, Application $app)
    {
        $user_list = $req->get('user_list', '');

        return $app['twig']->render(
            'admin/buyer_add_coins.twig',
            array(
                'user_list' => $user_list
            )
        );
    }

    public function addBuyerListCoin(Request $req, Application $app)
    {
        $user_type = $req->get('user_type', null);
        $user_list = $req->get('user_list', null);
        $coin_add_reason = $req->get('coin_add_reason', null);
        $coin_add_amount = $req->get('coin_add_amount', 0);

        $u_ids = explode(PHP_EOL, $user_list);
        foreach ($u_ids as $key => &$u_id) {
            $trimmed_uid = trim($u_id);
            if ($trimmed_uid) {
                $u_id = trim($u_id);
            } else {
                unset($u_ids[$key]);
            }
        }
        /*
         * 참고: http://php.net/manual/ro/control-structures.foreach.php
         * Reference of a $value and the last array element remain even after the foreach loop. It is recommended to destroy it by unset().
         */
        unset($u_id);

        if ($user_type && $coin_add_reason && ($coin_add_amount > 0)) {
            /*
             * 구글 계정: 구글 계정 -> 유저 ID + Valid 체크
             * 유저 ID : Valid 체크
             */
            if ($user_type == 'google_account') {
                $google_ids = $u_ids;
                $u_ids = Buyer::googleAccountsToUserIds($google_ids);

                if (count($google_ids) != count($u_ids)) {
                    $invalid_google_ids = array_diff($google_ids, array_keys($u_ids));
                    $message = '';
                    foreach ($invalid_google_ids as $google_id) {
                        $message .= $google_id . ' / ';
                    }
                    $message = substr($message, 0, strlen($message) - 3);

                    $app['session']->getFlashBag()->add('alert', array('error' => '구글 계정 정보가 정확하지 않습니다. (' . $message . ')'));
                    return $app->redirect('/admin/buyer/list/coin');
                }
            } else if ($user_type == 'uid') {
                foreach ($u_ids as $u_id) {
                    if (!Buyer::isValidUid($u_id)) {
                        $app['session']->getFlashBag()->add('alert', array('error' => '계정 정보가 정확하지 않습니다. (유저 ID: ' . $u_id . ')'));
                        return $app->redirect('/admin/buyer/list/coin');
                    }
                }
            }

            /*
             * 코인 추가.
             * 모든 코인을 성공적으로 추가하지 못하면, Rollback
             */
            $app['db']->beginTransaction();
            try {
                foreach ($u_ids as $u_id) {
                    $r = Buyer::addCoin($u_id, $coin_add_amount, Buyer::COIN_SOURCE_IN_EVENT);
                    if (!$r) {
                        throw new Exception('코인을 추가하는 도중 오류가 발생했습니다. (유저 ID: ' . $u_id . ')');
                    }
                }
                $app['db']->commit();
                $app['session']->getFlashBag()->add('alert', array('success' => $coin_add_amount . '코인씩을 추가하였습니다. (총 ' . count($u_ids) . '명 / ' . ($coin_add_amount * count($u_ids)) . '코인)'));
            } catch (Exception $e) {
                $app['session']->getFlashBag()->add('alert', array('error' => $e->getMessage()));
                $app['db']->rollback();
            }
        } else {
            $app['session']->getFlashBag()->add('alert', array('error' => '정보를 모두 정확히 입력해주세요.'));
        }

        return $app->redirect('/admin/buyer/list/coin');
    }

    public function addBuyerCoin(Request $req, Application $app, $id)
    {
        $source = $req->get('source');
        $coin_amount = $req->get('coin_amount');

        if ($source == '' || $coin_amount == 0) {
            $app['session']->getFlashBag()->add('alert', array('error' => '코인을 추가하지 못했습니다. (코인 추가 이유가 없거나, 추가하려는 코인이 0개 입니다.)'));
        }  else {
            $r = Buyer::addCoin($id, $coin_amount, $source);
            if ($r === 1) {
                $app['session']->getFlashBag()->add('alert', array('success' => $coin_amount.'코인이 추가되었습니다.'));
            } else {
                $app['session']->getFlashBag()->add('alert', array('error' => '코인을 추가하지 못했습니다. (DB 오류)'));
            }
        }

        return $app->json(array('success' => true));
    }

    public function reduceBuyerCoin(Request $req, Application $app, $id)
    {
        $source = $req->get('source');
        $coin_amount = $req->get('coin_amount');
        if ($coin_amount > 0) {
            $coin_amount *= -1;
        }

        if ($source == '' || $coin_amount == 0) {
            $app['session']->getFlashBag()->add('alert', array('error' => '코인을 감소시키지지 못했습니다. (코인 감소 이유가 없거나, 감소시키려는 코인이 0개 입니다.)'));
        }  else {
            $r = Buyer::useCoin($id, $coin_amount, $source, null);
            if ($r) {
                $app['session']->getFlashBag()->add('alert', array('success' => abs($coin_amount).'코인을 감소하였습니다.'));
            } else {
                $app['session']->getFlashBag()->add('alert', array('error' => '코인을 감소하지 못했습니다. (DB 오류)'));
            }
        }

        return $app->json(array('success' => true));
    }

    /*
     * Purchase History
     */
    public function deletePurchasedHistory(Request $req, Application $app, $id)
    {
        $ph_id = $req->get('ph_id');
        $delete_coin_history = $req->get('refund_coin');

        $purchase_history = Buyer::getPurchasedHistory($ph_id);
        $part = Part::get($purchase_history['p_id']);

        // 트랜잭션 시작
        $app['db']->beginTransaction();
        try {
            Buyer::deletePurchasedHistory($ph_id);
            Buyer::deleteBuyPartCoinHistory($ph_id);

            if ($delete_coin_history == 1) {
                $app['session']->getFlashBag()->add('alert', array('info' => '구매내역이 삭제되었습니다. (코인 반환)'));
            } else {
                Buyer::useCoin($id, $part['price'], Buyer::COIN_SOURCE_OUT_WITHDRAW, null);
                $app['session']->getFlashBag()->add('alert', array('info' => '구매내역이 삭제되었습니다. (코인 회수)'));
            }

            $app['db']->commit();
            $result = true;
        } catch (Exception $e) {
            $app['db']->rollback();
            $app['session']->getFlashBag()->add('alert', array('danger' => '구매내역을 삭제하는 도중 오류가 발생했습니다. (코인 회수)'));
            $result = false;
        }

        // 캐시 삭제
        $app['cache']->delete('part_list_0_0_' . $part['b_id']);
        $app['cache']->delete('part_list_0_1_' . $part['b_id']);
        $app['cache']->delete('part_list_1_0_' . $part['b_id']);
        $app['cache']->delete('part_list_1_1_' . $part['b_id']);

        // 구매목록 캐시 삭제
        $app['cache']->delete('purchased_book_list_' . $id);
        $app['cache']->delete('purchased_part_list_' . $id . '_' . $part['b_id']);

        return $app->json(array('success' => $result));
    }
}