<?php
namespace Story\Model;

use Exception;

class RecommendedBook
{
    private $row;

    public function __construct($id)
    {
        $this->row = self::get($id);
        if ($this->row === false) {
            throw new Exception('Invalid Recommended Book Id: ' . $id);
        }
    }

    public function __get($k)
    {
        return $this->row[$k];
    }

    public static function get($id)
    {
        global $app;
        $rb = $app['db']->fetchAssoc('select * from recommended_book where id = ?', array($id));
        if ($rb) {
            $rb['cover_url'] = Book::getCoverUrl($rb['store_id']);
        }
        return $rb;
    }

    public static function hasRecommendedBooks($b_id)
    {
        $sql = <<<EOT
select count(*) from recommended_book where b_id = ? and store_id != '' and title != ''
EOT;
        global $app;
        return $app['db']->fetchColumn($sql, array($b_id));
    }

    public static function getRecommendedBookListByBid($b_id, $show_all = false)
    {
        $sql = <<<EOT
select * from recommended_book where b_id = ? order by id
EOT;
        global $app;
        $recommended_books = $app['db']->fetchAll($sql, array($b_id));
        foreach ($recommended_books as $key => &$rb) {
            if ($rb) {
                $rb['cover_url'] = Book::getCoverUrl($rb['store_id']);

                if (($rb['store_id'] == '' || $rb['title'] == '') && !$show_all) {
                    unset($recommended_books[$key]);
                }
            }
        }

        return $recommended_books;
    }

    public static function create($b_id)
    {
        global $app;
        $app['db']->insert('recommended_book', array('b_id' => $b_id));
        return $app['db']->lastInsertId();
    }

    public static function update($id, $values)
    {
        global $app;
        return $app['db']->update('recommended_book', $values, array('id' => $id));
    }

    public static function delete($id)
    {
        global $app;
        return $app['db']->delete('recommended_book', array('id' => $id));
    }

    public static function deleteByBid($b_id)
    {
        global $app;
        return $app['db']->delete('recommended_book', array('b_id' => $b_id));
    }
}