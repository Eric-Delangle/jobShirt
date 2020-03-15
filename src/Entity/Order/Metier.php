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

}
