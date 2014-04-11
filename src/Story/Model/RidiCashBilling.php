<?php
namespace Story\Model;

class RidiCashBilling
{
    // Status Enum
    const STATUS_OK = 'OK';
    const STATUS_REFUNDED = 'REFUNDED';
    const STATUS_PENDING = 'PENDING';

    public static function exchangeRidiCashToCoin($values)
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
            'status' => InAppBilling::STATUS_PENDING,
        );
        $rcb_id = RidiCashBilling::saveRidiCashBillingHistory($bind);

        // 실제 리디캐시 결제(차감)
        $ch =curl_init();
        curl_setopt($ch, CURLOPT_URL, STORE_API_BASE_URL . '/api/story/exchange_cash_coin.php?payment_token=' . $payment_token . '&sku=' . $sku);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);

        $response = curl_exec($ch);
        $response = json_decode($response, true);
        curl_close($ch);

        if ($response['success'] == true) {
            $r = RidiCashBilling::changeRidiCashBillingStatusAndTidIfNotNull($rcb_id, $response['t_id'], RidiCashBilling::STATUS_OK);
            return ($r === 1);
        } else {
            error_log('[RIDICASH] Failed Exchange Ridicash->Coin: ' . print_r($response, true), 0);
        }
    }

    public static function saveRidiCashBillingHistory($values)
    {
        global $app;
        $app['db']->insert('ridicash_history', $values);
        return $app['db']->lastInsertId();
    }

    public static function changeRidiCashBillingStatusAndTidIfNotNull($rcb_id, $t_id = null, $status)
    {
        global $app;
        $update_values = array('status' => $status);

        // T_ID 있을 경우, T_ID 적용
        if ($t_id != null) {
            $update_values['t_id'] = $t_id;
        }

        // 환불일 경우, 환불일 적용
        if ($status == RidiCashBilling::STATUS_REFUNDED) {
            $update_values['refunded_time'] = date('Y-m-d H:i:s');
        }

        return $app['db']->update('ridicash_history', $update_values, array('id' => $rcb_id));
    }

    public static function getRidiCashBillingSalesListByOffsetAndSize($offset, $limit)
    {
        $sql = <<<EOT
select * from ridicash_history
order by purchase_time desc limit {$offset}, {$limit}
EOT;
        global $app;
        return $app['db']->fetchAll($sql);
    }

    public static function getRidiCashBillingSalesListBySearchTypeAndKeyword($type, $keyword)
    {
        if ($type == 'uid') {
            $sql = <<<EOT
select * from ridicash_history
where u_id = '{$keyword}' order by purchase_time desc
EOT;
        } else if ($type == 'tid') {
            $sql = <<<EOT
select * from ridicash_history
where t_id like '%{$keyword}%' order by purchase_time desc
EOT;
        } else if ($type == 'ridibooks_id') {
            $sql = <<<EOT
select * from ridicash_history
where ridibooks_id like '%{$keyword}%' order by purchase_time desc
EOT;
        } else {
            $sql = null;
        }

        global $app;
        return $app['db']->fetchAll($sql);
    }

    public static function getRidiCashBillingSalesDetail($rcb_sale_id)
    {
        $sql = <<<EOT
select bu.google_id, rh.* from ridicash_history rh
 left join (select * from buyer_user) bu on bu.id = rh.u_id
where rh.id = ?
EOT;
        $bind = array($rcb_sale_id);

        global $app;
        return $app['db']->fetchAssoc($sql, $bind);
    }
}