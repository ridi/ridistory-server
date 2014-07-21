<?php
namespace Story\Model;

class PartComment
{
    const ADMIN_NICKNAME = '리디스토리';

    public static function add($p_id, $device_id, $nickname, $comment, $ip)
    {
        global $app;
        $r = $app['db']->insert('part_comment', compact('p_id', 'device_id', 'nickname', 'comment', 'ip'));
        return $r;
    }

    public static function delete($c_id)
    {
        global $app;
        $r = $app['db']->delete('part_comment', array('id' => $c_id));
        return $r === 1;
    }

    public static function getComments($p_id, $exclude_admin_comment = true)
    {
        $sql = <<<EOT
select * from part_comment
where p_id = ?
EOT;
        if ($exclude_admin_comment) {
            $sql .= ' and nickname != "' . PartComment::ADMIN_NICKNAME . '"';
        }
        $sql .= ' order by timestamp desc limit 100';

        global $app;
        return $app['db']->fetchAll($sql, array($p_id));
    }

    public static function getListByOffsetAndSize($offset, $limit)
    {
        $sql = <<<EOT
select pc.*, p.seq, b.title from part_comment pc
 left join part p on p.id = pc.p_id
 left join book b on b.id = p.b_id
order by pc.id desc limit ?, ?
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
select pc.*, p.seq, b.title from part_comment pc
 left join part p on p.id = pc.p_id
 left join book b on b.id = p.b_id
EOT;
        if ($search_type == 'book_title') {
            $sql .= ' where b.title like ?';
            $bind = array('%' . $search_keyword . '%');
        } else if ($search_type == 'nickname') {
            $sql .= ' where pc.nickname like ?';
            $bind = array('%' . $search_keyword . '%');
        } else if ($search_type == 'ip_addr') {
            $ip_addr = ip2long($search_keyword);
            $sql .= ' where pc.ip = ?';
            $bind = array($ip_addr);
        } else {
            $sql = null;
            $bind = null;
        }
        $sql .= ' order by pc.timestamp desc';

        global $app;
        return $app['db']->fetchAll($sql, $bind);
    }

    public static function getAdminComments($p_id)
    {
        $admin_nickname = PartComment::ADMIN_NICKNAME;

        $sql = <<<EOT
select * from part_comment
where p_id = ? and nickname = '{$admin_nickname}' order by timestamp desc
EOT;
        global $app;
        return $app['db']->fetchAll($sql, array($p_id));
    }

    public static function getCommentsCount($p_id, $exclude_admin_comment = true)
    {
        $sql = <<<EOT
select count(*) from part_comment
where p_id = ?
EOT;
        if ($exclude_admin_comment) {
            $sql .= ' and nickname !="' . PartComment::ADMIN_NICKNAME . '"';
        }

        global $app;
        return $app['db']->fetchColumn($sql, array($p_id));
    }
}
