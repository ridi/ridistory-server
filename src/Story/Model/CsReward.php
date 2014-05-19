<?php
namespace Story\Model;

class CsReward
{
    public static function add($values)
    {
        global $app;
        return $app['db']->insert('cs_reward_history', $values);
    }
}
