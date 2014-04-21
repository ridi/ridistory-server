<?php

use Silex\Application;
use Silex\ControllerProviderInterface;
use Story\Util\AndroidPush;
use Story\Util\IosPush;
use Story\Util\PickDeviceResult;
use Story\Util\PushDevicePicker;
use Symfony\Component\HttpFoundation\Request;

class AdminPushNotificationControllerProvider implements ControllerProviderInterface
{
    const PUSH_TYPE_PART_UPDATE = 'part_update';
    const PUSH_TYPE_URL = 'url';

    public function connect(Application $app)
    {
        $admin = $app['controllers_factory'];

        $admin->get('part_update', array($this, 'pushPartUpdate'));
        $admin->get('url', array($this, 'pushUrl'));

        $admin->get('ios_payload_length/{type}', array($this, 'iOSPayloadLength'));

        $admin->get('notify/part_update', array($this, 'pushNotifyPartUpdate'));
        $admin->get('notify/url', array($this, 'pushNotifyUrl'));

        return $admin;
    }

    /**
     * View
     */
    public static function pushPartUpdate(Request $req, Application $app)
    {
        return $app['twig']->render('/admin/push_notification_part_update.twig');
    }

    public static function pushUrl(Request $req, Application $app)
    {
        return $app['twig']->render('/admin/push_notification_url.twig');
    }

    /**
     * Push Length Checker
     */
    public static function iOSPayloadLength(Request $req, Application $app, $type)
    {
        $message = $req->get('message');

        $notification_ios = null;
        $payload_length = 0;

        if ($type == self::PUSH_TYPE_PART_UPDATE) {
            $b_id = $req->get('b_id');
            $notification_ios = IosPush::createPartUpdateNotification($b_id);
        } else if ($type == self::PUSH_TYPE_URL) {
            $url = $req->get('url');
            $notification_ios = IosPush::createUrlNotification($url);
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
    public static function pushNotifyPartUpdate(Request $req, Application $app)
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
        $os_type = $req->get('os_type');
        $url = $req->get('url');
        $message = $req->get('message');

        if (empty($os_type) || empty($url) || empty($message)) {
            return 'not all required fields are filled';
        }

        if ($os_type == PickDeviceResult::PLATFORM_ANDROID) {
            $platform = PickDeviceResult::PLATFORM_ANDROID;
        } else if ($os_type == PickDeviceResult::PLATFORM_IOS) {
            $platform = PickDeviceResult::PLATFORM_IOS;
        } else {
            $platform = PickDeviceResult::PLATFORM_ALL;
        }

        $pick_result = PushDevicePicker::pickDevicesUsingPlatform($app['db'], $platform);
        $notification_android = AndroidPush::createUrlNotification($url, $message);
        $notification_ios = IosPush::createUrlNotification($url);

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
}
