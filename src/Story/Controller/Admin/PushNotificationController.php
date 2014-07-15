<?php
namespace Story\Controller\Admin;

use Exception;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Story\Model\Buyer;
use Story\Util\AndroidPush;
use Story\Util\IosPush;
use Story\Util\PickDeviceResult;
use Story\Util\PushDevicePicker;
use Symfony\Component\HttpFoundation\Request;

class PushNotificationController implements ControllerProviderInterface
{
    //TODO: iOS 서비스 종료로 인하여, 푸시메세지 발송 부분 삭제. 추후에 iOS 서비스 재개하면 다시 살릴 필요 있음. (Rev. 511)

    const PUSH_TYPE_INTEREST_PART_UPDATE = 'interest_book_part_update';
    const PUSH_TYPE_INTEREST_URL = 'interest_book_url';
    const PUSH_TYPE_NOTICE = 'notice';

    public function connect(Application $app)
    {
        $admin = $app['controllers_factory'];

        $admin->get('interest_book/part_update', array($this, 'pushInterestBookPartUpdate'));
        $admin->get('interest_book/url', array($this, 'pushInterestBookUrl'));
        $admin->get('notice', array($this, 'pushNotice'));

        $admin->get('ios_payload_length/{type}', array($this, 'iOSPayloadLength'));

        $admin->post('notify/interest_book/part_update', array($this, 'pushNotifyInterestBookPartUpdate'));
        $admin->post('notify/interest_book/url', array($this, 'pushNotifyInterestBookUrl'));
        $admin->post('notify/notice', array($this, 'pushNotifyNotice'));;

        return $admin;
    }

    /**
     * View
     */
    public static function pushInterestBookPartUpdate(Request $req, Application $app)
    {
        return $app['twig']->render('/admin/push_notification/interest_book_part_update.twig');
    }

    public static function pushInterestBookUrl(Request $req, Application $app)
    {
        return $app['twig']->render('/admin/push_notification/interest_book_url.twig');
    }

    public static function pushNotice(Request $req, Application $app)
    {
        return $app['twig']->render('/admin/push_notification/notice.twig', array('user_list' => null));
    }

    /**
     * Push Length Checker
     */
    public static function iOSPayloadLength(Request $req, Application $app, $type)
    {
        $message = $req->get('message');

        $notification_ios = null;
        $payload_length = 0;

        if ($type == self::PUSH_TYPE_INTEREST_PART_UPDATE) {
            $b_id = $req->get('b_id');
            $notification_ios = IosPush::createInterestBookPartUpdateNotification($b_id);
        } else if ($type == self::PUSH_TYPE_INTEREST_URL) {
            $url = $req->get('url');
            $notification_ios = IosPush::createInterestBookUrlNotification($url);
        } else if ($type == self::PUSH_TYPE_NOTICE) {
            $url = $req->get('url');
            $notification_ios = IosPush::createNoticeNotification($url);
        }

        if ($notification_ios) {
            $payload = IosPush::getPayloadInJson($message, $notification_ios);
            $payload_length = strlen($payload);
        }

        return $app->json(array("payload_length" => $payload_length));
    }

    /**
     * Push Notification
     */
    public static function pushNotifyInterestBookPartUpdate(Request $req, Application $app)
    {
        $recipient = $req->get('recipient');
        $b_id = $req->get('b_id');
        $message = $req->get('message');

        if (empty($b_id) || empty($message)) {
            return 'not all required fields are filled';
        }

        $pick_result = PushDevicePicker::pickDevicesUsingInterestBook($app['db'], $recipient);
        $notification_android = AndroidPush::createInterestBookPartUpdateNotification($b_id, $message);

        $result = self::_push($pick_result, $notification_android);

        $flash_message = self::getResultFlashMessage('[책 ID: ' . $recipient . '] 푸시 메세지가 성공적으로 발송되었습니다.', $result);
        $app['session']->getFlashBag()->add('alert', array('success' => $flash_message));

        return $app->redirect('/admin/push/interest_book/part_update');
    }

