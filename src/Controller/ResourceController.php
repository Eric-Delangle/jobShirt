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

namespace App\Controller;

use App\Entity\Order\Order;
use FOS\RestBundle\View\View;
use App\Entity\Order\OrderItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Sylius\Component\Resource\ResourceActions;
use Symfony\Component\HttpFoundation\Response;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController as BaseResourceController;

class ResourceController extends BaseResourceController
{
    
    public function showAction(Request $request, EntityManagerInterface $em): Response
    {
     

        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);
        $this->isGrantedOr403($configuration, ResourceActions::SHOW);
        $resource = $this->findOr404($configuration);

        // je récupere l'id de l'order
       $orderId = $resource->getId();
       dump($orderId);

    // je vais récuperer le metier enregistré  par le client afin de pouvoir l'afficher.
    $toutlorder = $em->getRepository(OrderItem::class)->findBy(['order'=>$orderId]);
  
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
