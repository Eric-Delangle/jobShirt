<?php

declare(strict_types=1);

namespace App\Entity\Order;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Order as BaseOrder;

/**
 * @ORM\Entity
 * @ORM\Table(name="sylius_order")
 */
class Order extends BaseOrder
{
    /**
    * @ORM\OneToOne(targetEntity="App\Entity\Order\Metier")
    * @ORM\JoinColumn(onDelete="SET NULL")
    */
    private $metier;

    
    public function getMetier()
    {
        return $this->metier;
    }

    public function setMetier(string $metier)
    {
        $this->metier = $metier;

        return $this;
    }
}
