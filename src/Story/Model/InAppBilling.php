<?php
namespace Story\Model;

class InAppBilling
{
    // In App Billing Public Key
    const IAB_PUBLIC_KEY = "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA0Gjc4tCCAk4YKbIu5w9SwSkXDJQzUJNC9181Bh3HDd8PlSkRHRGpQDS7jIl2P3Pt7VorWOSmlCnDzbBsAT+mPSxUK4JG3kapHE53hBCMEhJ9nKZ+yDS94FDBiKNyKpVwQ1qA+ILFHo3NoU/vav+1qIOk1hPZAU0SKATikyQI0vzYoudePrCX3O3ue4olAepTT/Q71n7jXEu2yIbovYq9Jy4JZyhU4CVPvAwUA484q+jgm4yhl+d2ASor4bxmSmxfjwuVj45Cylb3NZ4iOUo+VSHwUAV1d3SAJYAMcUZdZbO127gK+uu8PchU/g/3yWY8csZuJINQvDWUFo7rY3dVgQIDAQAB";

    // Purchase Status API, Purchase state
    const PURCHASE_STATE_PURCHASED = 0;
    const PURCHASE_STATE_CANCELLED = 1;

    // Consumption Status API, Consumption state
    const CONSUMPTION_STATE_CONSUMED = 1;
    const CONSUMPTION_STATE_NOT_CONSUMED = 0;

    // Status Enum
    const STATUS_OK = 'OK';
    const STATUS_REFUNDED = 'REFUNDED';
    const STATUS_PENDING = 'PENDING';

    public static function verifyInAppBilling($values)
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

        if ($u_id == null || $order_id == null || $sku == null || $payload == null || $purchase_time == null || $purchase_token == null || $signature == null) {
            return false;
        }

        // 인앱 결제 내역에 등록
        $bind = array(
            'order_id' => $order_id,
            'u_id' => $u_id,
            'sku' => $sku,
            // Purchase Time을 서버시간 기준으로 변경. (변경일: 2014년 4월 8일 19시 24분)
            'purchase_time' => date('Y-m-d H:i:s'),
            'payload' => $payload,
            'purchase_token' => $purchase_token,
            'purchase_data' => $values['purchase_data'],
            'signature' => $signature,
            'status' => InAppBilling::STATUS_PENDING,
        );
        $iab_id = InAppBilling::saveInAppBillingHistory($bind);

        //TODO: 추후에 Purchase Status API가 안정화되면, Purchase Status API로 교체
        // Signature 검증
        $iab_public_key =  "-----BEGIN PUBLIC KEY-----\n" . chunk_split(InAppBilling::IAB_PUBLIC_KEY, 64,"\n") . '-----END PUBLIC KEY-----';
        $iab_public_key = openssl_get_publickey($iab_public_key);
        $signature = base64_decode($signature);
        $is_valid_iab = openssl_verify($values['purchase_data'], $signature, $iab_public_key);
        openssl_free_key($iab_public_key);

        if ($is_valid_iab == 1) {
            $r = InAppBilling::changeInAppBillingStatus($iab_id, InAppBilling::STATUS_OK);
            return ($r === 1);
        } else {
            error_log('Failed Verify Signature: ' . $is_valid_iab, 0);
            return false;
        }
    }

    public static function saveInAppBillingHistory($values)
    {
        global $app;
        $app['db']->insert('inapp_history', $values);
        return $app['db']->lastInsertId();
    }

    public static function changeInAppBillingStatus($iab_id, $status)
    {
        global $app;
        $update_values = array('status' => $status);

        // 환불일 경우, 환불일 적용
        if ($status == InAppBilling::STATUS_REFUNDED) {
            $update_values['refunded_time'] = date('Y-m-d H:i:s');
        }

        return $app['db']->update('inapp_history', $update_values, array('id' => $iab_id));
    }

    public static function getInAppBillingSalesListByOffsetAndSize($offset, $limit)
    {
        $sql = <<<EOT
select * from inapp_history
order by purchase_time desc limit ?, ?
EOT;
        global $app;
        $stmt = $app['db']->executeQuery($sql,
            array($offset, $limit),
            array(\PDO::PARAM_INT, \PDO::PARAM_INT)
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function getInAppBillingSalesListBySearchTypeAndKeyword($type, $keyword)
    {
        if ($type == 'uid') {
            $sql = <<<EOT
select * from inapp_history
where u_id = ? order by purchase_time desc
EOT;
            $bind = array($keyword);
        } else if ($type == 'google_order_num') {
            $sql = <<<EOT
select * from inapp_history
where order_id like ? order by purchase_time desc
EOT;
            $bind = array('%' . $keyword . '%');
        } else {
            $sql = null;
            $bind = null;
        }

        global $app;
        return $app['db']->fetchAll($sql, $bind);
    }

    public static function getInAppBillingSalesDetail($iab_sale_id)
    {
        $sql = <<<EOT
select bu.google_id, ih.* from inapp_history ih
 left join (select * from buyer_user) bu on bu.id = ih.u_id
where ih.id = ?
EOT;
        $bind = array($iab_sale_id);

        global $app;
        return $app['db']->fetchAssoc($sql, $bind);
    }
}