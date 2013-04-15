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
		
		list($error_code, $error_string, $error_response_list) =
					self::doSendPush($device_tokens, $message, $notification);
		
		return array('sent' => sizeof($devices),
					'error_code' => $error_code,
					'error_string' => $error_string,
					'error_response_list' => $error_response_list);
	}
	
	public static function getPayloadInJson($message, $notification) {
		$payload = array('aps' => array('alert' => $message,
								'badge' => 0,
								'sound' => 'default'),
				 		'datas' => $notification);
		$payload = json_encode($payload);	// $payload의 길이는 256byte 미만이어야함
		
		return $payload;
	}
	
	/*
	 * 실제 전송
	 */
	private static function doSendPush($device_tokens, $message, $notification) {
		define('APNS_CERT_FILENAME', 'apns-dev.pem');
		define('APNS_CERT_PATH', dirname(__FILE__) . '/' . APNS_CERT_FILENAME);
		
	    $payload = self::getPayloadInJson($message, $notification);
		
		$stream_context = stream_context_create();
		stream_context_set_option($stream_context, 'ssl', 'local_cert', APNS_CERT_PATH);
		$apns = stream_socket_client('ssl://gateway.sandbox.push.apple.com:2195',
								$error, $error_string, 2, STREAM_CLIENT_CONNECT, $stream_context);
		stream_set_blocking($apns, 0);	// fread가 바로 리턴하도록

		$error_response_list = array();
		
		if($apns) {
			foreach ($device_tokens as $device_token) {
				$apns_message = chr(0).chr(0).chr(32).pack('H*', str_replace(' ', '', $device_token)).chr(0).chr(strlen($payload)).$payload;
				fwrite($apns, $apns_message);
				
				$error_response = self::checkError($apns);
				if ($error_response != null) {
					array_push($error_response_list, array('device_token' => $device_token,
														'error_response' => $error_response));
				}
			}

			fclose($apns);
		}
		
		return array($error, $error_string, $error_response_list);
	}
	
	// TODO: unchecked
	private static function checkError($apns) {
		/*
		 * byte[0] = always 8
		 * byte[1] = StatusCode
		 * bytes[2~5] = identifier(rowID)
		 * Should return nothing if OK.
		 */
		$apple_error_response = fread($apns, 6); 

       if ($apple_error_response) {
            $error_response = unpack('Ccommand/Cstatus_code/Nidentifier', $apple_error_response);

            if ($error_response['status_code'] == '0') {
                $error_response['status_description'] = '0-No errors encountered';
            } else if ($error_response['status_code'] == '1') {
                $error_response['status_description'] = '1-Processing error';
            } else if ($error_response['status_code'] == '2') {
                $error_response['status_description'] = '2-Missing device token';
            } else if ($error_response['status_code'] == '3') {
                $error_response['status_description'] = '3-Missing topic';
            } else if ($error_response['status_code'] == '4') {
                $error_response['status_description'] = '4-Missing payload';
            } else if ($error_response['status_code'] == '5') {
                $error_response['status_description'] = '5-Invalid token size';
            } else if ($error_response['status_code'] == '6') {
                $error_response['status_description'] = '6-Invalid topic size';
            } else if ($error_response['status_code'] == '7') {
                $error_response['status_description'] = '7-Invalid payload size';
            } else if ($error_response['status_code'] == '8') {
                $error_response['status_description'] = '8-Invalid token';
            } else if ($error_response['status_code'] == '255') {
                $error_response['status_description'] = '255-None (unknown)';
            } else {
                $error_response['status_description'] = $error_response['status_code'].'-Not listed';
            }

			return $error_response;
       }

       return null;
	}
}

?>
