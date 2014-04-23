<?php
namespace Story\Controller\Admin;

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