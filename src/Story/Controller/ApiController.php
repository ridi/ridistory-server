<?php
namespace Story\Controller;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Story\Model\Buyer;
use Story\Model\RecommendBook;
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

        $api->get('/buyer/auth', array($this, 'buyerAuthGoogleAccount'));
        $api->get('/buyer/coin', array($this, 'buyerCoinBalance'));

        $api->get('/book/list', array($this, 'bookList'));
        $api->get('/book/completed_list', array($this, 'completedBookList'));
        $api->get('/book/{b_id}', array($this, 'book'));
        $api->get('/book/{b_id}/buy', array($this, 'bookBuy'));

        $api->get('/version/storyplusbook', array($this, 'versionStoryPlusBook'));

        $api->get('/storyplusbook/list', array($this, 'storyPlusBookList'));
        $api->get('/storyplusbook/{b_id}', array($this, 'storyPlusBook'));

        $api->get('/user/{device_id}/interest/list', array($this, 'userInterestList'));
        $api->get('/user/{device_id}/interest/{b_id}/set', array($this, 'userInterestSet'));
        $api->get('/user/{device_id}/interest/{b_id}/clear', array($this, 'userInterestClear'));
        $api->get('/user/{device_id}/interest/{b_id}', array($this, 'userInterestGet'));

        $api->get('/user/{device_id}/part/{p_id}/like', array($this, 'userPartLike'));
        $api->get('/user/{device_id}/part/{p_id}/unlike', array($this, 'userPartUnlike'));

        $api->get('/user/{device_id}/storyplusbook/{b_id}/like', array($this, 'userStoryPlusBookLike'));
        $api->get('/user/{device_id}/storyplusbook/{b_id}/unlike', array($this, 'userStoryPlusBookUnlike'));

        $api->get('/storyplusbook/{b_id}/comment/list', array($this, 'storyPlusBookCommentList'));
        $api->get('/storyplusbook/{b_id}/comment/add', array($this, 'storyPlusBookCommentAdd'));

        $api->get('/push_device/register', array($this, 'pushDeviceRegister'));

        $api->get('/latest_version', array($this, 'latestVersion'));

        $api->get('/validate_download', array($this, 'validatePartDownload'));
        $api->get('/validate_storyplusbook_download', array($this, 'validateStoryPlusBookDownload'));

        $api->get('/shorten_url/{id}', array($this, 'shortenUrl'));

        return $api;
    }

    public function buyerAuthGoogleAccount(Request $req, Application $app)
    {
        $google_id = $req->get('google_account');
        $token = $req->get('token');

        // Google Services Auth
        $ch =curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/oauth2/v1/userinfo?access_token=".$token);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        $buyer = null;
        if (strpos($response, "200 OK") == true)
        {
            $buyer = Buyer::getByGoogleAccount($google_id);
            if ($buyer == null)
            {
                $id = Buyer::add($google_id);
                $buyer = Buyer::get($id);
            }
        }

        return $app->json($buyer);
    }

    public function buyerCoinBalance(Request $req, Application $app)
    {
        $u_id = $req->get('u_id');
        $coin_balance = Buyer::getCoinBalance($u_id);
        return $app->json(array('coin_balance' => $coin_balance));
    }

    public function versionStoryPlusBook(Application $app)
    {
        return $app->json(array('version' => '1'));
    }

    public function storyPlusBookCommentAdd(Request $req, Application $app, $b_id)
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

    public function storyPlusBook(Request $req, Application $app, $b_id)
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

    public function bookBuy(Request $req, Application $app)
    {
        $u_id = $req->get('u_id');
        if (!Buyer::validateUid($u_id)) {
            return $app->json(array('success' => 'false', 'message' => 'Wrong UID'));
        }
        $p_id = $req->get('p_id');
        $part = Part::get($p_id);
        $book = Book::get($part['b_id']);

        $today = date("Y-m-d H:i:s");

        $is_completed = (strtotime($today) >= strtotime($book['end_date']) ? true : false);

        $is_free = (!$is_completed && strtotime($part['begin_date']) <= strtotime($today)) || ($is_completed && (($book['end_action_flag'] == 'ALL_CHARGED' && $part['price'] == 0) || $book['end_action_flag'] == 'ALL_FREE'));
        $is_charged = (!$is_completed && (strtotime($part['begin_date']) > strtotime($today) && strtotime($part['begin_date']) <= strtotime($today . " + 14 days"))) || ($is_completed && ($book['end_action_flag'] == 'ALL_CHARGED' && $part['price'] > 0));


        // 무료일 경우, 구매내역에 있으면 무시하고 다운로드
        // 무료일 경우, 구매내역에 없으면, 구매내역 등록하고 다운로드

        // 유료일 경우, 구매내역에 있으면 무시하고 다운로드
        // 유료일 경우, 구매내역에 없으면, 구매 가능한지 여부 구하기
        //                          구매 불가능하면 -> 오류 (코인 부족 등)
        //                          구매 가능하면 -> 구매내역 등록하고 다운로드

        // 비공개일 경우, 오류

        if ($is_free && !$is_charged) { // 무료
            Buyer::buyPart($u_id, $p_id, 0);
            return $app->json(array('success' => 'true', 'message' => 'free'));
        } else if (!$is_free && $is_charged) {  // 유료
            $user_coin_balance = Buyer::getCoinBalance($u_id);
            if ($user_coin_balance >= $part['price']) {
                $ph_id = Buyer::buyPart($u_id, $p_id, $part['price']);
                if ($ph_id) {
                    $r = Buyer::useCoin($u_id, $part['price'], 'USE', $ph_id);
                } else {
                    $r = true;
                }
                $user_coin_balance = Buyer::getCoinBalance($u_id);
                return $app->json(array('success' => ($r === true), 'message' => 'charged', 'coin_balance' => $user_coin_balance));
            } else {    // 코인 부족
                return $app->json(array('success' => 'false', 'message' => 'no coin'));
            }
        } else {    // 비공개, 잘못된 접근
            return $app->json(array('success' => 'false', 'message' => 'access denied'));
        }
    }

    public function bookList(Request $req, Application $app)
    {
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
     */
    public function book(Request $req, Application $app, $b_id)
    {
        $book = Book::get($b_id);
        if ($book == false) {
            return $app->json(array('success' => false, 'error' => 'no such book'));
        }

        /**
         * @var $v App Api Version (cf. v > 2 : Use Lock Func)
         */
        $v = intval($req->get('v', '1'));
        $active_lock = ($v > 2) && ($book['is_active_lock'] == 1);

        // 완결되었고, 종료 후 액션이 모두 공개 혹은 모두 잠금이면 파트 모두 보임
        $show_all = false;
        $is_completed = ($book['is_completed'] == 1 || strtotime($book['end_date']) < strtotime('now') ? 1 : 0);
        $show_from_end = ($book['end_action_flag'] == 'ALL_FREE' || $book['end_action_flag'] == 'ALL_CHARGED' ? 1 : 0);
        if ($is_completed && $show_from_end) {
            $show_all = true;
        }

        $cache_key = 'part_list_' . $active_lock . '_' . $show_all . '_';
        $parts = $app['cache']->fetch(
            $cache_key . $b_id,
            function () use ($b_id, $active_lock, $show_all) {
                return Part::getListByBid($b_id, true, $active_lock, $show_all);
            },
            60 * 10
        );

        $purchased_flags = null;
        $u_id = intval($req->get('u_id', '0'));
        if (Buyer::validateUid($u_id) > 0) {
            $purchased_flags = Buyer::getPurchasedListByParts($u_id, $parts);
        }
        foreach ($parts as &$part) {
            $part['last_update'] = ($part['begin_date'] == date('Y-m-d')) ? 1 : 0;

            if ($show_all && $book['end_action_flag'] == 'ALL_FREE') {
                $part['is_locked'] = 0;
            } else if ($show_all && $book['end_action_flag'] == 'ALL_CHARGED') {
                $part['is_locked'] = 1;
            }

            $part['is_purchased'] = 0;
            foreach ($purchased_flags as $pf) {
                if ($pf['p_id'] == $part['id']) {
                    $part['is_purchased'] = 1;
                    unset($pf);
                    break;
                }
            }
        }

        $book['parts'] = $parts;

        $recommend_books = $app['cache']->fetch(
            'recommend_book_list_' . $b_id,
            function () use ($b_id) {
                return RecommendBook::getRecommendBookListByBid($b_id);
            },
            60 * 10
        );

        $book['recommend_books'] = $recommend_books;

        $device_id = $req->get('device_id');
        $book['interest'] = ($device_id === null) ? false : UserInterest::hasInterestInBook($device_id, $b_id);

        return $app->json($book);
    }

    public function userInterestSet(Application $app, $device_id, $b_id)
    {
        $r = UserInterest::set($device_id, $b_id);
        return $app->json(array('success' => $r));
    }

    public function userInterestClear(Application $app, $device_id, $b_id)
    {
        $r = UserInterest::clear($device_id, $b_id);
        return $app->json(array('success' => $r));
    }

    public function userInterestGet(Application $app, $device_id, $b_id)
    {
        $r = UserInterest::get($device_id, $b_id);
        return $app->json(array('success' => $r));
    }

    public function userInterestList(Application $app, $device_id)
    {
        $b_ids = UserInterest::getList($device_id);
        $list = Book::getListByIds($b_ids, true);
        return $app->json($list);
    }


    public function userPartLike(Application $app, $device_id, $p_id)
    {
        $p = Part::get($p_id);
        if ($p == false) {
            return $app->json(array('success' => false));
        }

        $r = UserPartLike::like($device_id, $p_id);
        $like_count = UserPartLike::getLikeCount($p_id);
        return $app->json(array('success' => ($r === 1), 'like_count' => $like_count));
    }

    public function userPartUnlike(Application $app, $device_id, $p_id)
    {
        $r = UserPartLike::unlike($device_id, $p_id);
        $like_count = UserPartLike::getLikeCount($p_id);
        return $app->json(array('success' => ($r === 1), 'like_count' => $like_count));
    }

    public function userStoryPlusBookLike(Application $app, $device_id, $b_id)
    {
        $b = StoryPlusBook::get($b_id);
        if ($b == false) {
            return $app->json(array('success' => false));
        }

        $r = UserStoryPlusBookLike::like($device_id, $b_id);
        $like_count = UserStoryPlusBookLike::getLikeCount($b_id);
        return $app->json(array('success' => ($r === 1), 'like_count' => $like_count));
    }

    public function userStoryPlusBookUnlike(Application $app, $device_id, $b_id)
    {
        $r = UserStoryPlusBookLike::unlike($device_id, $b_id);
        $like_count = UserStoryPlusBookLike::getLikeCount($b_id);
        return $app->json(array('success' => ($r === 1), 'like_count' => $like_count));
    }


    public function pushDeviceRegister(Application $app, Request $req)
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

    public function latestVersion(Request $req, Application $app)
    {
        $platform = $req->get('platform');
        if (strcasecmp($platform, 'android') === 0) {
            $r = array(
                'version' => '0.9',
                'force' => false,
                'update_url' => 'http://play.google.com/store/apps/details?id=com.initialcoms.story',
                'description' => '리디스토리 최신 버전으로 업데이트 하시겠습니까?'
            );
            return $app->json($r);
        }

        return $app->json(array('error' => 'invalid platform'));
    }

    public function validatePartDownload(Request $req, Application $app)
    {
        $p_id = $req->get('p_id');
        $store_id = $req->get('store_id');

        $valid = Part::isOpenedPart($p_id, $store_id);

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

