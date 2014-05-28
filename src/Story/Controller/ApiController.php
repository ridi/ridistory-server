<?php
namespace Story\Controller;

use Exception;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Story\Entity\NoticeFactory;
use Story\Entity\RecommendedBookFactory;
use Story\Model\Buyer;
use Story\Model\CoinBilling;
use Story\Model\CoinProduct;
use Story\Util\AES128;
use Story\Util\IpChecker;
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
        $api->post('/buyer/inapp_data/save', array($this, 'saveInAppBillingData'));

        $api->get('/book/list', array($this, 'bookList'));
        $api->get('/book/completed_list', array($this, 'completedBookList'));
        $api->post('/book/purchased/list', array($this, 'purchasedBookList'));
        $api->post('/book/purchased/{b_id}', array($this, 'purchasedBookDetail'));
        $api->get('/book/{b_id}', array($this, 'bookDetail'));
        $api->post('/book/{b_id}/buy', array($this, 'buyBookPart'));

        $api->get('/recommended_book/{b_id}', array($this, 'recommendedBookDetail'));

        $api->get('/part/{p_id}', array($this, 'partDetail'));

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
        $api->get('/latest_notice', array($this, 'getLatestNotice'));

        $api->get('/push_device/register', array($this, 'registerPushDevice'));

        $api->get('/validate_download', array($this, 'validatePartDownload'));
        $api->get('/validate_storyplusbook_download', array($this, 'validateStoryPlusBookDownload'));

        /*
         * 4.01, 4.02 버전 사용자들이, /inapp_proudct/list 주소로 API를 호출
         * TODO: 추후에 /inapp_product/list 제거
         */
        $api->get('/inapp_product/list', array($this, 'coinProductList'));
        $api->get('/coin_product/list', array($this, 'coinProductList'));

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

        if ($google_id && $token) {
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
                    $buyer = Buyer::getByUids(array($id));
                }
                $buyer['id'] = AES128::encrypt(Buyer::USER_ID_AES_SECRET_KEY, $buyer['id']);
            }

            if (empty($buyer)) {
                trigger_error('Failed verify google account. | Ori: ' . $google_id . ' / Server: ' . $server_google_id . ' (Verified: ' . $is_verified_email . ')', E_USER_ERROR);
            }

            return $app->json($buyer);
        }

        return null;
    }

    public function getBuyerCoinBalance(Request $req, Application $app)
    {
        $coin_balance = 0;

        $u_id = $req->get('u_id', null);
        if ($u_id) {
            $u_id = AES128::decrypt(Buyer::USER_ID_AES_SECRET_KEY, $u_id);
            if (Buyer::isValidUid($u_id)) {
                $coin_balance = Buyer::getCoinBalance($u_id);
            }
        }

        return $app->json(compact("coin_balance"));
    }

    public function saveInAppBillingData(Request $req, Application $app)
    {
        $inputs = $req->request->all();
        if ($inputs['u_id']) {
            $inputs['u_id'] = AES128::decrypt(Buyer::USER_ID_AES_SECRET_KEY, $inputs['u_id']);
            if (!Buyer::isValidUid($inputs['u_id'])) {
                return $app->json(array('success' => false, 'message' => '회원 정보를 찾을 수 없습니다.'));
            }
        } else {
            return $app->json(array('success' => false, 'message' => '회원 정보를 찾을 수 없습니다.'));
        }

        $iab_data = CoinBilling::getInAppBillingBindIfNotNull($inputs);
        $r = CoinBilling::saveBillingHistory($iab_data, CoinProduct::TYPE_INAPP);
        if ($r) {
            return $app->json(array('success' => true, 'message' => '성공'));
        } else {
            return $app->json(array('success' => false, 'message' => '결제 정보에 오류가 있습니다.'));
        }
    }

    public function addBuyerCoin(Request $req, Application $app)
    {
        $inputs = $req->request->all();
        if ($inputs['u_id']) {
            $inputs['u_id'] = AES128::decrypt(Buyer::USER_ID_AES_SECRET_KEY, $inputs['u_id']);
            if (!Buyer::isValidUid($inputs['u_id'])) {
                return $app->json(array('success' => false, 'message' => '회원 정보를 찾을 수 없습니다.'));
            }
        } else {
            return $app->json(array('success' => false, 'message' => '회원 정보를 찾을 수 없습니다.'));
        }

        /**
         * @var $v Api Version
         *
         * v1 : Save InAppBilling data when done Consume (Android RidiStory Ver <= 4.12)
         * v2 : Save InAppBilling data when done Purchase (Android RidiStory Ver > 4.12)
         */
        $v = intval($req->get('v', '1'));

        // 결제 수단
        $payment = isset($inputs['buy_method']) ? $inputs['buy_method'] : CoinProduct::TYPE_INAPP;

        // v1 에서는 여기서 결제정보를 저장
        // v2 에서는 결제완료 후, save api를 호출해서 따로 저장
        if ($v == 1 && $payment == CoinProduct::TYPE_INAPP) {
            $iab_data = CoinBilling::getInAppBillingBindIfNotNull($inputs);
            $r = CoinBilling::saveBillingHistory($iab_data, CoinProduct::TYPE_INAPP);
            if (!$r) {
                return $app->json(array('success' => false, 'message' => '결제 정보에 오류가 있습니다.'));
            }
        }

        // 트랜잭션 시작 (결제 정보 저장 + 코인 추가)
        $app['db']->beginTransaction();
        try {
            // 결제 수단 체크
            if ($payment != CoinProduct::TYPE_INAPP && $payment != CoinProduct::TYPE_RIDICASH) {
                throw new Exception('존재하지 않는 결제 수단입니다');
            }

            // 결제 검증
            $r = CoinBilling::verify($inputs, $payment);
            if (!$r) {
                throw new Exception('결제 도중 오류가 발생하였습니다');
            }

            if ($payment == CoinProduct::TYPE_INAPP) {
                $purchase_data = json_decode($inputs['purchase_data'], true);
                $sku = $purchase_data['productId'];
                $coin_source = Buyer::COIN_SOURCE_IN_INAPP;
            } else {
                $sku = $inputs['sku'];
                $coin_source = Buyer::COIN_SOURCE_IN_RIDI;
            }

            $coin_info = CoinProduct::getCoinProductBySkuAndType($sku, $payment);

            // 코인 추가
            $r = Buyer::addCoin($inputs['u_id'], ($coin_info['coin_amount'] + $coin_info['bonus_coin_amount']), $coin_source);
            if ($r) {
                $coin_amount = Buyer::getCoinBalance($inputs['u_id']);
                $result = array('success' => true, 'message' => '성공', 'coin_balance' => $coin_amount);;
            } else {
                throw new Exception('코인을 충전하는 도중 오류가 발생하였습니다.');
            }

            $app['db']->commit();
        } catch (Exception $e) {
            $app['db']->rollback();
            $result = array('success' => false, 'message' => $e->getMessage());
        }

        return $app->json($result);
    }

    /*
     * Book
     */
    public function bookList(Request $req, Application $app)
    {
        /**
         * @var $v Api Version
         *
         * v1 : Exclude Adult (deprecated, not working)
         * v2 : Include Adult
         * v3 : Use Lock Function
         */
        $v = intval($req->get('v', '1'));

	    // 해외 IP인 경우는 앱에서 실명인증을 처리하지 않기 위해
	    $ignore_adult_only = (IpChecker::isKoreanIp($_SERVER['REMOTE_ADDR']) == false) ? 1 : 0;

        $cache_key = 'book_list_' . $v . '_' . $ignore_adult_only;
        $book = $app['cache']->fetch(
            $cache_key,
            function () use ($ignore_adult_only) {
                return Book::getOpenedBookList($ignore_adult_only);
            },
            60 * 10
        );
        return $app->json($book);
    }

    public function completedBookList(Request $req, Application $app)
    {
        // 해외 IP인 경우는 앱에서 실명인증을 처리하지 않기 위해
        $ignore_adult_only = (IpChecker::isKoreanIp($_SERVER['REMOTE_ADDR']) == false) ? 1 : 0;

        $cache_key = 'completed_book_list_' . $ignore_adult_only;
        $book = $app['cache']->fetch(
            $cache_key,
            function () use ($ignore_adult_only) {
                return Book::getCompletedBookList($ignore_adult_only);
            },
            60 * 10
        );
        return $app->json($book);
    }

    public function purchasedBookList(Request $req, Application $app)
    {
        $u_id = $req->get('u_id', null);
        if ($u_id) {
            $u_id = AES128::decrypt(Buyer::USER_ID_AES_SECRET_KEY, $u_id);
            if (!Buyer::isValidUid($u_id)) {
                return $app->json(array('success' => false, 'message' => '회원 정보를 찾을 수 없습니다.'));
            }
        } else {
            return $app->json(array('success' => false, 'message' => '회원 정보를 찾을 수 없습니다.'));
        }

        $cache_key = 'purchased_book_list_' . $u_id;
        $book = $app['cache']->fetch(
            $cache_key,
            function () use ($u_id) {
                $purchases = Buyer::getWholePurchasedList($u_id);

                $b_ids = array();
                foreach ($purchases as $purchase) {
                    $part = Part::get($purchase['p_id']);
                    if (!in_array($part['b_id'], $b_ids, true)) {
                        array_push($b_ids, $part['b_id']);
                    }
                }
                return Book::getListByIds($b_ids, true);
            },
            60 * 10
        );

        return $app->json($book);
    }

    public function purchasedBookDetail(Request $req, Application $app, $b_id)
    {
        $book = Book::get($b_id);
        if ($book == false) {
            return $app->json(array('success' => false, 'error' => 'no such book'));
        }

        $u_id = $req->get('u_id', null);
        if ($u_id) {
            $u_id = AES128::decrypt(Buyer::USER_ID_AES_SECRET_KEY, $u_id);
            $is_valid_uid = Buyer::isValidUid($u_id);
            if (!$is_valid_uid) {
                return $app->json(array('success' => false, 'error' => 'not valid user'));
            }
        } else {
            return $app->json(array('success' => false, 'error' => 'not valid parameter'));
        }

        $book['is_active_lock'] = 1;

        $is_completed = ($book['is_completed'] == 1 || strtotime($book['end_date']) < strtotime(date('Y-m-d H:i:s')) ? 1 : 0);
        $book['is_completed'] = $is_completed;

        $cache_key = 'purchased_part_list_' . $u_id . '_' . $b_id;
        $parts = $app['cache']->fetch(
            $cache_key,
            function () use ($u_id, $b_id) {
                $p_ids = Buyer::getPurchasedPartIdListByBid($u_id, $b_id, true);
                return Part::getListByIds($p_ids, true);
            },
            60 * 10
        );

        foreach ($parts as &$part) {
            $part['is_locked'] = 0;
            $part['is_purchased'] = 1;
            $part['last_update'] = ($part['begin_date'] == date('Y-m-d')) ? 1 : 0;
        }

        $book['parts'] = $parts;
        $book['has_recommended_books'] = false;
        $book['interest'] = false;

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
        $is_completed = ($book['is_completed'] == 1 || strtotime($book['end_date']) < strtotime(date('Y-m-d H:i:s')) ? 1 : 0);
        $book['is_completed'] = $is_completed;
        $end_action_flag = $book['end_action_flag'];
        $lock_day_term = $book['lock_day_term'];

        if ($is_completed) {
            $book['is_completed'] = 1;
        }

        $cache_key = 'part_list_' . $active_lock . '_' . intval($show_all) . '_' . $b_id;
        $parts = $app['cache']->fetch(
            $cache_key,
            function () use ($b_id, $active_lock, $is_completed, $end_action_flag, $lock_day_term) {
                return Part::getListByBid($b_id, true, $active_lock, $is_completed, $end_action_flag, $lock_day_term);
            },
            60 * 10
        );

        $is_valid_uid = false;
        $purchased_p_ids = null;
        // 유료화 버전(v3)이고, Uid가 유효할 경우 구매내역 받아옴.
        if ($v > 2) {
            $u_id = $req->get('u_id', null);
            if ($u_id) {
                $u_id = AES128::decrypt(Buyer::USER_ID_AES_SECRET_KEY, $u_id);
                $is_valid_uid = Buyer::isValidUid($u_id);
                if ($is_valid_uid) {
                    $purchased_p_ids = Buyer::getPurchasedPartIdListByBid($u_id, $b_id, false);
                }
            }
        }

        foreach ($parts as &$part) {
            // 1화가 아직 시작하지 않은 경우에는, '잠금' 도서 무시하고, 앱 내에서 Coming Soon 처리
            if ($part['seq'] <= 1 && strtotime($part['begin_date']) > strtotime(date('Y-m-d H:i:s'))) {
                $parts = array();
                break;
            }

            $part['last_update'] = ($part['begin_date'] == date('Y-m-d')) ? 1 : 0;

            $part['is_purchased'] = 0;
            if ($is_valid_uid) {
                foreach ($purchased_p_ids as $p_id) {
                    if ($p_id == $part['id']) {
                        $part['is_purchased'] = 1;
                        break;
                    }
                }
            }
        }

        $book['parts'] = $parts;
        $book['has_recommended_books'] = (RecommendedBookFactory::hasRecommendedBooks($b_id) > 0) ? true : false;

        $device_id = $req->get('device_id');
        $book['interest'] = ($device_id === null) ? false : UserInterest::hasInterestInBook($device_id, $b_id);

        return $app->json($book);
    }

    public function buyBookPart(Request $req, Application $app)
    {
        $u_id = $req->get('u_id', null);
        if ($u_id) {
            $u_id = AES128::decrypt(Buyer::USER_ID_AES_SECRET_KEY, $u_id);
            if (!Buyer::isValidUid($u_id)) {
                return $app->json(array('success' => 'false', 'message' => '회원 정보를 찾을 수 없습니다.'));
            }
        } else {
            return $app->json(array('success' => 'false', 'message' => '구글 서비스 사용에 동의해 주셔야 책을 볼 수 있습니다.'));
        }

        $p_id = $req->get('p_id');
        $is_free_request = $req->get('free_request');
        $part = Part::get($p_id);
        $book = Book::get($part['b_id']);

        $today = date("Y-m-d H:i:s");

        $is_completed = (strtotime($today) >= strtotime($book['end_date']) ? true : false);

        $is_free = (!$is_completed && (strtotime($part['begin_date']) <= strtotime($today) || $part['price'] == 0))
            || ($is_completed && (($book['end_action_flag'] == Book::ALL_CHARGED && $part['price'] == 0) || $book['end_action_flag'] == Book::ALL_FREE));

        $is_charged = (!$is_completed && (strtotime($part['begin_date']) > strtotime($today) && strtotime($part['begin_date']) <= strtotime($today . ' + ' . $book['lock_day_term'] . ' days')) && $part['price'] > 0)
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
                    if ($is_free_request) {
                        // 유료책인데, 무료책으로 요청이 들어온 경우
                        throw new Exception('이 책은 유료 책으로 전환되었습니다. 이전 버튼을 누른 뒤, 해당 책으로 다시 들어와주세요.');
                    }

                    $ph_id = Buyer::buyPart($u_id, $p_id, $part['price']);
                    if ($ph_id && $user_coin_balance >= $part['price']) {
                        $r = Buyer::reduceCoin($u_id, $part['price'], Buyer::COIN_SOURCE_OUT_BUY_PART, $ph_id);
                        if ($r) {
                            $message = '유료(성공)';
                            $user_coin_balance -= $part['price'];
                            $r = true;
                        } else {
                            throw new Exception('파트 구매 도중에 오류가 발생하였습니다.');
                        }
                    } else {
                        throw new Exception(($ph_id > 0) ? '코인이 부족합니다.' : '구매를 진행하는 도중에 오류가 발생하였습니다.');
                    }
                }

                // 구매목록 캐시 삭제
                // 캐시 삭제
                $app['cache']->delete('purchased_book_list_' . $u_id);
                $app['cache']->delete('purchased_part_list_' . $u_id . '_' . $part['b_id']);

                $app['db']->commit();
            } catch (Exception $e) {
                $message = $e->getMessage();
                $app['db']->rollback();
                $r = false;
            }
            return $app->json(array('success' => ($r === true), 'message' => $message, 'coin_balance' => $user_coin_balance));
        } else {    // 비공개, 잘못된 접근
            return $app->json(array('success' => false, 'message' => '잘못된 요청입니다. 이전 버튼을 눌러 책 목록 화면으로 이동한 뒤, 다시 시도해주세요.'));
        }
    }

    /*
     * Part
     */
    public function partDetail(Request $req, Application $app, $p_id)
    {
        $part = Part::get($p_id);
        if ($part == false) {
            return $app->json(array('success' => false, 'error' => '파트 ID에 해당하는 파트가 존재하지 않습니다.'));
        }

        $book = Book::get($part['b_id']);

        $part['b_title'] = $book['title'];
        return $app->json($part);
    }

    /*
     * Recommended Book
     */
    public function recommendedBookDetail(Request $req, Application $app, $b_id)
    {
        // Ridibooks Metadata
        $ch =curl_init();
        curl_setopt($ch, CURLOPT_URL, STORE_API_BASE_URL . '/api/book/metadata?b_id=' . $b_id);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);

        $response = curl_exec($ch);
        curl_close($ch);

        // 이미 JSON 형식의 데이터로 넘어옴.
        return $response;
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

        $today = date('Y-m-d H:i:s');
        foreach ($list as $key => &$book) {
            $is_active_lock = $book['is_active_lock'];

            // 연재 시작일 이전의 책 제외.
            if (!$is_active_lock && strtotime($book['begin_date']) > strtotime($today)) {
                unset($list[$key]);
            }

            // 완결 후, 게시종료된 책 제외.
            if (strtotime($book['end_date']) < strtotime($today) && $book['end_action_flag'] == Book::ALL_CLOSED) {
                unset($list[$key]);
            }
        }

        // ReIndex
        $list = array_values($list);

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
                'version' => '4.12',
                'force' => false,
                'update_url' => 'http://play.google.com/store/apps/details?id=com.initialcoms.story',
                'description' => '리디스토리를 최신 버전으로 업데이트 해주세요. 더욱 더 다양한 신간 연재 도서를 만나보실 수 있습니다.'
            );
            return $app->json($r);
        }

        return $app->json(array('error' => 'invalid platform'));
    }

    /*
     * Check Notice New
     */
    public function getLatestNotice(Application $app, Request $req)
    {
        $latest_notice = NoticeFactory::getLatestNotice(true);
        return $app->json(array('latest_notice_date' => $latest_notice->reg_date));
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
        $p_id = $req->get('p_id');
        $store_id = $req->get('store_id');

        $part = Part::get($p_id);
        $book = Book::get($part['b_id']);

        $today = date('Y-m-d H:i:s');
        $is_ongoing = strtotime($today) >= strtotime($part['begin_date']) && strtotime($today) <= strtotime($part['end_date']);

        // 연재중 여부에 따라 분기
        $valid = false;
        if ($is_ongoing) {
            // 연재 중인 경우 (이전 버전 및 유료화 버전의 무료 연재중)
            $valid = Part::isOpenedPart($p_id, $store_id);
        } else {
            $is_locked = $book['is_active_lock'] && ($part['price'] > 0) && (strtotime($today) <= strtotime($part['begin_date']) && strtotime($today . ' + ' . $book['lock_day_term'] . ' days') >= $part['begin_date']);
            $is_completed = (strtotime($today) >= strtotime($book['end_date']) ? true : false);

            /*
             * 유료화 버전(v3)부터는 u_id를 추가적으로 받아서,
             * 닫혀 있는 책에 대해서 구매내역을 체크한다.
             */
            $u_id = $req->get('u_id', null);
            if ($u_id) {
                $u_id = AES128::decrypt(Buyer::USER_ID_AES_SECRET_KEY, $u_id);
            }

            // Uid가 유효한 경우
            if (Buyer::isValidUid($u_id)) {
                if ($is_completed) {
                    // 완결된 경우 -> ALL_FREE                 : true
                    //          -> ALL_CHARGED              : 구매내역 확인
                    //          -> SALES_CLOSED, ALL_CLOSED : 구매내역 확인 (+ 해당 책의 파트 구매내역이 있으면 첫 회 제공)
                    if ($book['end_action_flag'] == Book::ALL_FREE) {
                        $valid = true;
                    } else if ($book['end_action_flag'] == Book::ALL_CHARGED) {
                        if ($part['price'] > 0) {
                            // 구매내역 확인
                            $valid = Buyer::hasPurchasedPart($u_id, $p_id);
                        } else {
                            // 모두 잠금이지만, 가격이 공짜인 경우 다운로드 허가.
                            $valid = true;
                        }
                    } else if ($book['end_action_flag'] == Book::SALES_CLOSED || $book['end_action_flag'] == Book::ALL_CLOSED) {
                        // 구매내역 확인
                        $valid = Buyer::hasPurchasedPart($u_id, $p_id);

                        //TODO: 첫 회를 무료로 제공하는 것이 확정되면 추가. @유대열
                        // 첫 회를 구매한 적이 없지만, 해당 책의 파트 구매내역이 있으면, 첫 회 제공.
//                        if (!$valid && $part['seq'] == 1 && $part['price'] == 0) {
//                            $valid = Buyer::hasPurchasedPartInBook($u_id, $part['b_id']);
//                        }
                    }
                } else {
                    // 잠겨져 있는 경우 -> 구매내역 확인
                    if ($is_locked) {
                        $valid = Buyer::hasPurchasedPart($u_id, $p_id);
                    } else {
                        // 원래 잠금이어야 하는데, 무료라 강제 공개시킨 경우.
                        if ($part['price'] <= 0) {
                            $valid = true;
                        }
                    }
                }
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
     * Coin Product
     */
    public function coinProductList(Request $req, Application $app)
    {
        /**
         * @var $v Api Version
         *
         * v1 : Only Google InApp Products
         * v2 : InApp Products + RidiCash Products
         */
        $v = intval($req->get('v', '1'));
        if ($v > 1) {
            $type = CoinProduct::TYPE_ALL;
        } else {
            $type = CoinProduct::TYPE_INAPP;
        }
        $sku_list = CoinProduct::getCoinProductsByType($type);

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

