<?php

class Buyer
{
    public static function add($google_id)
    {
        global $app;
        $app['db']->insert('buyer_user', compact('google_id'));;
        return $app['db']->lastInsertId();
    }

    public static function get($id)
    {
        $sql = <<<EOT
select u.*, ifnull(coin_balance, 0) coin_balance from buyer_user u
 left join (select u_id, sum(amount) coin_balance from coin_history) ch on u.id = ch.u_id
where id = ?
EOT;
        global $app;
        return $app['db']->fetchAssoc($sql, array($id));
    }

    public static function getByGoogleAccount($google_id)
    {
        $sql = <<<EOT
select u.*, ifnull(coin_balance, 0) coin_balance from buyer_user u
 left join (select u_id, sum(amount) coin_balance from coin_history) ch on u.id = ch.u_id
where google_id = ?
EOT;
        global $app;
        return $app['db']->fetchAssoc($sql, array($google_id));
    }

    public static function getWholeList()
    {
        $sql = <<<EOT
select u.*, ifnull(coin_balance, 0) coin_balance from buyer_user u
 left join (select u_id, sum(amount) coin_balance from coin_history) ch on u.id = ch.u_id
order by google_reg_date desc
EOT;
        global $app;
        return $app['db']->fetchAll($sql);
    }

    public static function getCoinInList($id)
    {
        global $app;
        return $app['db']->fetchAll("select * from coin_history where amount > 0 and u_id = ? order by timestamp desc", array($id));;
    }

    public static function getCoinOutList($id)
    {
        $sql = <<<EOT
select ch.id, ch.u_id, ABS(ch.amount) amount, ch.timestamp, ph.p_id, p.b_id, p.store_id, p.title, p.seq, p.begin_date, p.end_date  from coin_history ch
 left join (select u_id, p_id from purchase_history) ph on ph.u_id = ch.u_id
 left join (select * from part) p on p.id = ph.p_id
where ch.amount < 0 and ch.u_id = ? order by timestamp desc
EOT;
        global $app;
        return $app['db']->fetchAll($sql, array($id));;
    }
}
