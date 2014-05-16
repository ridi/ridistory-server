<?php
namespace Story\Entity;

/**
 * @Entity @Table(name="notice")
 */
class Notice
{
    /**
     * @Id @Column(type="integer") @GeneratedValue
     */
    public $id;

    /**
     * @Column(type="string")
     */
    public $title;

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

    public function __construct()
    {
        $this->title = '제목이 없습니다';
        $this->is_visible = 0;
        $this->message = '';
        $this->reg_date = date('Y-m-d H:i:s');
    }
}