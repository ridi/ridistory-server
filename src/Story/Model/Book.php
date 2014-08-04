<?php
namespace Story\Model;

use Doctrine\DBAL\Connection;

class Book
{
    const ALL_FREE = 'ALL_FREE';
    const ALL_CHARGED = 'ALL_CHARGED';
    const SALES_CLOSED = 'SALES_CLOSED';
    const ALL_CLOSED = 'ALL_CLOSED';

    public static function get($id)
    {
        $sql = <<<EOT
select cp.name cp_name, b.* from book b
 left join cp_account cp on b.cp_id = cp.id
where b.id = ?
EOT;

        global $app;
        $b = $app['db']->fetchAssoc($sql, array($id));
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
            $b['ridibooks_sale_url'] = Book::getRidibooksSaleUrl($b['sale_store_id']);
            $b['ridibooks_sale_cover_url'] = Book::getCoverUrl($b['sale_store_id']);
        }
        return $b;
    }

    public static function getWholeList()
    {
        $today = date('Y-m-d H:00:00');
        $sql = <<<EOT
select count(part.b_id) uploaded_part_count, ifnull(open_part_count, 0) open_part_count, cp.name cp_name, b.* from book b
 left join (select b_id, count(*) open_part_count from part where begin_date <= ? and end_date >= ? group by b_id) pc on b.id = pc.b_id
 left join cp_account cp on cp.id = b.cp_id
 left join part on b.id = part.b_id group by b.id, part.b_id
order by b.begin_date desc
EOT;
        $bind = array($today, $today);
        global $app;
        return $app['db']->fetchAll($sql, $bind);
    }

    public static function getOpenedBookList($ignore_adult_only = 0)
    {
        $sql = <<<EOT
SELECT * FROM book b WHERE b.begin_date <= ? AND b.end_date >= ?
EOT;
        $today = date('Y-m-d H:00:00');

        global $app;
        $opened_books = $app['db']->fetchAll($sql, array($today, $today));
        return self::setAdditionalInfo($opened_books, $ignore_adult_only, true);
    }

    public static function getCompletedBookList($ignore_adult_only = 0)
    {
        $sql = <<<EOT
select b.*, ifnull(open_part_count, 0) open_part_count from book b
 left join (select b_id, count(*) open_part_count from part group by b_id) pc on b.id = pc.b_id
where b.end_date < ? and end_action_flag != 'ALL_CLOSED'
EOT;
        $today = date('Y-m-d H:00:00');

        global $app;
        $completed_books = $app['db']->fetchAll($sql, array($today));
        return self::setAdditionalInfo($completed_books, $ignore_adult_only, false);
    }

    private function setAdditionalInfo($books, $ignore_adult_only, $is_opened_book_list)
    {
        global $app;

        $b_ids = array();
        foreach ($books as $b) {
            $b_ids[] = $b['id'];
        }

        if ($is_opened_book_list) {
            $last_updates = self::getLastUpdated($b_ids);
            $open_part_count = self::getOpenPartCount($b_ids);
        }

        $cache_key = 'like_sum_' . (($is_opened_book_list) ? 0 : 1) . '_v2';
        $like_sum = $app['cache']->fetch(
            $cache_key,
            function () use ($b_ids) {
                return Book::getLikeSum($b_ids);
            },
            60 * 30
        );

        foreach ($books as &$b) {
            $b['cover_url'] = Book::getCoverUrl($b['store_id']);
            $b['ridibooks_sale_url'] = Book::getRidibooksSaleUrl($b['sale_store_id']);
            $b['ridibooks_sale_cover_url'] = Book::getCoverUrl($b['sale_store_id']);

            // TODO: iOS 앱 업데이트 후 아래 코드 제거할 것
            // iOS에서 시간 영역을 파싱하지 못하는 문제가 있어 하위호환을 위해 기존처럼 날짜만 내려줌.
            $b['begin_date'] = substr($b['begin_date'], 0, 10);
            $b['end_date'] = substr($b['end_date'], 0, 10);

            $b['like_sum'] = isset($like_sum[$b['id']]) ? $like_sum[$b['id']] : '0';

            if ($is_opened_book_list) {
                $b['last_update'] = in_array($b['id'], $last_updates) ? '1' : '0';
                $b['open_part_count'] = isset($open_part_count[$b['id']]) ? $open_part_count[$b['id']] : '0';
                $b['is_completed'] = ($b['total_part_count'] <= $b['open_part_count']) ? '1' : '0';
            }else {
                $b['is_completed'] = '1';
                $b['last_update'] = '0';

                // 종료액션이 판매종료이고, 완결일이 오늘 날짜 이전일 경우 판매종료
                if ($b['end_action_flag'] == Book::SALES_CLOSED
                    && strtotime($b['end_date']) < strtotime(date('Y-m-d H:00:00'))) {
                    $b['is_sales_closed'] = '1';
                } else {
                    $b['is_sales_closed'] = '0';
                }
            }

            if ($ignore_adult_only) {
                $b['adult_only'] = '0';
            }
        }

        return $books;
    }

    /**
     * begin_date 를 지난 당일, begin_date 다음날인 경우 last_updated = true
     * @param $b_ids
     * @return array
     */
    private static function getLastUpdated($b_ids)
    {
        $sql = <<<EOT
select b_id from part
where begin_date >= ? and begin_date <= ?
group by b_id
having b_id in (?)
EOT;
        $yesterday = date('Y-m-d H:i:s', mktime(0, 0, 0, date("m"), date("d") - 1, date("Y")));
        $today = date('Y-m-d H:00:00');

        global $app;
        $ar = $app['db']->fetchAll($sql,
            array(
                $yesterday,
                $today,
                $b_ids
            ), array(
                \PDO::PARAM_STR,
                \PDO::PARAM_STR,
                Connection::PARAM_INT_ARRAY
            )
        );

        $last_updates = array();
        foreach ($ar as $r) {
            $last_updates[] = $r['b_id'];
        }
        return $last_updates;
    }

    public function getLikeSum($b_ids)
    {
        $sql = <<<EOT
select b_id, sum(num_likes) like_sum from part
where b_id in (?)
group by b_id
EOT;
        global $app;
        $ar = $app['db']->fetchAll($sql, array($b_ids), array(Connection::PARAM_INT_ARRAY));

        $like_sum = array();
        foreach ($ar as $r) {
            $like_sum[$r['b_id']] = $r['like_sum'];
        }
        return $like_sum;
    }

    private function getOpenPartCount($b_ids)
    {
        $sql = <<<EOT
select b_id, count(*) open_part_count from part
where begin_date <= ? and end_date >= ?
group by b_id
having b_id in (?)
EOT;
        $today = date('Y-m-d H:00:00');

        global $app;
        $ar = $app['db']->fetchAll($sql,
            array(
                $today,
                $today,
                $b_ids
            ),
            array(
                \PDO::PARAM_STR,
                \PDO::PARAM_STR,
                Connection::PARAM_INT_ARRAY
            )
        );

        $open_part_count = array();
        foreach ($ar as $r) {
            $open_part_count[$r['b_id']] = $r['open_part_count'];
        }
        return $open_part_count;
    }

    public static function getListByIds(array $b_ids, $with_part_info = false, $ignore_adult_only = false)
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
                    Connection::PARAM_INT_ARRAY
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
                array(Connection::PARAM_INT_ARRAY)
            );
        }

        $today = date('Y-m-d H:i:s');
        $ar = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($ar as &$b) {
            $b['cover_url'] = Book::getCoverUrl($b['store_id']);
            $b['ridibooks_sale_url'] = Book::getRidibooksSaleUrl($b['sale_store_id']);
            $b['ridibooks_sale_cover_url'] = Book::getCoverUrl($b['sale_store_id']);

            if (strtotime($b['end_date']) < strtotime($today)) {
                $b['is_completed'] = '1';
            }

            if ($ignore_adult_only) {
                $b['adult_only'] = '0';
            }
        }

        return $ar;
    }

    public static function getListByCpId($cp_id)
    {
        $sql = <<<EOT
select * from book where cp_id = ?
EOT;
        global $app;
        return $app['db']->fetchAll($sql, array($cp_id));
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

    public static function getRidibooksSaleUrl($sales_store_id)
    {
        if ($sales_store_id != null) {
            return 'http://ridibooks.com/v2/Detail/index?id=' . $sales_store_id;
        } else {
            return '';
        }
    }

    /*
     * Book Intro
     */
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

    public static function deleteIntro($b_id)
    {
        global $app;
        return $app['db']->delete('book_intro', array('b_id' => $b_id));
    }
}
