<?php

class IosPush {
	public static function createPartUpdateNotification($b_id) {
		return array(
			'type' => 'part_update',
			'book_id' => $b_id
		);
	}
	
	public static function sendPush($devices, $message, $notification) {
		$device_tokens = array();
		foreach ($devices as $i => $device) {
			$device_tokens[] = $device['device_token'];
		}
		
		list($error_code, $error_string) = self::doSendPush($device_tokens, $message, $notification);
		
		return array('error_code' => $error_code,
					'error_string' => $error_string);
	}
	
	/*
	 * 실제 전송
	 */
	private static function doSendPush($device_tokens, $message, $notification) {
		define('APNS_CERT_FILENAME', 'apns-dev.pem');
		define('APNS_CERT_PATH', dirname(__FILE__) . '/' . APNS_CERT_FILENAME);
		
	    $payload = array('aps' => array('alert' => $message,
								'badge' => 0,
								'sound' => 'default'),
				 		'datas' => $notification);
		$payload = json_encode($payload);	// $payload의 길이는 256byte 미만이어야함
		
		$stream_context = stream_context_create();
		stream_context_set_option($stream_context, 'ssl', 'local_cert', APNS_CERT_PATH);
		
		$apns = stream_socket_client('ssl://gateway.sandbox.push.apple.com:2195',
								$error, $error_string, 2, STREAM_CLIENT_CONNECT, $stream_context);
								
		if($apns) {
			foreach ($device_tokens as $device_token) {
				$apns_message = chr(0).chr(0).chr(32).pack('H*', str_replace(' ', '', $device_token)).chr(0).chr(strlen($payload)).$payload;
				fwrite($apns, $apns_message);
			}
			
			fclose($apns);
		}
		
		return array($error, $error_string);
	}
}

?>
