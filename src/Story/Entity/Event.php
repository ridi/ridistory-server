<?php
namespace Story\Entity;

/**
 * @Entity @Table(name="event_history")
 */
class Event
{
    /**
     * @Id @Column(type="integer") @GeneratedValue
     */
    public $id;

    /**
     * @Column(type="integer")
     */
    public $ch_id;

    /**
     * @Column(type="integer")
     */
    public $u_id;

    /**
     * @Column(type="string")
     */
    public $comment;

    /**
     * @Column(type="string")
     */
    public $timestamp;

    public function __construct($ch_id, $u_id, $comment)
    {
        $this->ch_id = $ch_id;
        $this->u_id = $u_id;
        $this->comment = $comment;
        $this->timestamp = date('Y-m-d H:i:s');
    }
}