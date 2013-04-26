<?php

require_once dirname(__FILE__) . "/simple_apns_client.php";

class IosPush {
	const COMMAND_PUSH = 1; /* Payload command. */
	const DEVICE_BINARY_SIZE = 32;
	
	public static function createPartUpdateNotification($b_id) {
		return array(
			'type' => 'part_update',
			'book_id' => $b_id
		);
	}
	
	
	public static function createUrlNotification($url) {
		return array(
			'type' => 'url',
			'url' => $url
		);
	}
	
	public static function createNewBookNotification($b_id) {
		return array(
			'type' => 'new_book',
			'book_id' => $b_id
		);
	}
	
	public static function createLaunchAppNotification() {
		return array(
			'type' => 'launch_app',
		);
	}
	
	public static function sendPush($devices, $message, $notification) {
		$device_tokens = array();
		foreach ($devices as $i => $device) {
			$device_tokens[] = $device['device_token'];
		}
		
		$error_response_list = self::doSendPush($device_tokens, $message, $notification);
		
		return array('sent' => sizeof($devices),
					'error_response_list' => $error_response_list);
	}
	
	public static function getPayloadInJson($message, $notification) {
		$payload = array('aps' => array('alert' => $message,
								'badge' => 0,
								'sound' => 'default'),
				 		'data' => $notification);
		$payload = json_encode($payload);	// $payload의 길이는 256byte 미만이어야함
		
		return $payload;
	}
	
	/*
	 * 실제 전송
	 */
	private static function doSendPush($device_tokens, $message, $notification) {
		define('APNS_CERT_FILENAME', 'apns-distribution.pem');
		define('APNS_CERT_PATH', dirname(__FILE__) . '/' . APNS_CERT_FILENAME);
		
		
		$push = new SimpleApnsClient(SimpleApnsClient::ENVIRONMENT_PRODUCTION,
								APNS_CERT_PATH);
		
	    $payload = self::getPayloadInJson($message, $notification);
		$push->setPayload($payload);
		
		foreach ($device_tokens as $device_token) {
			$push->addDeviceToken($device_token);
		}
		
		$push->connect();
		$error_response_list = $push->send();
		$push->disconnect();
		
		return $error_response_list;
	}
}

?>
