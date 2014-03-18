<?php
namespace Story\Controller;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Story\Model\Buyer;
use Story\Model\InAppBilling;
use Story\Model\RecommendedBook;
use Story\Util\AES128;
use Symfony\Component\HttpFoundation\Request;

use Story\Model\Book;
use Story\Model\Part;
use Story\Model\StoryPlusBook;
use Story\Model\StoryPlusBookIntro;
use Story\Model\StoryPlusBookComment;
use Story\Model\UserInterest;
use Story\Model\UserPartLike;
use Story\Model\UserStoryPlusBookLike;
use Story\Model\PushDevice;
use Symfony\Component\Security\Acl\Exception\Exception;

class ApiController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        /**
         * @var $api \Silex\ControllerCollection
         */
        $api = $app['controllers_factory'];

        $api->post('/buyer/auth', array($this, 'authBuyerGoogleAccount'));
        $api->get('/buyer/coin', array($this, 'getBuyerCoinBalance'));
        $api->post('/buyer/coin/add', array($this, 'addBuyerCoin'));

        $api->get('/book/list', array($this, 'bookList'));
        $api->get('/book/completed_list', array($this, 'completedBookList'));
        $api->get('/book/{b_id}', array($this, 'bookDetail'));
        $api->post('/book/{b_id}/buy', array($this, 'buyBookPart'));

        $api->get('/user/{device_id}/part/{p_id}/like', array($this, 'userLikePart'));
        $api->get('/user/{device_id}/part/{p_id}/unlike', array($this, 'userUnlikePart'));

        $api->get('/user/{device_id}/interest/list', array($this, 'userInterestList'));
        $api->get('/user/{device_id}/interest/{b_id}', array($this, 'getUserInterest'));
        $api->get('/user/{device_id}/interest/{b_id}/set', array($this, 'setUserInterest'));
        $api->get('/user/{device_id}/interest/{b_id}/clear', array($this, 'clearUserInterest'));

        $api->get('/storyplusbook/list', array($this, 'storyPlusBookList'));
        $api->get('/storyplusbook/{b_id}', array($this, 'storyPlusBookDetail'));

        $api->get('/user/{device_id}/storyplusbook/{b_id}/like', array($this, 'userLikeStoryPlusBook'));
        $api->get('/user/{device_id}/storyplusbook/{b_id}/unlike', array($this, 'userUnlikeStoryPlusBook'));

        $api->get('/storyplusbook/{b_id}/comment/add', array($this, 'addStoryPlusBookComment'));
        $api->get('/storyplusbook/{b_id}/comment/list', array($this, 'storyPlusBookCommentList'));

        $api->get('/version/storyplusbook', array($this, 'getStoryPlusBookVersion'));

        $api->get('/latest_version', array($this, 'getLatestVersion'));

        $api->get('/push_device/register', array($this, 'registerPushDevice'));

        $api->get('/validate_download', array($this, 'validatePartDownload'));
        $api->get('/validate_storyplusbook_download', array($this, 'validateStoryPlusBookDownload'));

        $api->get('/inapp_product/list', array($this, 'inAppProductList'));

        $api->get('/shorten_url/{id}', array($this, 'shortenUrl'));

        return $api;
    }

    /*
     * Buyer & Coin
     */
    public function authBuyerGoogleAccount(Request $req, Application $app)
    {
        $google_id = $req->get('google_account');
        $token = $req->get('token');

        // Google Services Auth
        $ch =curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/oauth2/v1/userinfo?access_token=' . $token);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);

        $response = curl_exec($ch);
        $response = json_decode($response, true);
        curl_close($ch);

        $buyer = null;
        $server_google_id = $response['email'];
        $is_verified_email = $response['verified_email'];
        if ($server_google_id == $google_id && $is_verified_email) {
            $buyer = Buyer::getByGoogleAccount($google_id);
            if ($buyer == null) {
                $id = Buyer::add($google_id);
                $buyer = Buyer::get($id);
            }
            $buyer['id'] = AES128::encrypt(Buyer::USER_ID_AES_SECRET_KEY, $buyer['id']);
        }

        return $app->json($buyer);
    }

    public function getBuyerCoinBalance(Request $req, Application $app)
    {
        $coin_balance = 0;

        $u_id = $req->get('u_id', '0');
        if ($u_id) {
            $u_id = AES128::decrypt(Buyer::USER_ID_AES_SECRET_KEY, $u_id);
            if (Buyer::isValidUid($u_id)) {
                $coin_balance = Buyer::getCoinBalance($u_id);
            }
        }

        return $app->json(compact("coin_balance"));
    }

    public function addBuyerCoin(Request $req, Application $app)
    {
        $inputs = $req->request->all();
        if ($inputs['u_id']) {
            $inputs['u_id'] = AES128::decrypt(Buyer::USER_ID_AES_SECRET_KEY, $inputs['u_id']);
        } else {
            return $app->json(array('success' => false, 'message' => 'Access Denied1'));
        }

        $r = InAppBilling::verifyInAppBilling($inputs);
        error_log("Verfying IAB: " . $r, 0);
        if ($r) {
            $inapp_info = InAppBilling::getInAppProductBySku($inputs['sku']);
            $r = Buyer::addCoin($inputs['u_id'], ($inapp_info['coin_amount'] + $inapp_info['bonus_coin_amount']), Buyer::COIN_SOURCE_IN_INAPP);
            if ($r) {
                $coin_amount = Buyer::getCoinBalance($inputs['u_id']);
                return $app->json(array('success' => true, 'message' => '성공', 'coin_balance' => $coin_amount));
            } else {
                return $app->json(array('success' => false, 'message' => '코인 증가 오류'));
            }
        } else {
            return $app->json(array('success' => false, 'message' => 'Access Denied2'));
        }
    }

    /*
     * Book
     */
    public function bookList(Request $req, Application $app)
    {
        /**
         * @var $v Api Version
         *
         * v1 : Exclude Adult
         * v2 : Include Adult
         * v3 : Use Lock Function
         */
        $v = intval($req->get('v', '1'));
        $cache_key = 'book_list_' . $v;

        $exclude_adult = ($v < 2);
        $book = $app['cache']->fetch(
            $cache_key,
            function () use ($exclude_adult) {
                return Book::getOpenedBookList($exclude_adult);
            },
            60 * 10
        );
        return $app->json($book);
    }

    public function completedBookList(Request $req, Application $app)
    {
        $book = $app['cache']->fetch(
            'completed_book_list',
            function () {
                return Book::getCompletedBookList();
            },
            60 * 10
        );
        return $app->json($book);
    }

    /**
     * 상세페이지에서 보여질 데이터를 JSON 형태로 전송
     *  - 책 정보
     *  - 파트 정보 리스트 (각 파트별 좋아요, 댓글 갯수 포함)
     *  - 관심책 지정 여부
     *  - 잠금 여부 (v3 이상)
     *  - 구매 여부 (v3 이상)
     */
    public function bookDetail(Request $req, Application $app, $b_id)
    {
        $book = Book::get($b_id);
        if ($book == false) {
            return $app->json(array('success' => false, 'error' => 'no such book'));
        }

        /**
         * @var $v Api Version
         *
         * v1 : Exclude Adult
         * v2 : Include Adult
         * v3 : Use Lock Function
         */
        $v = intval($req->get('v', '1'));
        $active_lock = ($v > 2) && ($book['is_active_lock'] == 1);

        // 완결되었고, 종료 후 액션이 모두 공개 혹은 모두 잠금이면 파트 모두 보임
        $show_all = false;
        $is_completed = ($book['is_completed'] == 1 || strtotime($book['end_date']) < strtotime('now') ? 1 : 0);
        $end_action_flag = $book['end_action_flag'];

        if ($is_completed) {
            $book['is_completed'] = 1;
        }

        $cache_key = 'part_list_' . $active_lock . '_' . $show_all . '_';
        $parts = $app['cache']->fetch(
            $cache_key . $b_id,
            function () use ($b_id, $active_lock, $is_completed, $end_action_flag) {
                return Part::getListByBid($b_id, true, $active_lock, $is_completed, $end_action_flag);
            },
            60 * 10
        );

        $is_valid_uid = false;
        $purchased_flags = null;
        // 유료화 버전(v3)이고, Uid가 유효할 경우 구매내역 받아옴.
        if ($v > 2) {
            $u_id = $req->get('u_id', '0');
            if ($u_id) {
                $u_id = AES128::decrypt(Buyer::USER_ID_AES_SECRET_KEY, $u_id);
                $is_valid_uid = Buyer::isValidUid($u_id);
                if ($is_valid_uid) {
                    $purchased_flags = Buyer::getPurchasedListByParts($u_id, $parts);
                }
            }
        }

        foreach ($parts as &$part) {
            // 1화가 아직 시작하지 않은 경우에는, '잠금' 도서 무시하고, 앱 내에서 Coming Soon 처리
            if ($part['seq'] <= 1 && strtotime($part['begin_date']) > strtotime('now')) {
                $parts = array();
                break;
            }

            $part['last_update'] = ($part['begin_date'] == date('Y-m-d')) ? 1 : 0;

            $part['is_purchased'] = 0;
            if ($is_valid_uid) {
                foreach ($purchased_flags as $pf) {
                    if ($pf['p_id'] == $part['id']) {
                        $part['is_purchased'] = 1;
                        unset($pf);
                        break;
                    }
                }
            }
        }

        $book['parts'] = $parts;
        $book['has_recommended_books'] = (RecommendedBook::hasRecommendedBooks($b_id) > 0) ? true : false;

        $device_id = $req->get('device_id');
        $book['interest'] = ($device_id === null) ? false : UserInterest::hasInterestInBook($device_id, $b_id);

        return $app->json($book);
    }

    public function buyBookPart(Request $req, Application $app)
    {
        $u_id = $req->get('u_id', '0');
        if ($u_id) {
            $u_id = AES128::decrypt(Buyer::USER_ID_AES_SECRET_KEY, $u_id);
            if (!Buyer::isValidUid($u_id)) {
                return $app->json(array('success' => 'false', 'message' => 'Invalid User'));
            }
        } else {
            return $app->json(array('success' => 'false', 'message' => 'Invalid User'));
        }

        $p_id = $req->get('p_id');
        $part = Part::get($p_id);
        $book = Book::get($part['b_id']);

        $today = date("Y-m-d H:i:s");

        $is_completed = (strtotime($today) >= strtotime($book['end_date']) ? true : false);

        $is_free = (!$is_completed && strtotime($part['begin_date']) <= strtotime($today))
            || ($is_completed && (($book['end_action_flag'] == Book::ALL_CHARGED && $part['price'] == 0) || $book['end_action_flag'] == Book::ALL_FREE));

        $is_charged = (!$is_completed && (strtotime($part['begin_date']) > strtotime($today) && strtotime($part['begin_date']) <= strtotime($today . " + 14 days")))
            || ($is_completed && ($book['end_action_flag'] == Book::ALL_CHARGED && $part['price'] > 0));


        // 무료일 경우, 구매내역에 있으면 무시하고 다운로드
        // 무료일 경우, 구매내역에 없으면, 구매내역 등록하고 다운로드

        // 유료일 경우, 구매내역에 있으면 무시하고 다운로드
        // 유료일 경우, 구매내역에 없으면, 구매 가능한지 여부 구하기
        //                          구매 불가능하면 -> 오류 (코인 부족 등)
        //                          구매 가능하면 -> 구매내역 등록하고 다운로드

        // 비공개일 경우, 오류

        $user_coin_balance = Buyer::getCoinBalance($u_id);
        if ($is_free && !$is_charged) { // 무료
            Buyer::buyPart($u_id, $p_id, 0);
            return $app->json(array('success' => true, 'message' => '무료(성공)', 'coin_balance' => $user_coin_balance));
        } else if (!$is_free && $is_charged) {  // 유료
            // 트랜잭션 시작
            $app['db']->beginTransaction();
            try {
                if (Buyer::hasPurchasedPart($u_id, $p_id)) {
                    $message = '구매내역 존재(성공)';
                    $r = true;
                } else {
                    $ph_id = Buyer::buyPart($u_id, $p_id, $part['price']);
                    if ($ph_id && $user_coin_balance >= $part['price']) {
                        $r = Buyer::useCoin($u_id, $part['price'], Buyer::COIN_SOURCE_OUT_USE, $ph_id);
                        if ($r === true) {
                            $message = '유료(성공)';
                            $user_coin_balance -= $part['price'];
                        } else {
                            throw new Exception('코인 결제 도중 오류가 발생하였습니다.');
                        }
                    } else {
                        throw new Exception(($ph_id > 0) ? '코인이 부족합니다.' : '구매 처리 도중 오류가 발생하였습니다.');
                    }
                }
                $app['db']->commit();
            } catch (Exception $e) {
                $message = $e->getMessage();
                $app['db']->rollback();
                $r = false;
            }
            return $app->json(array('success' => ($r === true), 'message' => $message, 'coin_balance' => $user_coin_balance));
        } else {    // 비공개, 잘못된 접근
            return $app->json(array('success' => false, 'message' => 'Access Denied'));
        }
    }

    /*
     * Part Like / Unlike
     */
    public function userLikePart(Application $app, $device_id, $p_id)
    {
        $p = Part::get($p_id);
        if ($p == false) {
            return $app->json(array('success' => false));
        }

        $r = UserPartLike::like($device_id, $p_id);
        $like_count = UserPartLike::getLikeCount($p_id);
        return $app->json(array('success' => ($r === 1), 'like_count' => $like_count));
    }

    public function userUnlikePart(Application $app, $device_id, $p_id)
    {
        $r = UserPartLike::unlike($device_id, $p_id);
        $like_count = UserPartLike::getLikeCount($p_id);
        return $app->json(array('success' => ($r === 1), 'like_count' => $like_count));
    }

    /*
     * User Interest
     */
    public function userInterestList(Application $app, $device_id)
    {
        $b_ids = UserInterest::getList($device_id);
        $list = Book::getListByIds($b_ids, true);
        return $app->json($list);
    }

    public function getUserInterest(Application $app, $device_id, $b_id)
    {
        $r = UserInterest::get($device_id, $b_id);
        return $app->json(array('success' => $r));
    }

    public function setUserInterest(Application $app, $device_id, $b_id)
    {
        $r = UserInterest::set($device_id, $b_id);
        return $app->json(array('success' => $r));
    }

    public function clearUserInterest(Application $app, $device_id, $b_id)
    {
        $r = UserInterest::clear($device_id, $b_id);
        return $app->json(array('success' => $r));
    }

    /*
     * StoryPlusBook
     */
    public function storyPlusBookList(Application $app)
    {
        $book = $app['cache']->fetch(
            'storyplusbook_list',
            function () {
                return StoryPlusBook::getOpenedBookList();
            },
            60 * 10
        );
        return $app->json($book);
    }

    public function storyPlusBookDetail(Request $req, Application $app, $b_id)
    {
        $book = StoryPlusBook::get($b_id);
        $intro = StoryPlusBookIntro::getListByBid($b_id);
        $comment = StoryPlusBookComment::getList($b_id);

        $info = array(
            'book_detail' => $book,
            'intro' => $intro,
            'comment' => $comment
        );
        return $app->json($info);
    }

    /*
     * StoryPlusBook Like / UnLike
     */
    public function userLikeStoryPlusBook(Application $app, $device_id, $b_id)
    {
        $b = StoryPlusBook::get($b_id);
        if ($b == false) {
            return $app->json(array('success' => false));
        }

        $r = UserStoryPlusBookLike::like($device_id, $b_id);
        $like_count = UserStoryPlusBookLike::getLikeCount($b_id);
        return $app->json(array('success' => ($r === 1), 'like_count' => $like_count));
    }

    public function userUnlikeStoryPlusBook(Application $app, $device_id, $b_id)
    {
        $r = UserStoryPlusBookLike::unlike($device_id, $b_id);
        $like_count = UserStoryPlusBookLike::getLikeCount($b_id);
        return $app->json(array('success' => ($r === 1), 'like_count' => $like_count));
    }

    /*
     * StoryPlusBook Comment
     */
    public function addStoryPlusBookComment(Request $req, Application $app, $b_id)
    {
        $device_id = $req->get('device_id');
        $comment = trim($req->get('comment'));
        $ip = ip2long($_SERVER['REMOTE_ADDR']);

        StoryPlusBookComment::add($b_id, $device_id, $comment, $ip);
        return $app->json(array('success' => 'true'));
    }

    public function storyPlusBookCommentList(Application $app, $b_id)
    {
        $comments = StoryPlusBookComment::getList($b_id);
        return $app->json($comments);
    }

    /*
     * StoryPlusBook Version
     */
    public function getStoryPlusBookVersion(Application $app)
    {
        return $app->json(array('version' => '1'));
    }

    /*
     * RidiStory Latest Version
     */
    public function getLatestVersion(Request $req, Application $app)
    {
        $platform = $req->get('platform');
        if (strcasecmp($platform, 'android') === 0) {
            $r = array(
                'version' => '3.22',
                'force' => false,
                'update_url' => 'http://play.google.com/store/apps/details?id=com.initialcoms.story',
                'description' => '리디스토리 최신 버전으로 업데이트 하시겠습니까?'
            );
            return $app->json($r);
        }

        return $app->json(array('error' => 'invalid platform'));
    }

    /*
     * Push Device
     */
    public function registerPushDevice(Application $app, Request $req)
    {
        $device_id = $req->get('device_id');
        $platform = $req->get('platform');
        $device_token = $req->get('device_token');

        if (strlen($device_id) == 0 || strlen($device_token) == 0 ||
            (strcmp($platform, 'iOS') != 0 && strcmp($platform, 'Android') != 0)
        ) {
            return $app->json(array('success' => false, 'reason' => 'invalid parameters'));
        }

        if (PushDevice::insertOrUpdate($device_id, $platform, $device_token)) {
            return $app->json(array('success' => true));
        } else {
            return $app->json(array('success' => false, 'reason' => 'Insert or Update error'));
        }
    }

    /*
     * Validate Download
     */
    public function validatePartDownload(Request $req, Application $app)
    {
        /*
         * 유료화 버전(v3)부터는 u_id를 추가적으로 받아서,
         * 닫혀 있는 책에 대해서 구매내역을 체크한다.
         */
        $u_id = $req->get('u_id', '0');
        if ($u_id) {
            $u_id = AES128::decrypt(Buyer::USER_ID_AES_SECRET_KEY, $u_id);
        }

        $p_id = $req->get('p_id');
        $store_id = $req->get('store_id');

        $part = Part::get($p_id);
        $book = Book::get($part['b_id']);

        $today = date('Y-m-d H:00:00');
        $is_ongoing = strtotime($today) >= strtotime($part['begin_date']) && strtotime($today) <= strtotime($part['end_date']);

        // 연재 진행 중 여부에 따라 분기
        if ($is_ongoing) {
            // 연재 중인 경우 (이전 버전 및 유료화 버전의 무료 연재중)
            $valid = Part::isOpenedPart($p_id, $store_id);
        } else {
            $is_locked = $book['is_active_lock'] && (strtotime($today) <= strtotime($part['begin_date']) && strtotime($today . ' + 14 days') >= $part['begin_date']);
            $is_completed = (strtotime($today) >= strtotime($book['end_date']) ? true : false);

            // Uid가 유효한 경우
            if (Buyer::isValidUid($u_id)) {
                if ($is_locked) {
                    // 잠겨져 있는 경우 -> 구매내역 확인
                    $valid = Buyer::hasPurchasedPart($u_id, $p_id);
                } else if ($is_completed) {
                    // 완결된 경우 -> ALL_FREE    : true
                    //          -> ALL_CHARGED : 구매내역 확인
                    if ($book['end_action_flag'] == Book::ALL_FREE) {
                        $valid = true;
                    } else if ($book['end_action_flag'] == Book::ALL_CHARGED) {
                        $valid = Buyer::hasPurchasedPart($u_id, $p_id);
                    } else {    // ALL_CLOSED, SALED_CLOSED : 비공개
                        $valid = false;
                    }
                } else {        // 잠겨져 있지도 않고, 완결도 아닌 경우 -> 비공개/잘못된 접근
                    $valid = false;
                }
            } else {            // Uid가 유효하지 않은 경우 -> 잘못된 접근
                $valid = false;
            }
        }

        // log
        $app['db']->insert('stat_download', array('p_id' => $p_id, 'is_success' => ($valid ? 1 : 0)));

        return $app->json(array('success' => $valid));
    }

    public function validateStoryPlusBookDownload(Request $req, Application $app)
    {
        $storyplusbook_id = $req->get('storyplusbook_id');
        $store_id = $req->get('store_id');

        // TODO: 더 strict하게 구현
        $book = StoryPlusBook::get($storyplusbook_id);
        $valid = ($book['store_id'] == $store_id);

        // log
        $app['db']->insert(
            'stat_download_storyplusbook',
            array('storyplusbook_id' => $storyplusbook_id, 'is_success' => ($valid ? 1 : 0))
        );

        return $app->json(array('success' => $valid));
    }

    /*
     * In App Billing
     */
    public function inAppProductList(Request $req, Application $app)
    {
        $sku_list = InAppBilling::getInAppProductList();
        return $app->json($sku_list);
    }

    /*
     * Shorten Url
     */
    public function shortenUrl(Request $req, Application $app, $id)
    {
        $type = $req->get('type');
        $store_id = '';
        if ($type === 'storyplusbook') {
            $b = StoryPlusBook::get($id);

            $today = date('Y-m-d H:00:00');
            if ($b['begin_date'] <= $today && $b['end_date'] >= $today) {
                $store_id = $b['store_id'];
            }
        } else {
            $p = new Part($id);
            if ($p->isOpened()) {
                $store_id = $p->getStoreId();
            }
        }

        if ($store_id) {
            $preview_url = 'http://preview.ridibooks.com/' . $store_id . '?mobile';
            $shorten_url = $this->_getShortenUrl($preview_url);
            return $app->json(array('url' => $shorten_url));
        }

        return $app->json(array('error' => 'unable to get shorten url'));
    }

    private function _getShortenUrl($target_url)
    {
        $url = "http://ridi.kr/yourls-api.php";
        $attachment = array(
            'signature' => 'bbd2b597f6',
            'action' => 'shorturl',
            'format' => 'json',
            'url' => $target_url,
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $attachment);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //to suppress the curl output
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $result = curl_exec($ch);
        curl_close($ch);

        $json_result = json_decode($result, true);

        return $json_result['shorturl'];
    }
}

