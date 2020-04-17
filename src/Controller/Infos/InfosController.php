<?php

declare(strict_types=1);

namespace App\Controller\Infos;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


class InfosController extends AbstractController
{
        
        public function infosAPropos():Response
        {

            return $this->render('@SyliusShop/Homepage/Infos/apropos.html.twig' );
            
        }
 
        public function conditions():Response
        {
            return $this->render('@SyliusShop/Homepage/Infos/conditions.html.twig');
            
        }
/*
        public function faqs():Response
        {
            return $this->render('@SyliusShop/Homepage/Infos/faqs.html.twig');
            
        }

        public function livraison():Response
        {
            return $this->render('@SyliusShop/Homepage/Infos/livraison.html.twig');
            
        }

        public function retour():Response
        {
            return $this->render('@SyliusShop/Homepage/Infos/retour.html.twig');
            
        }
        */
}
