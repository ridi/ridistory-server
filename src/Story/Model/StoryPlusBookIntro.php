<?php
namespace Story\Model;

class StoryPlusBookIntro
{
    public static function create($b_id)
    {
        global $app;
        $app['db']->insert('storyplusbook_intro', array('b_id' => $b_id));
        return $app['db']->lastInsertId();
    }

    public static function get($id)
    {
        global $app;
        return $app['db']->fetchAssoc('select * from storyplusbook_intro where id = ?', array($id));
    }

    public static function delete($id)
    {
        global $app;
        return $app['db']->delete('storyplusbook_intro', array('id' => $id));
    }

    public static function update($id, $values)
    {
        global $app;
        return $app['db']->update('storyplusbook_intro', $values, array('id' => $id));
    }

    public static function getListByBid($b_id)
    {
        global $app;

        $sql = 'select * from storyplusbook_intro where b_id = ?';
        $bind = array($b_id);

        $ar = $app['db']->fetchAll($sql, $bind);
        return $ar;
    }
}