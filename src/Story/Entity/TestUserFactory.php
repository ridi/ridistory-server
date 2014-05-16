<?php
namespace Story\Entity;

use Story\DB\EntityManagerProvider;

class TestUserFactory
{
    public static function get($u_id)
    {
        $sql = <<<EOT
select bu.google_id, tu.* from test_user tu
 left join buyer_user bu on tu.u_id = bu.id
where u_id = ?
EOT;
        global $app;
        return $app['db']->fetchAssoc($sql, array($u_id));
    }

    public static function getList()
    {
        $sql = <<<EOT
select bu.google_id, tu.* from test_user tu
 left join buyer_user bu on tu.u_id = bu.id
EOT;
        global $app;
        return $app['db']->fetchAll($sql);
    }

    /**
     * @param bool $exclude_inactive
     * @return string
     */
    public static function getConcatUidList($exclude_inactive = true)
    {
        $bind = array();
        if ($exclude_inactive) {
            $bind['is_active'] = 1;
        }

        $em = EntityManagerProvider::getEntityManager();
        $test_users = $em->getRepository('Story\Entity\TestUser')
            ->findBy($bind);

        $test_u_ids = array();
        foreach ($test_users as $test_user) {
            if ($test_user) {
                array_push($test_u_ids, $test_user->u_id);
            }
        }
        return implode(',', $test_u_ids);
    }

    public static function create($u_id, $comment, $is_active)
    {
        $test_user = new TestUser($u_id, $comment, $is_active);
        $em = EntityManagerProvider::getEntityManager();
        $em->persist($test_user);
        $em->flush();
        return $test_user->u_id;
    }

    public static function update($u_id, $values)
    {
        global $app;
        return $app['db']->update('test_user', $values, array('u_id' => $u_id));
    }

    public static function delete($u_id)
    {
        global $app;
        return $app['db']->delete('test_user', array('u_id' => $u_id));
    }
}
