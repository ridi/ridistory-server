<?php
namespace Story\Model;

use Doctrine\DBAL\Connection;
use Story\Entity\TestUserFactory;

class Buyer
{
    // Coin Sources (IN: 코인증가, OUT: 코인감소)
    const COIN_SOURCE_IN_INAPP        = 'IN_INAPP';
    const COIN_SOURCE_IN_RIDI         = 'IN_RIDI';
    const COIN_SOURCE_IN_TEST         = 'IN_TEST';
    const COIN_SOURCE_IN_EVENT        = 'IN_EVENT';
    const COIN_SOURCE_IN_CS_REWARD    = 'IN_CS_REWARD';

    const COIN_SOURCE_OUT_BUY_PART    = 'OUT_BUY_PART';
    const COIN_SOURCE_OUT_COIN_REFUND = 'OUT_COIN_REFUND';
    const COIN_SOURCE_OUT_WITHDRAW    = 'OUT_WITHDRAW';

    // AES Key
    const USER_ID_AES_SECRET_KEY = '';

    public static function isValidUid($id)
    {
        global $app;
        $r = $app['db']->fetchColumn('select count(*) from buyer_user where id = ?', array($id));
        return ($r > 0) ? true : false;
    }

    public static function checkIfHavePushDeviceTokens($u_ids) {
        $sql = <<<EOT
select distinct u_id from push_devices
where u_id in (?) and is_active = 1
EOT;
        global $app;
        $stmt = $app['db']->executeQuery(
            $sql,
            array($u_ids),
            array(Connection::PARAM_INT_ARRAY)
        );

        $u_ids_with_device_token = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        return array_diff($u_ids, $u_ids_with_device_token);
    }

    public static function verifyUids($u_ids)
    {
        $sql = <<<EOT
select id from buyer_user
where id in (?)
EOT;
        global $app;
        $stmt = $app['db']->executeQuery(
            $sql,
            array($u_ids),
            array(Connection::PARAM_INT_ARRAY)
        );

        $valid_u_ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        return array_diff($u_ids, $valid_u_ids);
    }

    public static function verifyGoogleAccounts($google_ids)
    {
        $sql = <<<EOT
select google_id from buyer_user
where google_id in (?)
EOT;
        global $app;
        $stmt = $app['db']->executeQuery(
            $sql,
            array($google_ids),
            array(Connection::PARAM_STR_ARRAY)
        );

        $valid_google_ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        return array_diff($google_ids, $valid_google_ids);
    }

    public static function getByUid($u_id, $include_ridibooks_ids_for_ridicash = false)
    {
        $sql = <<<EOT
select bu.*, ifnull(sum(amount), 0) coin_balance from coin_history ch
 left join buyer_user bu on ch.u_id = bu.id
where bu.id = ? having google_id is not null
EOT;
        global $app;
        $buyer = $app['db']->fetchAssoc($sql, array($u_id));
        if ($include_ridibooks_ids_for_ridicash) {
            $buyer['ridibooks_ids_for_ridicash'] = self::getRidibooksIdsForRidicashIfExists($u_id);
        }
        return $buyer;
    }

    public static function getByGoogleAccount($google_id, $include_ridibooks_ids_for_ridicash = false)
    {
        $sql = <<<EOT
select bu.*, ifnull(sum(amount), 0) coin_balance from coin_history ch
 left join buyer_user bu on ch.u_id = bu.id
where bu.google_id = ? having id is not null
EOT;
        global $app;
        $buyer = $app['db']->fetchAssoc($sql, array($google_id));
        if ($include_ridibooks_ids_for_ridicash) {
            $buyer['ridibooks_ids_for_ridicash'] = self::getRidibooksIdsForRidicashIfExists($buyer['id']);
        }
        return $buyer;
    }

    public static function getRidibooksIdsForRidicashIfExists($u_id)
    {
        $sql = <<<EOT
select distinct ridibooks_id from ridicash_history where u_id = ?
EOT;
        global $app;
        $ridibooks_ids_by_row = $app['db']->fetchAll($sql, array($u_id));

        $ridibooks_ids = array();
        foreach($ridibooks_ids_by_row as $ridibooks_id) {
            $ridibooks_ids[] = $ridibooks_id['ridibooks_id'];
        }
        return $ridibooks_ids;
    }

    public static function googleAccountsToUserIds($google_ids)
    {
        $sql = <<<EOT
select id, google_id from buyer_user
where google_id in (?)
EOT;
        global $app;
        $stmt = $app['db']->executeQuery($sql,
            array($google_ids),
            array(Connection::PARAM_STR_ARRAY)
        );
        $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $u_ids = array();
        foreach($users as $user) {
            // Key: Google Id, Value: User Id
            $u_ids[$user['google_id']] = $user['id'];
        }
        return $u_ids;
    }

