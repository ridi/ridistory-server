<?php
namespace Story\Entity;

/**
 * @Entity @Table(name="recommended_book")
 */
class RecommendedBook
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
    public $store_id;

    /**
     * @Column(type="string")
     */
    public $title;

    public $cover_url;

    public $ridibooks_sale_url;

    public function __construct($b_id)
    {
        $this->b_id = $b_id;
        $this->store_id = '';
        $this->title = '';
    }
}