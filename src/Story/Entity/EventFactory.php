<?php
namespace Story\Entity;

use Story\DB\EntityManagerProvider;

class EventFactory
{
    public static function create($ch_id, $u_id, $comment)
    {
        $event = new Event($ch_id, $u_id, $comment);
        $em = EntityManagerProvider::getEntityManager();
        $em->persist($event);
        $em->flush();
        return $event->id;
    }

    public static function update($id, $values)
    {
        global $app;
        return $app['db']->update('event_history', $values, array('id' => $id));
    }

    public static function delete($id)
    {
        global $app;
        return $app['db']->delete('event_history', array('id' => $id));
    }
}