    public static function getTotalUserCount($include_test_users = false)
    {
        $sql = <<<EOT
select count(*) user_count from buyer_user
EOT;
        if (!$include_test_users) {
            // 테스트 유저 제외
            $test_users = TestUserFactory::getConcatUidList(true);
            if ($test_users) {
                $sql .= ' where id not in (' . $test_users . ')';
            }
        }

        global $app;
        return $app['db']->fetchColumn($sql);
    }

    public static function getListByOffsetAndSize($offset, $limit)
    {
        $sql = <<<EOT
select u.*, ifnull(total_coin_in, 0) total_coin_in, ifnull(total_coin_out, 0) total_coin_out, ifnull(is_migrated, 0) is_migrated from buyer_user u
 left join (select u_id, sum(amount) total_coin_in from coin_history where amount > 0 group by u_id) ch on u.id = ch.u_id
 left join (select u_id, abs(sum(amount)) total_coin_out from coin_history where amount < 0 group by u_id) ch2 on u.id = ch2.u_id
 left join (select u_id, count(*) is_migrated from ridibooks_migration_history) rmh on u.id = rmh.u_id
order by google_reg_date desc limit ?, ?
EOT;
        global $app;
        $stmt = $app['db']->executeQuery($sql,
            array($offset, $limit),
            array(\PDO::PARAM_INT, \PDO::PARAM_INT)
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function getListBySearchTypeAndKeyword($search_type, $search_keyword)
    {
        $sql = <<<EOT
select u.*, ifnull(total_coin_in, 0) total_coin_in, ifnull(total_coin_out, 0) total_coin_out, ifnull(is_migrated, 0) is_migrated from buyer_user u
 left join (select u_id, sum(amount) total_coin_in from coin_history where amount > 0 group by u_id) ch on u.id = ch.u_id
 left join (select u_id, abs(sum(amount)) total_coin_out from coin_history where amount < 0 group by u_id) ch2 on u.id = ch2.u_id
 left join (select u_id, count(*) is_migrated from ridibooks_migration_history) rmh on u.id = rmh.u_id
EOT;
        if ($search_type == 'google_account') {
            $sql .= ' where u.google_id like ? order by google_reg_date desc';
            $bind = array('%' . $search_keyword . '%');
        } else if ($search_type == 'uid') {
            $sql .= ' where u.id = ? order by google_reg_date desc';
            $bind = array($search_keyword);
        } else {
            $sql = null;
            $bind = null;
        }

        global $app;
        return $app['db']->fetchAll($sql, $bind);
    }

    public static function getMigrationHistoryList()
    {
        global $app;
        return $app['db']->fetchAll('select * from user_migration_history order by migration_time desc');
    }

    public static function getCoinInList($id)
    {
        $sql = <<<EOT
select * from coin_history where amount > 0 and u_id = ? order by timestamp desc
EOT;
        global $app;
        return $app['db']->fetchAll($sql, array($id));
    }

    public static function getCoinOutList($id)
    {
        $sql = <<<EOT
select ch.id, abs(ch.amount) amount, ch.timestamp, ch.source, ph.p_id from coin_history ch
 left join (select id, p_id from purchase_history where is_paid = 1) ph on ph.id = ch.ph_id
where ch.amount < 0 and ch.u_id = ? order by timestamp desc
EOT;
        global $app;
        return $app['db']->fetchAll($sql, array($id));
    }

    public static function getPurchasedHistory($ph_id)
    {
        global $app;
        return $app['db']->fetchAssoc('select * from purchase_history where id = ?', array($ph_id));
    }

    public static function getPurchasedPartIdListByBid($u_id, $b_id, $include_first_seq = false)
    {
        $sql = <<<EOT
select distinct p.id from purchase_history ph
 left join part p on ph.p_id = p.id
where is_paid = 1 and u_id = ? and p.b_id = ? order by p.seq
EOT;
        $bind = array($u_id, $b_id);

        global $app;
        $parts = $app['db']->fetchAll($sql, $bind);

        $p_ids = array();

        //TODO: 첫 회를 무료로 제공하는 것이 확정되면 추가. @유대열
        // 구매한 파트가 1개이상 존재할 때, 해당 파트의 첫 화를 같이 줌
//        if ($include_first_seq && count($parts) > 0) {
//            $first_seq_part = Part::getFirstSeq($b_id);
//            array_push($p_ids, $first_seq_part['id']);
//        }

        foreach ($parts as $part) {
            if (!in_array($part['id'], $p_ids)) {
                array_push($p_ids, $part['id']);
            }
        }

        return $p_ids;
    }

    public static function getWholePurchasedList($u_id)
    {
        $sql = <<<EOT
select * from purchase_history
where is_paid = 1 and u_id = ? order by timestamp desc
EOT;
        global $app;
        return $app['db']->fetchAll($sql, array($u_id));
    }

    public static function deletePurchasedHistory($ph_id)
    {
        global $app;
        return $app['db']->delete('purchase_history', array('id' => $ph_id));
    }

    public static function getCoinBalance($u_id)
    {
        $sql = <<<EOT
select ifnull(sum(amount), 0) coin_balance from coin_history where u_id = ?
EOT;
        global $app;
        return $app['db']->fetchColumn($sql, array($u_id));
    }

    public static function getCoinUsageByUidAndTime($u_ids, $begin_date, $end_date)
    {
        $sql = <<<EOT
select u_id, abs(ifnull(sum(amount), 0)) used_coin from coin_history
where source = 'OUT_BUY_PART' and amount < 0 and timestamp >= ? and timestamp <= ? and u_id in (?) group by u_id
EOT;
        global $app;
        $stmt = $app['db']->executeQuery(
            $sql,
            array($begin_date, $end_date, $u_ids),
            array(\PDO::PARAM_STR, \PDO::PARAM_STR, Connection::PARAM_INT_ARRAY)
        );
        $coin_usages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($u_ids as $u_id) {
            $null_flag = true;
            foreach ($coin_usages as $coin_usage) {
                if ($coin_usage['u_id'] == $u_id) {
                    $null_flag = false;
                    break;
                }
            }

            if ($null_flag) {
                array_push($coin_usages, array('u_id' => $u_id, 'used_coin' => 0));
            }
        }

        usort($coin_usages, function ($a, $b) {
                if ($a['used_coin'] == $b['used_coin']) {
                    return 0;
                }

                return ($a['used_coin'] < $b['used_coin'] ? -1 : 1);
            }
        );

        return $coin_usages;
    }

    public static function addCoin($u_id, $coin_amount, $source)
    {
        global $app;
        $app['db']->insert('coin_history', array(
                'u_id' => $u_id,
                'amount' => $coin_amount,
                'source' => $source
            ));
        return $app['db']->lastInsertId();
    }

    public static function reduceCoin($u_id, $coin_amount, $source, $ph_id = null)
    {
        $sql = <<<EOT
 insert ignore into coin_history (u_id, amount, source, ph_id, timestamp)
 values (?, ?, ?, ?, ?)
EOT;
        if ($coin_amount > 0) {
            $coin_amount *= -1;
        }
        $today = date('Y-m-d H:i:s');

        $bind = array($u_id, $coin_amount, $source, $ph_id, $today);

        global $app;
        $app['db']->executeUpdate($sql, $bind);
        return $app['db']->lastInsertId();
    }

    public static function deleteBuyPartCoinHistory($ph_id)
    {
        global $app;
        return $app['db']->delete('coin_history', array('ph_id' => $ph_id, 'source' => Buyer::COIN_SOURCE_OUT_BUY_PART));
    }

    public static function hasPurchasedPart($u_id, $p_id)
    {
        $sql = <<<EOT
select count(*) from purchase_history
where u_id = ? and p_id = ? and is_paid = 1
EOT;
        global $app;
        $r = $app['db']->fetchColumn($sql, array($u_id, $p_id));
        return ($r > 0) ? true : false;
    }

    public static function hasPurchasedPartInBook($u_id, $b_id)
    {
        $sql = <<<EOT
select count(*) from part p
 left join purchase_history ph on ph.p_id = p.id
where ph.is_paid = 1 and ph.u_id = ? and p.b_id = ?
EOT;
        global $app;
        $r = $app['db']->fetchColumn($sql, array($u_id, $b_id));
        return ($r > 0) ? true : false;
    }

    public static function buyPart($u_id, $p_id, $coin_amount)
    {
        $sql = <<<EOT
insert ignore into purchase_history (u_id, p_id, is_paid, coin_amount, timestamp)
values (?, ?, ?, ?, ?)
EOT;
        $is_paid = ($coin_amount > 0 ? 1 : 0);
        $today = date('Y-m-d H:i:s');

        $bind = array ($u_id, $p_id, $is_paid, $coin_amount, $today);

        global $app;
        $r = $app['db']->executeUpdate($sql, $bind);
        if ($r) {
            return $app['db']->lastInsertId();
        } else {
            return 0;
        }
    }

    public static function update($u_id, $values) {
        global $app;
        return $app['db']->update('buyer_user', $values, array('id' => $u_id));
    }
}
