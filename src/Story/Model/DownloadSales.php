<?php
namespace Story\Model;

class DownloadSales
{
    public static function get($b_id)
    {
        $sql = <<<EOT
select b.id b_id, b.title, b.author, b.begin_date, b.end_date, b.end_action_flag, sum(if(coin_amount=0, 1, 0)) free_download, sum(if(coin_amount!=0, 1, 0)) charged_download, sum(coin_amount) total_sales from purchase_history ph
 left join (select id, b_id, price from part) p on p.id = ph.p_id
 left join (select * from book) b on b.id = p.b_id
where b_id = ?
EOT;
        global $app;
        return $app['db']->fetchAssoc($sql, array($b_id));
    }

    public static function getWholeList()
    {
        $sql = <<<EOT
select b.id b_id, b.title, b.author, b.begin_date, b.end_date, b.end_action_flag, sum(if(coin_amount=0, 1, 0)) free_download, sum(if(coin_amount!=0, 1, 0)) charged_download, sum(coin_amount) total_sales from purchase_history ph
 left join (select id, b_id, price from part) p on p.id = ph.p_id
 left join (select * from book) b on b.id = p.b_id
group by b_id order by (count(*) * p.price) desc
EOT;
        global $app;
        return $app['db']->fetchAll($sql);
    }

    public static function getDetail($b_id)
    {
        $sql = <<<EOT
select p.id p_id, p.seq, p.title, ifnull(total_free_download, 0) total_free_download, ifnull(total_charged_download, 0) total_charged_download, ifnull(total_coin_amount, 0) total_coin_amount from part p
 left join (select p_id, count(*) total_free_download from purchase_history where coin_amount = 0 group by p_id) ph on p.id = ph.p_id
 left join (select p_id, count(*) total_charged_download, sum(coin_amount) total_coin_amount from purchase_history where coin_amount > 0 group by p_id) ph2 on p.id = ph2.p_id
where b_id = ? order by p.seq
EOT;
        global $app;
        return $app['db']->fetchAll($sql, array($b_id));
    }
}
