<?php
namespace Story\Entity;

use Story\DB\EntityManagerProvider;

class CsRewardFactory
{
    public static function create($ch_id, $u_id, $comment)
    {
        $cs_reward = new CsReward($ch_id, $u_id, $comment);
        $em = EntityManagerProvider::getEntityManager();
        $em->persist($cs_reward);
        $em->flush();
        return $cs_reward->id;
    }

    public static function update($id, $values)
    {
        global $app;
        return $app['db']->update('cs_reward_history', $values, array('id' => $id));
    }

    public static function delete($id)
    {
        global $app;
        return $app['db']->delete('cs_reward_history', array('id' => $id));
    }
}
