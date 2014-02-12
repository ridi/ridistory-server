<?php
namespace Story\Util;

class SimpleApnsClient
{
    const ENVIRONMENT_PRODUCTION = 0;
    const ENVIRONMENT_SANDBOX = 1;

    const COMMAND_PUSH = 1;
    const DEVICE_BINARY_SIZE = 32;

    const CONNECT_RETRY_TIMES = 3;

    private $environment;
    private $certificate_path;

    private $device_tokens;
    private $payload;

    private $apns;

    public function __construct($environment, $certificate_path)
    {
        $this->environment = $environment;
        $this->certificate_path = $certificate_path;
        $this->device_tokens = array();
    }

    public function addDeviceToken($device_token)
    {
        array_push($this->device_tokens, $device_token);
    }

    public function setPayload($payload)
    {
        $this->payload = $payload;
    }

    private function getApnsUrl()
    {
        if ($this->environment == self::ENVIRONMENT_PRODUCTION) {
            return 'ssl://gateway.push.apple.com:2195';
        } else {
            if ($this->environment == self::ENVIRONMENT_SANDBOX) {
                return 'ssl://gateway.sandbox.push.apple.com:2195';
            }
        }

        return null;
    }

	public function connect() {
		$stream_context = stream_context_create(array('ssl' =>
								array('local_cert' => $this->certificate_path)));

		// 접속 실패할 경우를 대비에 반복 시도
		for ($i = 0; $i < self::CONNECT_RETRY_TIMES; $i++) {
			$this->apns = stream_socket_client($this->getApnsUrl(), $error, $error_string, 2, STREAM_CLIENT_CONNECT, $stream_context);

			if (isset($this->apns)) {
				stream_set_blocking($this->apns, 0);
				return true;
			}
		}

		return false;
	}

    public function disconnect()
    {
        fclose($this->apns);
    }

    private function isConnected()
    {
        return isset($this->apns);
    }

    public function send()
    {
        if ($this->isConnected() == false || empty($this->payload)) {
            throw new \Exception("sending push is not prepared.", 1);
        }

        $error_response_list = array();

        foreach ($this->device_tokens as $device_token) {
            $apns_message = pack('CNNnH*', self::COMMAND_PUSH, 0, 0, self::DEVICE_BINARY_SIZE, $device_token);
            $apns_message .= pack('n', strlen($this->payload));
            $apns_message .= $this->payload;

            fwrite($this->apns, $apns_message);
            usleep(200 * 1000);

			$error_response = $this->checkError();
			if ($error_response != null) {
				array_push($error_response_list, array('device_token' => $device_token, 'error_response' => $error_response));

				// APN에서는 에러 발견시 바로 연결을 끊으므로 재접속
				$result = $this->connect();
				if ($result == false) {
					array_push($error_response_list, array('device_token' => $device_token, 'error' => 'reconnection failed'));
					break;
				}
			}
		}

		return $error_response_list;
	}

    private function checkError()
    {
        $apple_error_response = fread($this->apns, 6);

        if ($apple_error_response) {
            $error_response = unpack('Ccommand/Cstatus_code/Nidentifier', $apple_error_response);

            if ($error_response['status_code'] == '0') {
                $error_response['status_description'] = '0-No errors encountered';
            } elseif ($error_response['status_code'] == '1') {
                $error_response['status_description'] = '1-Processing error';
            } elseif ($error_response['status_code'] == '2') {
                $error_response['status_description'] = '2-Missing device token';
            } elseif ($error_response['status_code'] == '3') {
                $error_response['status_description'] = '3-Missing topic';
            } elseif ($error_response['status_code'] == '4') {
                $error_response['status_description'] = '4-Missing payload';
            } elseif ($error_response['status_code'] == '5') {
                $error_response['status_description'] = '5-Invalid token size';
            } elseif ($error_response['status_code'] == '6') {
                $error_response['status_description'] = '6-Invalid topic size';
            } elseif ($error_response['status_code'] == '7') {
                $error_response['status_description'] = '7-Invalid payload size';
            } elseif ($error_response['status_code'] == '8') {
                $error_response['status_description'] = '8-Invalid token';
            } elseif ($error_response['status_code'] == '255') {
                $error_response['status_description'] = '255-None (unknown)';
            } else {
                $error_response['status_description'] = $error_response['status_code'] . '-Not listed';
            }

            return $error_response;
        }

        return null;
    }
}
