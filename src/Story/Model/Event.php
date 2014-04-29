<?php
namespace Story\Model;

class Event
{
    public static function add($values)
    {
        global $app;
        return $app['db']->insert('event_history', $values);
    }
}