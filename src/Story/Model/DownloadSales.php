<?php
namespace Story\Model;

class DownloadSales
{
    public static function get($b_id, $begin_date, $end_date)
    {
        if (!$begin_date) {
            $begin_date = '0000-00-00';
        }
        if (!$end_date) {
            $end_date = date('Y-m-d');
        }

        $sql = <<<EOT
select b.id b_id, b.title, cp.id cp_id, cp.name cp_name, b.royalty_percent, b.begin_date, b.end_date, b.end_action_flag from purchase_history ph
 left join (select id, b_id, price from part) p on p.id = ph.p_id
 left join (select * from book) b on b.id = p.b_id
 left join (select id, name from cp_account) cp on cp.id = b.cp_id
where b_id = ? and date(timestamp) >= ? and date(timestamp) <= ?
EOT;
        $test_users = TestUser::getConcatUidList(true);
        if ($test_users) {
            $sql .= ' and u_id not in (' . $test_users . ')';
        }
        $sql .= ' group by b_id';

        $bind = array($b_id, $begin_date, $end_date);

        global $app;
        return $app['db']->fetchAssoc($sql, $bind);
    }

    public static function getWholeList($begin_date, $end_date, $exclude_free = false)
    {
        $today = date('Y-m-d H:i:s');

        if (!$begin_date) {
            $begin_date = '0000-00-00';
        }
        if (!$end_date) {
            $end_date = date('Y-m-d');
        }

        $sql = <<<EOT
select b.id b_id, b.title, cp.id, cp_id, cp.name cp_name, b.royalty_percent, ifnull(open_part_count, 0) open_part_count, b.total_part_count, b.begin_date, b.end_date, b.end_action_flag, sum(if(coin_amount=0, 1, 0)) free_download, sum(if(coin_amount>0, 1, 0)) charged_download, sum(coin_amount) total_sales from purchase_history ph
 left join (select id, b_id, price from part) p on p.id = ph.p_id
 left join (select * from book) b on b.id = p.b_id
 left join (select b_id, count(*) open_part_count from part where begin_date <= ? and end_date >= ? group by b_id) pc on pc.b_id = b.id
 left join (select id, name from cp_account) cp on cp.id = b.cp_id
where date(ph.timestamp) >= ? and date(ph.timestamp) <= ?
EOT;
        if ($exclude_free) {
            $sql .= ' and coin_amount > 0';
        }

        $test_users = TestUser::getConcatUidList(true);
        if ($test_users) {
            $sql .= ' and ph.u_id not in (' . $test_users . ')';
        }
        $sql .= ' group by b_id order by (count(*) * p.price) desc';

        $bind = array($today, $today, $begin_date, $end_date);

        global $app;
        return $app['db']->fetchAll($sql, $bind);
    }

    public static function getListByCpId($cp_id, $begin_date, $end_date)
    {
        $today = date('Y-m-d H:i:s');

        if (!$begin_date) {
            $begin_date = '0000-00-00';
        }
        if (!$end_date) {
            $end_date = date('Y-m-d');
        }

        $sql = <<<EOT
select b.id b_id, b.title, b.royalty_percent, b.author, b.publisher, b.adult_only, ifnull(open_part_count, 0) open_part_count, b.total_part_count, b.begin_date, b.end_date, b.end_action_flag, sum(if(coin_amount=0, 1, 0)) free_download, sum(if(coin_amount!=0, 1, 0)) charged_download, sum(coin_amount) total_sales from purchase_history ph
 left join (select id, b_id, price from part) p on p.id = ph.p_id
 left join (select * from book) b on b.id = p.b_id
 left join (select b_id, count(*) open_part_count from part where begin_date <= ? and end_date >= ? group by b_id) pc on pc.b_id = b.id
where b.cp_id = ? and date(ph.timestamp) >= ? and date(ph.timestamp) <= ?
EOT;
        $test_users = TestUser::getConcatUidList(true);
        if ($test_users) {
            $sql .= ' and ph.u_id not in (' . $test_users . ')';
        }
        $sql .= ' group by b_id order by (count(*) * p.price) desc';

        $bind = array($today, $today, $cp_id, $begin_date, $end_date);

        global $app;
        return $app['db']->fetchAll($sql, $bind);
    }

    public static function getWholePartSalesList($begin_date, $end_date)
    {
        if (!$begin_date) {
            $begin_date = '0000-00-00';
        }
        if (!$end_date) {
            $end_date = date('Y-m-d');
        }

        $sql = <<<EOT
select date(ph.timestamp) purchase_date, p.b_id, b.title b_title, ph.p_id, p.title p_title, sum(if(is_paid=0, 1, 0)) free_download, sum(if(is_paid=1, 1, 0)) charged_download, sum(coin_amount) total_sales from purchase_history ph
 left join (select id, b_id, title from part) p on p.id = ph.p_id
 left join (select id, title from book) b on b.id = p.b_id
where date(ph.timestamp) >= ? and date(ph.timestamp) <= ?
EOT;
        $test_users = TestUser::getConcatUidList(true);
        if ($test_users) {
            $sql .= ' and ph.u_id not in (' . $test_users . ')';
        }
        $sql .= ' group by ph.p_id, date(ph.timestamp) order by ph.timestamp desc';

        $bind = array($begin_date, $end_date);

        global $app;
        return $app['db']->fetchAll($sql, $bind);
    }

    public static function getPartSalesList($b_id, $begin_date, $end_date)
    {
        if (!$begin_date) {
            $begin_date = '0000-00-00';
        }
        if (!$end_date) {
            $end_date = date('Y-m-d');
        }

        $test_users = TestUser::getConcatUidList(true);
        if ($test_users) {
            $sql = <<<EOT
select p.id p_id, p.seq, p.title, p.price, ifnull(free_download, 0) free_download, ifnull(charged_download, 0) charged_download, ifnull(total_coin_amount, 0) total_coin_amount from part p
 left join (select p_id, count(*) free_download from purchase_history where coin_amount = 0 and date(timestamp) >= ? and date(timestamp) <= ? and u_id not in ({$test_users}) group by p_id) ph on p.id = ph.p_id
 left join (select p_id, count(*) charged_download, sum(coin_amount) total_coin_amount from purchase_history where coin_amount > 0 and date(timestamp) >= ? and date(timestamp) <= ? and u_id not in ({$test_users}) group by p_id) ph2 on p.id = ph2.p_id
where b_id = ? order by p.seq
EOT;
        } else {
            $sql = <<<EOT
select p.id p_id, p.seq, p.title, p.price, ifnull(free_download, 0) free_download, ifnull(charged_download, 0) charged_download, ifnull(total_coin_amount, 0) total_coin_amount from part p
 left join (select p_id, count(*) free_download from purchase_history where coin_amount = 0 and date(timestamp) >= ? and date(timestamp) <= ? group by p_id) ph on p.id = ph.p_id
 left join (select p_id, count(*) charged_download, sum(coin_amount) total_coin_amount from purchase_history where coin_amount > 0 and date(timestamp) >= ? and date(timestamp) <= ? group by p_id) ph2 on p.id = ph2.p_id
where b_id = ? order by p.seq
EOT;
        }
        $bind = array($begin_date, $end_date, $begin_date, $end_date, $b_id);

        global $app;
        return $app['db']->fetchAll($sql, $bind);
    }
}
