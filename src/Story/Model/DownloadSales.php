<?php
namespace Story\Model;

class DownloadSales
{
    public static function get($b_id)
    {
        $sql = <<<EOT
select b.id b_id, b.title, cp.id cp_id, cp.cp_account_name, b.royalty_percent, b.begin_date, b.end_date, b.end_action_flag, sum(if(coin_amount=0, 1, 0)) free_download, sum(if(coin_amount!=0, 1, 0)) charged_download, sum(coin_amount) total_sales from purchase_history ph
 left join (select id, b_id, price from part) p on p.id = ph.p_id
 left join (select * from book) b on b.id = p.b_id
 left join (select id, cp_account_name from cp_account) cp on cp.id = b.cp_id
where b_id = ?
EOT;
        global $app;
        return $app['db']->fetchAssoc($sql, array($b_id));
    }

    public static function getWholeList()
    {
        $today = date('Y-m-d H:00:00');

        $sql = <<<EOT
select b.id b_id, b.title, cp.id, cp_id, cp.cp_account_name, b.royalty_percent, ifnull(open_part_count, 0) open_part_count, b.total_part_count, b.begin_date, b.end_date, b.end_action_flag, sum(if(coin_amount=0, 1, 0)) free_download, sum(if(coin_amount!=0, 1, 0)) charged_download, sum(coin_amount) total_sales from purchase_history ph
 left join (select id, b_id, price from part) p on p.id = ph.p_id
 left join (select * from book) b on b.id = p.b_id
 left join (select b_id, count(*) open_part_count from part where begin_date <= ? and end_date >= ? group by b_id) pc on pc.b_id = b.id
 left join (select id, cp_account_name from cp_account) cp on cp.id = b.cp_id
group by b_id order by (count(*) * p.price) desc
EOT;
        $bind = array($today, $today);
        global $app;
        return $app['db']->fetchAll($sql, $bind);
    }

    public static function getPartSalesList($b_id)
    {
        $sql = <<<EOT
select p.id p_id, p.seq, p.title, p.price, ifnull(free_download, 0) free_download, ifnull(charged_download, 0) charged_download, ifnull(total_coin_amount, 0) total_coin_amount from part p
 left join (select p_id, count(*) free_download from purchase_history where coin_amount = 0 group by p_id) ph on p.id = ph.p_id
 left join (select p_id, count(*) charged_download, sum(coin_amount) total_coin_amount from purchase_history where coin_amount > 0 group by p_id) ph2 on p.id = ph2.p_id
where b_id = ? order by p.seq
EOT;
        global $app;
        return $app['db']->fetchAll($sql, array($b_id));
    }
}