    public static function pushNotifyInterestBookUrl(Request $req, Application $app)
    {
        $recipient = $req->get('recipient');
        $url = $req->get('url');
        $message = $req->get('message');

        if (empty($url) || empty($message)) {
            return 'not all required fields are filled';
        }

        $pick_result = PushDevicePicker::pickDevicesUsingInterestBook($app['db'], $recipient);
        $notification_android = AndroidPush::createInterestBookUrlNotification($url, $message);

        $result = self::_push($pick_result, $notification_android);

        $flash_message = self::getResultFlashMessage('[책 ID: ' . $recipient . '] 푸시 메세지가 성공적으로 발송되었습니다.', $result);
        $app['session']->getFlashBag()->add('alert', array('success' => $flash_message));

        return $app->redirect('/admin/push/interest_book/url');
    }

    public static function pushNotifyNotice(Request $req, Application $app)
    {
        $user_list = $req->get('user_list');
        $url = $req->get('url', null);
        $message = $req->get('message', null);

        $url = empty($url) ? null : $url;
        $message = empty($message) ? null : $message;

        $push_token_exist_u_ids = null;
        $push_token_inexist_u_ids = null;

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

        try {
            // 정보 입력 검사
            if (empty($u_ids) || (empty($url) && empty($message))) {
                throw new Exception('정보를 모두 정확히 입력해주세요.');
            }

            // 입력한 회원 정보 중에, 유효하지 않은 회원이 있는지를 검사
            $invalid_u_ids = Buyer::verifyUids($u_ids);
            if (!empty($invalid_u_ids)) {
                throw new Exception('회원 계정 정보가 정확하지 않습니다. (' . implode(' / ', $invalid_u_ids) . ')');
            }

            // 입력한 회원 정보 중에, Push Device Token이 있는 회원들만 푸시 전송.
            $push_token_inexist_u_ids = Buyer::checkIfHavePushDeviceTokens($u_ids);
            $push_token_exist_u_ids = array_diff($u_ids, $push_token_inexist_u_ids);

            if (empty($push_token_exist_u_ids)) {
                throw new Exception('Push Device Token이 있는 계정이 존재하지 않습니다.');
            }

            $pick_result = PushDevicePicker::pickDevicesUsingUids($app['db'], $push_token_exist_u_ids);
            $notification_andorid = AndroidPush::createNoticeNotification($url, $message);

            $result = self::_push($pick_result, $notification_andorid);
            $flash_message = self::getResultFlashMessage('[공지사항] 푸시 메세지가 성공적으로 발송되었습니다.', $result);

            $app['session']->getFlashBag()->add('alert', array('success' => $flash_message));

            if (!empty($push_token_inexist_u_ids)) {
                $app['session']->getFlashBag()->add('alert', array('error' => '기기 정보가 존재하지 않는 회원(유저ID): ' . implode(', ', $push_token_inexist_u_ids)));
            }
        } catch (\Exception $e) {
            $app['session']->getFlashBag()->add('alert', array('error' => $e->getMessage()));
        }

        return $app->redirect('/admin/push/notice');
    }

    private static function _push(PickDeviceResult $pick_result, $notification_android)
    {
        $result_android = AndroidPush::sendPush($pick_result->getAndroidDevices(), $notification_android);

        return array(
            'Android' => $result_android
        );
    }

    private static function getResultFlashMessage($init_message, $results)
    {
        $success = 0;
        $invalid = 0;
        foreach($results['Android'] as $result) {
            $success += $result['success'];
            $invalid += $result['invalid'];
        }

        $flash_message = $init_message;
        $flash_message .= ' (성공: ' . $success . '건)';
        $flash_message .= ' (실패: ' . $invalid . '건)';

        return $flash_message;
    }
}
