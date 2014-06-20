<?php
namespace Story\Model;

use Exception;

class CoinBilling
{
    // In App Billing Public Key
    const IAB_PUBLIC_KEY = "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA0Gjc4tCCAk4YKbIu5w9SwSkXDJQzUJNC9181Bh3HDd8PlSkRHRGpQDS7jIl2P3Pt7VorWOSmlCnDzbBsAT+mPSxUK4JG3kapHE53hBCMEhJ9nKZ+yDS94FDBiKNyKpVwQ1qA+ILFHo3NoU/vav+1qIOk1hPZAU0SKATikyQI0vzYoudePrCX3O3ue4olAepTT/Q71n7jXEu2yIbovYq9Jy4JZyhU4CVPvAwUA484q+jgm4yhl+d2ASor4bxmSmxfjwuVj45Cylb3NZ4iOUo+VSHwUAV1d3SAJYAMcUZdZbO127gK+uu8PchU/g/3yWY8csZuJINQvDWUFo7rY3dVgQIDAQAB";

    // Status Enum
    const STATUS_OK = 'OK';
    const STATUS_REFUNDED = 'REFUNDED';
    const STATUS_PENDING = 'PENDING';

    // DB Table Name
    const TABLE_INAPP = 'inapp_history';
    const TABLE_RIDICASH = 'ridicash_history';

    public static function verify($values, $payment)
    {
        if ($payment == CoinProduct::TYPE_INAPP) {
            return self::verifyInAppBilling($values);
        } else if ($payment == CoinProduct::TYPE_RIDICASH) {
            return self::verifyRidicashBilling($values);
        } else {
            return false;
        }
    }

    public static function getInAppBillingBindIfNotNull($values)
    {
        // Purchase Data (JSON data of Paramters)
        $purchase_data = json_decode($values['purchase_data'], true);

        // Parameters
        $u_id = $values['u_id'];
        $order_id = $purchase_data['orderId'];
        $sku = $purchase_data['productId'];
        $payload = $purchase_data['developerPayload'];
        $purchase_time = $purchase_data['purchaseTime'];
        $purchase_token = $purchase_data['purchaseToken'];
        $signature = $values['signature'];

        // Null Check
        if ($u_id == null || $order_id == null || $sku == null || $payload == null || $purchase_time == null || $purchase_token == null || $signature == null) {
            return null;
        }

        // 인앱 결제 내역에 등록
        $bind = array(
            'order_id' => $order_id,
            'u_id' => $u_id,
            'sku' => $sku,
            'purchase_time' => date('Y-m-d H:i:s'),
            'payload' => $payload,
            'purchase_token' => $purchase_token,
            'purchase_data' => $values['purchase_data'],
            'signature' => $signature,
            'status' => self::STATUS_PENDING,
        );

        return $bind;
    }

    private static function verifyInAppBilling($values)
    {
        $purchase_data = json_decode($values['purchase_data'], true);
        $order_id = $purchase_data['orderId'];

        $signature = $values['signature'];

        // 저장되어 있는 결제정보가 없으면 오류
        $history = self::getBillingSalesDetailByOrderId(CoinProduct::TYPE_INAPP, $order_id);
        if ($history == null) {
            trigger_error('InAppBilling History NullException | OrderId: ' . $order_id, E_USER_ERROR);
            return false;
        }

        //TODO: 추후에 Purchase Status API가 안정화되면, Purchase Status API로 교체
        // Signature 검증
        $iab_public_key =  "-----BEGIN PUBLIC KEY-----\n"
                         . chunk_split(self::IAB_PUBLIC_KEY, 64,"\n")
                         . "-----END PUBLIC KEY-----";
        $iab_public_key = openssl_get_publickey($iab_public_key);
        $signature = base64_decode($signature);
        $is_valid_iab = openssl_verify($values['purchase_data'], $signature, $iab_public_key);
        openssl_free_key($iab_public_key);

        if ($is_valid_iab == 1) {
            $r = self::changeBillingStatusAndValues($history['id'], CoinProduct::TYPE_INAPP, self::STATUS_OK);
            return ($r === 1);
        } else {
            error_log('[INAPP] Failed Verify InAppBilling(Signature): ' . $is_valid_iab, 0);
            return false;
        }
    }

