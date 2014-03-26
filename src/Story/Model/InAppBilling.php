<?php
namespace Story\Model;

class InAppBilling
{
    // Purchase Status API, Purchase state
    const PURCHASE_STATE_PURCHASED = 0;
    const PURCHASE_STATE_CANCELLED = 1;

    // Consumption Status API, Consumption state
    const CONSUMPTION_STATE_CONSUMED = 1;
    const CONSUMPTION_STATE_NOT_CONSUMED = 0;

    // Ridistory API oAuth 2.0 Info
    const CLIENT_ID = '78321583520.apps.googleusercontent.com';
    const CLIENT_SECRET = 'EmIXOkQqsIhuXSLV1aIt9TvB';
    const REFRESH_TOKEN = '1/PRMtp_Jo6-TZ8eATWPK20SO-0V_UwGfg9PGJdn3FQ5Y';

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
        return $app['db']->fetchAll('select sku from inapp_products order by coin_amount asc');
    }

    public static function getInAppProductListWithTotalSales()
    {
        $sql = <<<EOT
select ip.*, ifnull(total_sales_count, 0) total_sales_count from inapp_products ip
 left join (select sku, count(*) total_sales_count from inapp_history group by sku) ih on ip.sku = ih.sku
order by ip.coin_amount asc
EOT;

        global $app;
        return $app['db']->fetchAll($sql);
    }

    public static function verifyInAppBilling($values)
    {
        // Parameters
        $order_id = $values['order_id'];
        $u_id = $values['u_id'];
        $sku = $values['sku'];
        $payload = $values['payload'];
        $purchase_time = $values['purchase_time'];
        $purchase_token = $values['purchase_token'];
        $signature = $values['signature'];

        if ($u_id == null || $order_id == null || $sku == null || $payload == null || $purchase_time == null || $purchase_token == null || $signature == null) {
            return false;
        }

        // 인앱 결제 내역에 등록
        $bind = array(
            'order_id' => $order_id,
            'u_id' => $u_id,
            'sku' => $sku,
            'purchase_time' => date('Y-m-d H:i:s', ($purchase_time / 1000)),
            'payload' => $payload,
            'purchase_token' => $purchase_token,
            'signature' => $signature
        );
        $iab_id = InAppBilling::saveInAppBillingHistory($bind);

        // Google oAuth 2.0 Reresh Token API Param
        $refresh_token_params = array(
            'refresh_token' => urlencode(InAppBilling::REFRESH_TOKEN),
            'grant_type' => urlencode('refresh_token'),
            'client_id' => urlencode(InAppBilling::CLIENT_ID),
            'client_secret' => urlencode(InAppBilling::CLIENT_SECRET)
        );
        $refresh_token_params_string = '';
        foreach($refresh_token_params as $key=>$value) {
            $refresh_token_params_string .= $key . '=' . $value . '&';
        }
        rtrim($refresh_token_params_string, '&');

        // Google oAuth 2.0 Refresh Token API
        $refresh_token_url = 'https://accounts.google.com/o/oauth2/token';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $refresh_token_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, count($refresh_token_params));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $refresh_token_params_string);
        $response = curl_exec($ch);
        curl_close($ch);
        $response = json_decode($response, true);

        // Refresh Token Null Check
        if ($response['token_type'] == null || $response['access_token'] == null) {
            return false;
        }

        // Generate oAuth 2.0 Access Token
        $access_token = $response['token_type'] . ' ' . $response['access_token'];

        // Google Purchase Status API
        $purchase_status_url = 'https://www.googleapis.com/androidpublisher/v1.1/applications/com.initialcoms.story/inapp/' . $sku . '/purchases/' . $purchase_token;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $purchase_status_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: ' . $access_token));
        $response = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($response, true);

        InAppBilling::setInAppBillingVerifyInfo($iab_id, $response);

        if ($response['developerPayload'] == $payload
            && $response['purchaseTime'] == $purchase_time
            && $response['purchaseState'] == InAppBilling::PURCHASE_STATE_PURCHASED
            && $response['consumptionState'] == InAppBilling::CONSUMPTION_STATE_CONSUMED) {
            $r = InAppBilling::setInAppBillingSucceeded($iab_id);
            return ($r === 1);
        } else {
            error_log("Purchase Status API Error", 0);
            error_log(print_r($response, true), 0);
            return false;
        }
    }

    public static function saveInAppBillingHistory($values)
    {
        global $app;
        $app['db']->insert('inapp_history', $values);
        return $app['db']->lastInsertId();
    }

    public static function setInAppBillingVerifyInfo($iab_id, $verify_info)
    {
        switch($verify_info['purchaseState']) {
            case InAppBilling::PURCHASE_STATE_PURCHASED:
                $purchase_state = 'PURCHASED';
                break;
            case InAppBilling::PURCHASE_STATE_CANCELLED:
                $purchase_state = 'CANCELLED';
                break;
            default:
                $purchase_state = 'NONE';
        }

        switch($verify_info['consumptionState']) {
            case InAppBilling::CONSUMPTION_STATE_CONSUMED:
                $consumption_state = 'CONSUMED';
                break;
            case InAppBilling::CONSUMPTION_STATE_NOT_CONSUMED:
                $consumption_state = 'NOT_CONSUMED';
                break;
            default:
                $consumption_state = 'NONE';
        }

        global $app;
        return $app['db']->update('inapp_history',
            array(
                'verify_purchase_time' => date('Y-m-d H:i:s', ($verify_info['purchaseTime'] / 1000)),
                'verify_purchase_state' => $purchase_state,
                'verify_consumption_state' => $consumption_state,
                'verify_payload' => $verify_info['developerPayload']
            ),
            array('id' => $iab_id)
        );
    }

    public static function setInAppBillingSucceeded($iab_id)
    {
        global $app;
        return $app['db']->update('inapp_history', array('is_succeeded' => 1), array('id' => $iab_id));
    }

    public static function getInAppBillingSalesListByOffsetAndSize($offset, $limit, $begin_date, $end_date)
    {
        $first_timestamp = date('1970-01-01 00:00:01');
        $today = date('Y-m-d H:i:s');

        $sql = <<<EOT
select * from inapp_history
where purchase_time >= ? and purchase_time <= ?
order by purchase_time desc limit {$offset}, {$limit}
EOT;

        if ($begin_date && $end_date) {
            $begin_date = date('Y-m-d 00:00:00', strtotime($begin_date));
            $end_date = date('Y-m-d 23:59:59', strtotime($end_date));
        } else if ($begin_date && !$end_date) {
            $begin_date = date('Y-m-d 00:00:00', strtotime($begin_date));
            $end_date = $today;
        } else if (!$begin_date && $end_date) {
            $begin_date = $first_timestamp;
            $end_date = date('Y-m-d 23:59:59', strtotime($end_date));
        } else {
            $begin_date = $first_timestamp;
            $end_date = $today;
        }
        $bind = array($begin_date, $end_date);

        global $app;
        return $app['db']->fetchAll($sql, $bind);
    }

    public static function getInAppBillingSalesDetail($coin_sale_id)
    {
        $sql = <<<EOT
select bu.google_id, ih.* from inapp_history ih
 left join (select * from buyer_user) bu on bu.id = ih.u_id
where ih.id = ?
EOT;
        $bind = array($coin_sale_id);

        global $app;
        return $app['db']->fetchAssoc($sql, $bind);
    }
}