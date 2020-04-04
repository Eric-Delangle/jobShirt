<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Controller\Order;

use App\Entity\Order\Order;
use FOS\RestBundle\View\View;
use App\Entity\Order\OrderItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Sylius\Component\Resource\ResourceActions;
use Symfony\Component\HttpFoundation\Response;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController as ResourceController;

abstract class OrderResourceController extends ResourceController
{
    
    public function showAction(Request $request): Response
    {
     
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);
        $this->isGrantedOr403($configuration, ResourceActions::SHOW);
        $resource = $this->findOr404($configuration);

        // je récupere l'id de l'order
       $orderId = $resource->getId();    

    // je vais récuperer le metier enregistré  par le client afin de pouvoir l'afficher.
    // mais comme apparement je ne peux utiliser l'injection de dépendance ici , j'utilise $this->getDocrtine() qui a l'air de fonctionner.
    $toutlorder = $this->getDoctrine()->getRepository(OrderItem::class)->findBy(['order'=>$orderId]);
  
        $this->eventDispatcher->dispatch(ResourceActions::SHOW, $configuration, $resource);
        $view = View::create($resource);
        if ($configuration->isHtmlRequest()) {
            $view
                ->setTemplate($configuration->getTemplate(ResourceActions::SHOW . '.html'))
                ->setTemplateVar($this->metadata->getName())
                ->setData([
                    'metier' => $toutlorder,
                    'configuration' => $configuration,
                    'metadata' => $this->metadata,
                    'resource' => $resource,
                    $this->metadata->getName() => $resource,
                ])
            ;
        }
        return $this->viewHandler->handle($configuration, $view);
    }

}
