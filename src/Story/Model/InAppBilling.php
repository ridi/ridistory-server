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

    public static function createInAppProduct()
    {
        global $app;
        $app['db']->insert('inapp_products', array());
        return $app['db']->lastInsertId();
    }

    public static function updateInAppProduct($id, $values)
    {
        global $app;
        return $app['db']->update('inapp_products', $values, array('id' => $id));
    }

    public static function deleteInAppProduct($id)
    {
        global $app;
        return $app['db']->delete('inapp_products', array('id' => $id));
    }

    public static function getInAppProduct($id)
    {
        global $app;
        return $app['db']->fetchAssoc('select * from inapp_products where id = ?', array($id));
    }

    public static function getInAppProductBySku($sku)
    {
        global $app;
        return $app['db']->fetchAssoc('select * from inapp_products where sku = ?', array($sku));
    }

    public static function getInAppProductList()
    {
        global $app;
        return $app['db']->fetchAll('select * from inapp_products order by coin_amount asc');
    }

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
            $update_values['refunded_date'] = date('Y-m-d H:i:s');
        }

        return $app['db']->update('inapp_history', $update_values, array('id' => $iab_id));
    }

    public static function getInAppBillingSalesListByOffsetAndSize($offset, $limit, $begin_date, $end_date)
    {
        if (!$begin_date) {
            $begin_date = '0000-00-00';
        }
        if (!$end_date) {
            $end_date = date('Y-m-d');
        }

        $sql = <<<EOT
select * from inapp_history
where date(purchase_time) >= ? and date(purchase_time) <= ?
order by purchase_time desc limit {$offset}, {$limit}
EOT;
        $bind = array($begin_date, $end_date);

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