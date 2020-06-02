<?php

declare(strict_types=1);

namespace App\Entity\Order;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\OrderItem as BaseOrderItem;

/**
 * @ORM\Entity
 * @ORM\Table(name="sylius_order_item")
 */
class OrderItem extends BaseOrderItem
{


    /**
    *@ORM\Column(type="string", length=255)
    */
    protected $metier;

     /**
    *@ORM\Column(type="string", length=255)
    */
    protected $genre;


    public function getMetier()
    {
        return $this->metier;
    }
 
    public function setMetier($metier)
    {
        $this->metier = $metier;
    }


    public function getGenre()
    {
        return $this->genre;
    }
 
    public function setGenre($genre)
    {
        $this->genre = $genre;
    }
}
