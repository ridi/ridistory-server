<?php

class Book
{
    public static function get($id)
    {
        global $app;
        $b = $app['db']->fetchAssoc('select * from book where id = ?', array($id));
        if ($b) {
            /*
             * iOS 사용자들의 책 소개 상단에만 서비스 종료 공지가 표시되도록 소스 추가.
             */
            $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
            $is_iphone = (strpos($user_agent, 'iPhone') !== false);
            if ($is_iphone) {
                $ios_description_header = "**[공지] iOS 서비스 종료안내(종료일: 2014.03.14)**\n🔹기존 사용자는 정상적으로 이용이 가능합니다.\n🔹자세한 사항은 더보기>공지사항을 확인해주세요.";
                $b['short_description'] = $ios_description_header . "\n\n" . $b['short_description'];
            }

            $b['cover_url'] = Book::getCoverUrl($b['store_id']);
        }
        return $b;
    }

    public static function getWholeList()
    {
        $today = date('Y-m-d H:00:00');
        $sql = <<<EOT
select count(part.b_id) uploaded_part_count, ifnull(open_part_count, 0) open_part_count, b.* from book b
 left join (select b_id, count(*) open_part_count from part where begin_date <= ? and end_date >= ? group by b_id) pc on b.id = pc.b_id
 left join part on b.id = part.b_id group by b.id, part.b_id
order by begin_date desc
EOT;
        $bind = array($today, $today);
        global $app;
        return $app['db']->fetchAll($sql, $bind);
    }

    public static function getOpenedBookList($exclude_adult = true)
    {
        $today = date('Y-m-d H:00:00');
        $today_date = date('Y-m-d');
        $sql = <<<EOT
select ifnull(last_update, 0) last_update, ifnull(open_part_count, 0) open_part_count, ifnull(like_sum, 0) like_sum, b.* from book b
 left join (select b_id, count(*) open_part_count from part where begin_date <= ? and end_date >= ? group by b_id) pc on b.id = pc.b_id
 left join (select b_id, count(*) like_sum from user_part_like, part where p_id = part.id group by b_id) ls on b.id = ls.b_id
 left join (select b_id, 1 last_update from part where (date(begin_date) = ? and ? >= begin_date) or date_add(date(begin_date), INTERVAL 1 DAY) = ? group by b_id) p on b.id = p.b_id
where b.begin_date <= ? and end_date >= ?
EOT;
        if ($exclude_adult) {
            $sql .= " and adult_only = 0";
        }

        $bind = array($today, $today, $today_date, $today, $today_date, $today, $today);

        global $app;
        $ar = $app['db']->fetchAll($sql, $bind);

        foreach ($ar as &$b) {
            $b['cover_url'] = Book::getCoverUrl($b['store_id']);
            // TODO: iOS 앱 업데이트 후 아래 코드 제거할 것
            // iOS에서 시간 영역을 파싱하지 못하는 문제가 있어 하위호환을 위해 기존처럼 날짜만 내려줌.
            $b['begin_date'] = substr($b['begin_date'], 0, 10);
            $b['end_date'] = substr($b['end_date'], 0, 10);
        }

        return $ar;
    }

    public static function getListByIds(array $b_ids, $with_part_info = false)
    {
        if (count($b_ids) === 0) {
            return array();
        }

        global $app;

        if ($with_part_info) {
            $sql = <<<EOT
select ifnull(last_update, 0) last_update, ifnull(open_part_count, 0) open_part_count, b.* from book b
 left join (select b_id, count(*) open_part_count from part where begin_date <= ? and end_date >= ? group by b_id) pc on b.id = pc.b_id
 left join (select b_id, 1 last_update from part where (date(begin_date) = date(?) and ? >= begin_date) or date_add(date(begin_date), INTERVAL 1 DAY) = date(?) group by b_id) p on b.id = p.b_id
where b.id in (?)
EOT;
            $today = date('Y-m-d H:00:00');
            $stmt = $app['db']->executeQuery(
                $sql,
                array($today, $today, $today, $today, $today, $b_ids),
                array(
                    \PDO::PARAM_STR,
                    \PDO::PARAM_STR,
                    \PDO::PARAM_STR,
                    \PDO::PARAM_STR,
                    \PDO::PARAM_STR,
                    \Doctrine\DBAL\Connection::PARAM_INT_ARRAY
                )
            );
        } else {
            $sql = <<<EOT
select p.popularity, b.* from book b
 left join (select b_id, count(*) popularity from user_interest group by b_id) p on b.id = b_id
where b.id in (?)
EOT;
            $stmt = $app['db']->executeQuery(
                $sql,
                array($b_ids),
                array(\Doctrine\DBAL\Connection::PARAM_INT_ARRAY)
            );
        }

        $ar = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ar as &$b) {
            $b['cover_url'] = Book::getCoverUrl($b['store_id']);
        }

        return $ar;
    }

    public static function create()
    {
        global $app;
        $app['db']->insert('book', array());
        return $app['db']->lastInsertId();
    }

    public static function update($id, $values)
    {
        global $app;
        return $app['db']->update('book', $values, array('id' => $id));
    }

    public static function delete($id)
    {
        global $app;
        return $app['db']->delete('book', array('id' => $id));
    }

    public static function getCoverUrl($store_id)
    {
        return 'http://misc.ridibooks.com/cover/' . $store_id . '/xxlarge';
    }


    public static function createIntro($values)
    {
        global $app;
        return $app['db']->insert('book_intro', $values);
    }

    public static function getIntro($b_id)
    {
        global $app;
        return $app['db']->fetchAssoc('select * from book_intro where b_id = ?', array($b_id));
    }

    public static function updateIntro($b_id, $values)
    {
        global $app;
        return $app['db']->update('book_intro', $values, array('b_id' => $b_id));
    }
}
