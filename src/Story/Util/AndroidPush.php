<?php
namespace Story\Util;

use Story\Model\PushDevice;

class AndroidPush
{
    const PUSH_TITLE = '리디스토리';

    public static function createInterestBookPartUpdateNotification($b_id, $message)
    {
        return array(
            'type' => 'part_update',
            'book_id' => $b_id,
            'title' => self::PUSH_TITLE,
            'message' => $message,
        );
    }

    public static function createInterestBookUrlNotification($url, $message)
    {
        return array(
            'type' => 'url',
            'title' => self::PUSH_TITLE,
            'message' => $message,
            'url' => $url
        );
    }

    public static function createNoticeNotification($url, $message)
    {
        // 공지사항 URL은 리디스토리 안드로이드 4.15버전부터 지원.
        return array(
            'type' => 'notice',
            'title' => self::PUSH_TITLE,
            'message' => $message,
            'url' => $url
        );
    }

    /**
     * GCM은 한 번에 1000대 까지만 발송할 수 있으므로, 끊어서 전송하고 결과를 취합해서 리턴
     */
    public static function sendPush($devices, $notification)
    {
        define('GCM_MULTICAST_SIZE', 1000);

        $result = array();
        $partial_devices = array();
        foreach ($devices as $i => $device) {
            $partial_devices[] = $device;
            if (count($partial_devices) == GCM_MULTICAST_SIZE || $i == count($devices) - 1) {
                $partial_result = self::sendPushPartial($partial_devices, $notification);
                $result[] = $partial_result;
                $partial_devices = array();
            }
        }

        return $result;
    }

    /**
     * GCM 보내고 결과에 따른 device_token remove/update 수행.
     * GCM 요청/응답을 DB에 로깅
     */
    private static function sendPushPartial($devices, $notification)
    {
        $device_tokens = array();
        foreach ($devices as $device) {
            $device_tokens[] = $device['device_token'];
        }

        list($req_json, $res_json) = self::doSendPush($device_tokens, $notification);

        global $app;
        /** @var $db \Doctrine\DBAL\Connection */
        $db = $app['db'];
        $db->insert(
            'log_push',
            array(
                'request' => $req_json,
                'response' => $res_json,
                'platform' => 'Android',
            )
        );

        $r = json_decode($res_json, true);
        $success = 0;
        $canonical = 0;
        $invalid = 0;
        foreach ($r['results'] as $i => $result) {
            if ($result['message_id'] != null) {
                $success++;
            }
            if (isset($result['registration_id'])) {
                $update_values = array(
                    'device_token' => $result['registration_id']
                );

                PushDevice::update($devices[$i]['id'], $update_values);
                $canonical++;
            }
            if (isset($result['error'])) {
                if ($result['error'] == 'InvalidRegistration' || $result['error'] == 'NotRegistered') {
                    PushDevice::deactivate($devices[$i]['id']);
                    $invalid++;
                }
            }
        }

        return array('success' => $success, 'canonical' => $canonical, 'invalid' => $invalid);
    }

    /*
     * 실제 전송
     */
    private static function doSendPush($device_tokens, $notification)
    {
        static $GOOGLE_API_KEY_FOR_GCM = "";

        $headers = array(
            'Authorization: key=' . $GOOGLE_API_KEY_FOR_GCM,
            'Content-Type: application/json'
        );

        // TODO: collapse_key
        $post_data = array(
            'data' => $notification,
            'collapse_key' => 'temp',
            'delay_while_idle' => true,
            'registration_ids' => $device_tokens
        );
        $post_json = json_encode($post_data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://android.googleapis.com/gcm/send');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_json);

        $result = curl_exec($ch);

        curl_close($ch);

        return array($post_json, $result);
    }
}
