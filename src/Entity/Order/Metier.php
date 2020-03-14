<?php

namespace App\Entity\Order;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\MetierRepository")
 */
class Metier
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $job;

    /**
    * @ORM\OneToOne(targetEntity="App\Entity\Order\Order", cascade={"remove"})
    * @ORM\JoinColumn(onDelete="SET NULL")
    */
    private $order;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJob()
    {
        return $this->job;
    }

    public function setJob(string $job)
    {
        $this->job = $job;

        return $this;
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function setOrder($order)
    {
        $this->order = $order;

        return $this;
    }
}
