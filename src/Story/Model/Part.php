<?php
namespace Story\Model;

class Part
{
    private $row;

    public function __construct($id)
    {
        $this->row = self::get($id);
        if ($this->row === false) {
            throw new Exception('Invalid Part Id: ' . $id);
        }
    }

    public function isOpened()
    {
        $today = date('Y-m-d H:00:00');
        return $this->row['begin_date'] <= $today && $this->row['end_date'] >= $today;
    }

    public function __get($k)
    {
        return $this->row[$k];
    }

    public static function get($id)
    {
        global $app;
        $p = $app['db']->fetchAssoc('select * from part where id = ?', array($id));
        if ($p !== false) {
            self::_fill_additional($p);
        }
        return $p;
    }

    public static function isOpenedPart($p_id, $store_id)
    {
        $p = new Part($p_id);
        return $p->isOpened() && $p->store_id == $store_id;
    }

    public static function getListByBid($b_id, $with_social_info = false)
    {
        global $app;

        if ($with_social_info) {
            $today = date('Y-m-d H:00:00');
            $sql = <<<EOT
select p.*, ifnull(like_count, 0) like_count, ifnull(comment_count, 0) comment_count from part p
 left join (select p_id, count(*) like_count from user_part_like group by p_id) l on p.id = l.p_id
 left join (select p_id, count(*) comment_count from part_comment group by p_id) c on p.id = c.p_id
where b_id = ? and begin_date <= ? and end_date >= ?
order by seq
EOT;
            $bind = array($b_id, $today, $today);
        } else {
            $sql = 'select * from part where b_id = ? order by seq';
            $bind = array($b_id);
        }

        $ar = $app['db']->fetchAll($sql, $bind);
        foreach ($ar as &$p) {
            self::_fill_additional($p);
        }

        return $ar;
    }

    public static function getOpendCount($b_id)
    {
        $sql = "select count(*) open_part_count from part where b_id = ? and begin_date <= ? and end_date >= ?";
        $today = date('Y-m-d H:00:00');
        $r = $app['db']->fetchColumn($sql, array($b_id, $today, $today));
        return $r;
    }

    private static function _fill_additional(&$p)
    {
        $p['meta_url'] = STORE_API_BASE_URL . '/api/book/metadata?b_id=' . $p['store_id'];

        // v1: token only
        //$query = '?token=' . $p['store_id'];
        //$p['download_url'] = STORE_API_BASE_URL . '/api/story/download_part.php' . $query;

        // v2: v=2&store_id=xxx&p_id=xxx
        $query = http_build_query(array('v' => 2, 'store_id' => $p['store_id'], 'p_id' => $p['id']));
        $p['download_url'] = STORE_API_BASE_URL . '/api/story/download_part.php?' . $query;
    }

    public static function create($b_id)
    {
        global $app;
        $app['db']->insert('part', array('b_id' => $b_id));
        return $app['db']->lastInsertId();
    }

    public static function update($id, $values)
    {
        global $app;
        return $app['db']->update('part', $values, array('id' => $id));
    }

    public static function delete($id)
    {
        global $app;
        return $app['db']->delete('part', array('id' => $id));
    }
}

