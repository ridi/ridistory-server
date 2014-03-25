<?php
namespace Story\Model;

class StoryPlusBook
{
    public static function get($id)
    {
        global $app;
        $sql = <<<EOT
select storyplusbook.*, ifnull(like_sum, 0) like_sum from storyplusbook
	left join (select b_id, count(*) like_sum from user_storyplusbook_like group by b_id) L on storyplusbook.id = L.b_id
where id = ?
EOT;
        $b = $app['db']->fetchAssoc($sql, array($id));
        if ($b) {
            $b['cover_url'] = self::getCoverUrl($b['store_id']);
            self::_fill_additional($b);
        }
        return $b;
    }

    public static function getWholeList()
    {
        $sql = "select * from storyplusbook";

        global $app;
        return $app['db']->fetchAll($sql);
    }

    public static function getOpenedBookList()
    {
        $today = date('Y-m-d H:i:s');
        $sql = <<<EOT
select storyplusbook.*, ifnull(like_sum, 0) like_sum from storyplusbook
	left join (select b_id, count(*) like_sum from storyplusbook, user_storyplusbook_like where storyplusbook.id = user_storyplusbook_like.b_id group by b_id) L on storyplusbook.id = L.b_id
where begin_date <= ? and end_date >= ?
order by priority desc
EOT;

        $bind = array($today, $today);

        global $app;
        $ar = $app['db']->fetchAll($sql, $bind);

        foreach ($ar as &$b) {
            $b['cover_url'] = self::getCoverUrl($b['store_id']);
        }

        return $ar;
    }

    public static function create()
    {
        global $app;
        $app['db']->insert('storyplusbook', array());
        return $app['db']->lastInsertId();
    }

    public static function update($id, $values)
    {
        global $app;
        return $app['db']->update('storyplusbook', $values, array('id' => $id));
    }

    public static function delete($id)
    {
        global $app;
        return $app['db']->delete('storyplusbook', array('id' => $id));
    }

    public static function getCoverUrl($store_id)
    {
        return 'http://misc.ridibooks.com/cover/' . $store_id . '/xxlarge';
    }

    private static function _fill_additional(&$b)
    {
        $b['meta_url'] = STORE_API_BASE_URL . '/api/book/metadata?b_id=' . $b['store_id'];

        $query = http_build_query(array('store_id' => $b['store_id'], 'storyplusbook_id' => $b['id']));
        $b['download_url'] = STORE_API_BASE_URL . '/api/story/download_storyplusbook.php?' . $query;
    }
}
