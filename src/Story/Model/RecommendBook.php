<?php
namespace Story\Model;

class RecommendBook
{
    private $row;

    public function __construct($id)
    {
        $this->row = self::get($id);
        if ($this->row === false) {
            throw new \Exception('Invalid Recommend Book Id: ' . $id);
        }
    }

    public function __get($k)
    {
        return $this->row[$k];
    }

    public static function get($id)
    {
        global $app;
        $rb = $app['db']->fetchAssoc('select * from recommend_book where id = ?', array($id));
        if ($rb) {
            $rb['cover_url'] = Book::getCoverUrl($rb['store_id']);
        }
        return $rb;
    }

    public static function getRecommendBookListByBid($b_id)
    {
        $sql = <<<EOT
select * from recommend_book where b_id = ?
EOT;
        global $app;
        $recommend_books = $app['db']->fetchAll($sql, array($b_id));
        foreach ($recommend_books as &$rb) {
            if ($rb) {
                $rb['cover_url'] = Book::getCoverUrl($rb['store_id']);
            }
        }
        return $recommend_books;
    }

    public static function create($b_id)
    {
        global $app;
        $app['db']->insert('recommend_book', array('b_id' => $b_id));
        return $app['db']->lastInsertId();
    }

    public static function update($id, $values)
    {
        global $app;
        return $app['db']->update('recommend_book', $values, array('id' => $id));
    }

    public static function delete($id)
    {
        global $app;
        return $app['db']->delete('recommend_book', array('id' => $id));
    }
}