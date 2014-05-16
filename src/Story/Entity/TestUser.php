<?php
namespace Story\Entity;

/**
 * @Entity @Table(name="test_user")
 */
class TestUser
{
    /**
     * @Id @Column(type="integer")
     */
    public $u_id;

    /**
     * @Column(type="string")
     */
    public $comment;

    /**
     * @Column(type="integer")
     */
    public $is_active;

    public function __construct($u_id, $comment, $is_active)
    {
        $this->u_id = $u_id;
        $this->comment = $comment;
        $this->is_active = $is_active;
    }
}