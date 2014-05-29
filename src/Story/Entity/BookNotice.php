<?php
namespace Story\Entity;

/**
 * @Entity @Table(name="book_notice")
 */
class BookNotice
{
    /**
     * @Id @Column(type="integer") @GeneratedValue
     */
    public $id;

    /**
     * @Column(type="integer")
     */
    public $b_id;

    /**
     * @Column(type="string")
     */
    public $message;

    /**
     * @Column(type="string")
     */
    public $reg_date;

    /**
     * @Column(type="integer")
     */
    public $is_visible;

    public function __construct($b_id)
    {
        $this->b_id = $b_id;
        $this->message = '';
        $this->reg_date = date('Y-m-d H:i:s');
        $this->is_visible = 0;
    }
}