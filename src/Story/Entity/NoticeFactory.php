<?php
namespace Story\Entity;

use Story\DB\EntityManagerProvider;

class NoticeFactory
{
    /**
     * @param $id
     * @param bool $exclude_invisible
     * @return \Story\Entity\Notice
     */
    public static function get($id, $exclude_invisible = true)
    {
        $bind = array('id' => $id);
        if ($exclude_invisible) {
            $bind['is_visible'] = 1;
        }

        $em = EntityManagerProvider::getEntityManager();
        $notice = $em->getRepository('Story\Entity\Notice')
            ->findOneBy($bind);
        return $notice;
    }

    /**
     * @param bool $exclude_invisible
     * @return \Story\Entity\Notice
     */
    public static function getLatestNotice($exclude_invisible = true)
    {
        $bind = array();
        if ($exclude_invisible) {
            $bind['is_visible'] = 1;
        }

        $em = EntityManagerProvider::getEntityManager();
        $latest_notice = $em->getRepository('Story\Entity\Notice')
            ->findOneBy($bind, array('reg_date' => 'DESC'));
        return $latest_notice;
    }

    /**
     * @param bool $exclude_invisible
     * @return \Story\Entity\Notice[]
     */
    public static function getList($exclude_invisible = true)
    {
        $bind = array();
        if ($exclude_invisible) {
            $bind['is_visible'] = 1;
        }

        $em = EntityManagerProvider::getEntityManager();
        $notices = $em->getRepository('Story\Entity\Notice')
            ->findBy($bind, array('reg_date' => 'DESC'));

        return $notices;
    }

    public static function create()
    {
        $notice = new Notice();
        $em = EntityManagerProvider::getEntityManager();
        $em->persist($notice);
        $em->flush();
        return $notice->id;
    }

    public static function update($id, $values)
    {
        global $app;
        return $app['db']->update('notice', $values, array('id' => $id));
    }

    public static function delete($id)
    {
        global $app;
        return $app['db']->delete('notice', array('id' => $id));
    }
}
