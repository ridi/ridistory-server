<?php
namespace Story\Controller\Admin;

use Exception;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Story\Model\Buyer;
use Story\Model\Event;
use Story\Model\Part;
use Symfony\Component\HttpFoundation\Request;

class BuyerController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $admin = $app['controllers_factory'];

        // 일괄 작업
        $admin->get('bloc/verify', array($this, 'verifyBuyerListBloc'));
        $admin->post('bloc/verify', array($this, 'verifyBuyerListBloc'));
        $admin->get('bloc/coin', function () use ($app) {
                return $app['twig']->render('admin/buyer_bloc_coin.twig');
            }
        );
        $admin->post('bloc/coin/add', array($this, 'addBuyerListBlocCoin'));
        $admin->post('bloc/coin/reduce', array($this, 'reduceBuyerListBlocCoin'));

        $admin->get('list', array($this, 'buyerList'));
        $admin->get('{id}', array($this, 'buyerDetail'));
        $admin->post('{id}/coin/add', array($this, 'addBuyerCoin'));
        $admin->post('{id}/coin/reduce', array($this, 'reduceBuyerCoin'));
        $admin->post('{id}/purchase_history/delete', array($this, 'deletePurchasedHistory'));

        return $admin;
    }

    /*
     * Bloc (Batch Process)
     */
    public function verifyBuyerListBloc(Request $req, Application $app)
    {
        $user_type = $req->get('user_type', 'google_account');
        $user_list = $req->get('user_list', '');

        $invalid_ids = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $accounts = explode(PHP_EOL, $user_list);
            foreach ($accounts as $key => &$account) {
                $trimmed_account = trim($account);
                if ($trimmed_account) {
                    $account = trim($account);
                } else {
                    unset($accounts[$key]);
                }
            }

            if (!empty($accounts) && $user_type) {
                if ($user_type == 'google_account') {
                    $invalid_ids = Buyer::verifyGoogleAccounts($accounts);
                } else if ($user_type == 'uid') {
                    $invalid_ids = Buyer::verifyUids($accounts);
                }

                if (empty($invalid_ids)) {
                    $app['session']->getFlashBag()->add('alert', array('success' => '입력하신 계정이 모두 정상입니다.'));
                } else {
                    $app['session']->getFlashBag()->add('alert', array('error' => '존재하지 않는 계정이 ' . count($invalid_ids) . '건 존재합니다.'));
                }
            }
        }

        return $app['twig']->render(
            'admin/buyer_verify.twig',
            array(
                'user_type' => $user_type,
                'user_list' => $user_list,
                'invalid_ids' => $invalid_ids
            )
        );
    }

    public function addBuyerListBlocCoin(Request $req, Application $app)
    {
        $user_type = $req->get('user_type', null);
        $user_list = $req->get('user_list', null);
        $comment = $req->get('comment', null);
        $coin_amount = $req->get('coin_amount', 0);

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

        // 인원 수 검사
        if (count($u_ids) > 1000 || empty($u_ids)) {
            $app['session']->getFlashBag()->add('alert', array('error' => '코인은 한 번에 1명 ~ 1,000명까지 지급 가능합니다. (인원 수를 확인해주세요)'));
            return $app->redirect('/admin/buyer/bloc/coin');
        }

        if (!$comment && ($coin_amount <= 0)) {
            $app['session']->getFlashBag()->add('alert', array('error' => '정보를 모두 정확히 입력해주세요.'));
            return $app->redirect('/admin/buyer/bloc/coin');
        }

        // 입력한 회원 정보 중에, 유효하지 않은 회원이 있는지를 검사
        if ($user_type == 'google_account') {
            $invalid_u_ids = Buyer::verifyGoogleAccounts($u_ids);
        } else if ($user_type == 'uid') {
            $invalid_u_ids = Buyer::verifyUids($u_ids);
        }

        if (!empty($invalid_u_ids)) {
            $app['session']->getFlashBag()->add('alert', array('error' => '계정 정보가 정확하지 않습니다. (' . implode(' / ', $invalid_u_ids) . ')'));
            return $app->redirect('/admin/buyer/bloc/coin');
        } else if ($user_type == 'google_account') {
            $u_ids = Buyer::googleAccountsToUserIds($u_ids);
        }

        /*
         * 코인 추가.
         * 모든 코인을 성공적으로 추가하지 못하면, Rollback
         */
        $app['db']->beginTransaction();
        try {
            foreach ($u_ids as $u_id) {
                $ch_id = Buyer::addCoin($u_id, $coin_amount, Buyer::COIN_SOURCE_IN_EVENT);
                if (!$ch_id) {
                    throw new Exception('코인을 추가하는 도중 오류가 발생했습니다. (유저 ID: ' . $u_id . ')');
                }

                $r = Event::add(array('u_id' => $u_id, 'ch_id' => $ch_id, 'comment' => trim($comment)));
                if (!$r) {
                    throw new Exception('코인 추가 이벤트를 등록하는 도중 오류가 발생했습니다. (유저 ID: ' . $u_id . ')');
                }
            }
            $app['db']->commit();
            $app['session']->getFlashBag()->add('alert', array('success' => $coin_amount . '코인씩을 추가하였습니다. (총 ' . count($u_ids) . '명 / ' . ($coin_amount * count($u_ids)) . '코인)'));
        } catch (Exception $e) {
            $app['db']->rollback();
            $app['session']->getFlashBag()->add('alert', array('error' => $e->getMessage()));
        }

        return $app->redirect('/admin/buyer/bloc/coin');
    }

    public function reduceBuyerListBlocCoin(Request $req, Application $app)
    {
        $user_type = $req->get('user_type', null);
        $user_list = $req->get('user_list', null);
        $comment = $req->get('comment', null);
        $coin_amount = $req->get('coin_amount', 0);

        $u_ids = explode(PHP_EOL, $user_list);
        foreach ($u_ids as $key => &$u_id) {
            $trimmed_uid = trim($u_id);
            if ($trimmed_uid) {
                $u_id = trim($u_id);
            } else {
                unset($u_ids[$key]);
            }
        }
        unset($u_id);

        // 인원 수 검사
        if (count($u_ids) > 1000 || empty($u_ids)) {
            $app['session']->getFlashBag()->add('alert', array('error' => '코인은 한 번에 1명 ~ 1,000명까지 회수 가능합니다. (인원 수를 확인해주세요)'));
            return $app->redirect('/admin/buyer/bloc/coin');
        }

        if (!$comment && ($coin_amount <= 0)) {
            $app['session']->getFlashBag()->add('alert', array('error' => '정보를 모두 정확히 입력해주세요.'));
            return $app->redirect('/admin/buyer/bloc/coin');
        }

        // 입력한 회원 정보 중에, 유효하지 않은 회원이 있는지를 검사
        if ($user_type == 'google_account') {
            $invalid_u_ids = Buyer::verifyGoogleAccounts($u_ids);
        } else if ($user_type == 'uid') {
            $invalid_u_ids = Buyer::verifyUids($u_ids);
        }

        if (!empty($invalid_u_ids)) {
            $app['session']->getFlashBag()->add('alert', array('error' => '계정 정보가 정확하지 않습니다. (' . implode(' / ', $invalid_u_ids) . ')'));
            return $app->redirect('/admin/buyer/bloc/coin');
        } else if ($user_type == 'google_account') {
            $u_ids = Buyer::googleAccountsToUserIds($u_ids);
        }

        /*
         * 코인 회수.
         * 모든 코인을 성공적으로 회수하지 못하면, Rollback
         */
        $app['db']->beginTransaction();
        try {
            foreach ($u_ids as $u_id) {
                $ch_id = Buyer::reduceCoin($u_id, -$coin_amount, Buyer::COIN_SOURCE_OUT_WITHDRAW);
                if (!$ch_id) {
                    throw new Exception('코인을 회수하는 도중 오류가 발생했습니다. (유저 ID: ' . $u_id . ')');
                }
            }
            $app['db']->commit();
            $app['session']->getFlashBag()->add('alert', array('success' => $coin_amount . '코인씩을 회수하였습니다. (총 ' . count($u_ids) . '명 / ' . ($coin_amount * count($u_ids)) . '코인)'));
        } catch (Exception $e) {
            $app['db']->rollback();
            $app['session']->getFlashBag()->add('alert', array('error' => $e->getMessage()));
        }

        return $app->redirect('/admin/buyer/bloc/coin');
    }

    /*
     * Buyer User
     */
    public function buyerList(Request $req, Application $app)
    {
        $search_type = $req->get('search_type', 'google_account');
        $search_keyword = $req->get('search_keyword', null);
        $cur_page = $req->get('page', 0);

        $limit = 50;
        $offset = $cur_page * $limit;

        if ($search_keyword) {
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
            if ($r) {
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
            $r = Buyer::reduceCoin($id, $coin_amount, $source);
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
                $r = Buyer::reduceCoin($id, $part['price'], Buyer::COIN_SOURCE_OUT_WITHDRAW);
                if ($r) {
                    $app['session']->getFlashBag()->add('alert', array('info' => '구매내역이 삭제되었습니다. (코인 회수)'));
                } else {
                    throw new Exception('구매내역을 삭제하는 도중 오류가 발생했습니다. (코인 회수)');
                }
            }

            $app['db']->commit();
            $result = true;
        } catch (Exception $e) {
            $app['db']->rollback();
            $app['session']->getFlashBag()->add('alert', array('danger' => $e->getMessage()));
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