    private static function verifyRidicashBilling($values)
    {
        // Parameters
        $u_id = $values['u_id'];
        $ridibooks_id = $values['ridibooks_id'];
        $payment_token = $values['payment_token'];
        $sku = $values['sku'];

        if ($u_id == null || $ridibooks_id == null || $payment_token == null || $sku == null) {
            return false;
        }

        // 리디캐시 결제 내역에 등록
        $bind = array(
            'u_id' => $u_id,
            'ridibooks_id' => $ridibooks_id,
            'sku' => $sku,
            'purchase_time' => date('Y-m-d H:i:s'),
            'status' => self::STATUS_PENDING,
        );
        $rcb_id = self::saveBillingHistory($bind, CoinProduct::TYPE_RIDICASH);

        // 실제 리디캐시 결제(차감)
        $ch =curl_init();
        curl_setopt($ch, CURLOPT_URL, STORE_API_BASE_URL . '/api/story/exchange_cash_coin.php?payment_token=' . $payment_token . '&sku=' . $sku);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);

        $response = curl_exec($ch);
        $response = json_decode($response, true);
        curl_close($ch);

        if ($response['success'] == true) {
            $r = self::changeBillingStatusAndValues($rcb_id, CoinProduct::TYPE_RIDICASH, self::STATUS_OK, array('t_id' => $response['t_id']));
            return ($r === 1);
        } else {
            error_log('[RIDICASH] Failed Verify RidicashBilling: ' . print_r($response, true), 0);
            if (empty($response)) {
                // 리디스토리 -> 리디북스로의 서버 요청이 막혔을 경우에 Sentry에 등록시킴
                trigger_error('Failed verify RidicashBilling: (Response is null)', E_USER_ERROR);
            }
            return false;
        }
    }

    public static function saveBillingHistory($values, $payment)
    {
        global $app;
        $app['db']->insert(self::getDBTableName($payment), $values);
        return $app['db']->lastInsertId();
    }

    public static function changeBillingStatusAndValues($id, $payment, $status, $values = null)
    {
        $update_values = array('status' => $status);

        // 환불일 경우에, 환불일 적용.
        if ($status == CoinBilling::STATUS_REFUNDED) {
            $update_values['refunded_time'] = date('Y-m-d H:i:s');
        }

        if (!empty($values)) {
            $update_values = array_merge($update_values, $values);
        }

        global $app;
        return $app['db']->update(self::getDBTableName($payment), $update_values, array('id' => $id));
    }

    public static function refund($payment, $id)
    {
        // 결제 수단 별로, 코인 결제 내역 가져오기
        if ($payment == CoinProduct::TYPE_INAPP || $payment == CoinProduct::TYPE_RIDICASH) {
            $coin_sale = self::getBillingSalesDetail($payment, $id);
        } else {
            throw new Exception('결제 수단이 잘못 되었습니다.');
        }

        // 환불할 수 있는 결제인지를 검증
        if ($coin_sale['status'] != CoinBilling::STATUS_OK) {
            throw new Exception('이미 환불되었거나, 환불할 수 없는 결제입니다.');
        }

        $user = Buyer::getByUid($coin_sale['u_id'], false);
        $product = CoinProduct::getCoinProductBySkuAndType($coin_sale['sku'], $payment);

        $user_coin_balance = $user['coin_balance'];
        $refund_coin_amount = $product['coin_amount'] + $product['bonus_coin_amount'];

        // 잔여 코인 확인
        if ($user_coin_balance < $refund_coin_amount) {
            throw new Exception('회원의 잔여 코인이 구입시 충전된 코인보다 적어 환불할 수 없습니다. (현재 회원 잔여 코인: ' . $user_coin_balance . '개)');
        }

        // 리디캐시 결제일 경우에, 리디북스의 결제 취소 API 호출
        if ($payment == CoinProduct::TYPE_RIDICASH) {
            //TODO: 리디북스 결제 취소 API 호출
        }

        // 환불 트랜잭션 시작
        global $app;
        $app['db']->beginTransaction();
        try {
            // 코인 회수(감소)
            $r = Buyer::reduceCoin($user['id'], $refund_coin_amount, Buyer::COIN_SOURCE_OUT_COIN_REFUND);
            if (!$r) {
                throw new Exception('환불 도중 오류가 발생했습니다. (코인 감소 DB 오류)');
            }

            // 상태 변경 (정상 -> 환불됨)
            $r = self::changeBillingStatusAndValues($id, $payment, self::STATUS_REFUNDED);
            if (!$r) {
                throw new Exception('환불 도중 오류가 발생했습니다. (상태 변경 DB 오류)');
            }

            $app['db']->commit();
        } catch (Exception $e) {
            $app['db']->rollback();
            throw $e;
        }

        return ($user_coin_balance - $refund_coin_amount);
    }

    public static function getBillingSalesListByOffsetAndSize($payment, $offset, $limit)
    {
        $table = self::getDBTableName($payment);
        if ($table) {
            $sql = <<<EOT
select * from {$table}
order by purchase_time desc limit ?, ?
EOT;
            global $app;
            $stmt = $app['db']->executeQuery($sql,
                array($offset, $limit),
                array(\PDO::PARAM_INT, \PDO::PARAM_INT)
            );
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            return null;
        }
    }

    public static function getBillingSalesListBySearchTypeAndKeyword($payment, $type, $keyword)
    {
        $table = self::getDBTableName($payment);
        if ($table) {
            $bind = null;

            $sql = <<<EOT
select * from {$table} where
EOT;
            if ($payment == CoinProduct::TYPE_INAPP) {
                switch($type) {
                    case 'uid':
                        $sql .= ' u_id = ?';
                        $bind = array($keyword);
                        break;
                    case 'google_order_num':
                        $sql .= ' order_id like ?';
                        $bind = array('%' . $keyword . '%');
                        break;
                    default:
                        $sql = null;
                }
            } else {
                switch($type) {
                    case 'uid':
                        $sql .= ' u_id = ?';
                        $bind = array($keyword);
                        break;
                    case 'tid':
                        $sql .= ' t_id like ?';
                        $bind = array('%' . $keyword . '%');
                        break;
                    case 'ridibooks_id':
                        $sql .= ' ridibooks_id like ?';
                        $bind = array('%' . $keyword . '%');
                        break;
                    default:
                        $sql = null;
                }
            }
            $sql .= ' order by purchase_time desc';

            global $app;
            return $app['db']->fetchAll($sql, $bind);
        } else {
            return null;
        }
    }

    public static function getBillingSalesDetail($payment, $id)
    {
        $table = self::getDBTableName($payment);
        if ($table) {
            $sql = <<<EOT
select u.google_id, bh.* from {$table} bh
 left join buyer_user u on u.id = bh.u_id
where bh.id = ?
EOT;
            global $app;
            return $app['db']->fetchAssoc($sql, array($id));
        } else {
            return null;
        }
    }

    private static function getBillingSalesDetailByOrderId($payment, $order_id)
    {
        $field = null;
        if ($payment == CoinProduct::TYPE_INAPP) {
            $field = 'order_id';
        } else if ($payment == CoinProduct::TYPE_RIDICASH) {
            $field = 't_id';
        }

        $table = self::getDBTableName($payment);
        if ($table) {
            $sql = <<<EOT
select * from {$table}
where {$field} = ?
EOT;
            global $app;
            return $app['db']->fetchAssoc($sql, array($order_id));
        } else {
            return null;
        }
    }

    private static function getDBTableName($payment)
    {
        if ($payment == CoinProduct::TYPE_INAPP) {
            return self::TABLE_INAPP;
        } else if ($payment == CoinProduct::TYPE_RIDICASH) {
            return self::TABLE_RIDICASH;
        } else {
            return null;
        }
    }
}