<?php
namespace Story\Entity;

use Story\DB\EntityManagerProvider;

class BookNoticeFactory
{
    /**
     * @param $id
     * @param bool $exclude_invisible
     * @return \Story\Entity\BookNotice
     */
    public static function get($id, $exclude_invisible = true)
    {
        $bind = array('id' => $id);
        if ($exclude_invisible) {
            $bind['is_visible'] = 1;
        }

        $em = EntityManagerProvider::getEntityManager();
        $book_notice = $em->getRepository('Story\Entity\BookNotice')
            ->findOneBy($bind);
        return $book_notice;
    }

    /**
     * @param $b_id
     * @param bool $exclude_invisible
     * @return \Story\Entity\BookNotice[]
     */
    public static function getList($b_id, $exclude_invisible = true)
    {
        $bind = array('b_id' => $b_id);
        if ($exclude_invisible) {
            $bind['is_visible'] = 1;
        }

        $em = EntityManagerProvider::getEntityManager();
        $book_notices = $em->getRepository('Story\Entity\BookNotice')
            ->findBy($bind, array('reg_date' => 'DESC'));

        return $book_notices;
    }

    public static function create($b_id)
    {
        $book_notice = new BookNotice($b_id);
        $em = EntityManagerProvider::getEntityManager();
        $em->persist($book_notice);
        $em->flush();
        return $book_notice->id;
    }

    public static function update($id, $values)
    {
        global $app;
        try {
            // b_id 와 message가 unique로 설정되어 있어, 오류가 발생할 수 있음.
            return $app['db']->update('book_notice', $values, array('id' => $id));
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function delete($id)
    {
        global $app;
        return $app['db']->delete('book_notice', array('id' => $id));
    }
}
