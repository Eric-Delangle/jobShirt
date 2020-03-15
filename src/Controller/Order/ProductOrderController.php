<?php

declare(strict_types=1);

namespace App\Controller\Order;

use App\Form\JobType;
use App\Entity\Order\Metier;
use FOS\RestBundle\View\View;
use App\Repository\MetierRepository;
use Sylius\Component\Order\SyliusCartEvents;
use Symfony\Component\HttpFoundation\Request;
use Sylius\Component\Resource\ResourceActions;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\EventDispatcher\GenericEvent;
use Sylius\Bundle\OrderBundle\Controller\OrderController;
use Symfony\Component\HttpKernel\Exception\HttpException;


class ProductOrderController extends OrderController
{
   

    public function summaryAction(Request $request): Response
    {
       
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);

        $cart = $this->getCurrentCart();
        if (null !== $cart->getId()) {
            $cart = $this->getOrderRepository()->findCartById($cart->getId());
        }

        if (!$configuration->isHtmlRequest()) {
            return $this->viewHandler->handle($configuration, View::create($cart));
        }

        $form = $this->resourceFormFactory->create($configuration, $cart);

        $view = View::create()
            ->setTemplate($configuration->getTemplate('summary.html'))
            ->setData([
                'cart' => $cart,
                'form' => $form->createView(),
            ])
        ;

        return $this->viewHandler->handle($configuration, $view);
       
    }

    /* TEST */

    public function choixMetier(Request $request, MetierRepository $repometier)
    { 
        $metier = $repometier->findById();
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);

        $view = View::create()
        ->setTemplate($configuration->getTemplate('summary.html'))
        ->setData([
            
            'metier' => $metier,
         
        ])
    ;
        return $this->viewHandler->handle($configuration, $view);
    }

    /* FIN TEST */

    public function saveAction(Request $request): Response
    {
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);

        $this->isGrantedOr403($configuration, ResourceActions::UPDATE);
        $resource = $this->getCurrentCart();

        $form = $this->resourceFormFactory->create($configuration, $resource);

        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'], true) && $form->handleRequest($request)->isValid()) {
            $resource = $form->getData();

            $event = $this->eventDispatcher->dispatchPreEvent(ResourceActions::UPDATE, $configuration, $resource);

            if ($event->isStopped() && !$configuration->isHtmlRequest()) {
                throw new HttpException($event->getErrorCode(), $event->getMessage());
            }
            if ($event->isStopped()) {
                $this->flashHelper->addFlashFromEvent($configuration, $event);

                return $this->redirectHandler->redirectToResource($configuration, $resource);
            }

            if ($configuration->hasStateMachine()) {
                $this->stateMachine->apply($configuration, $resource);
            }

            $this->eventDispatcher->dispatchPostEvent(ResourceActions::UPDATE, $configuration, $resource);

            $this->getEventDispatcher()->dispatch(SyliusCartEvents::CART_CHANGE, new GenericEvent($resource));
            $this->manager->flush();

            if (!$configuration->isHtmlRequest()) {
                return $this->viewHandler->handle($configuration, View::create(null, Response::HTTP_NO_CONTENT));
            }

            $this->flashHelper->addSuccessFlash($configuration, ResourceActions::UPDATE, $resource);

            return $this->redirectHandler->redirectToResource($configuration, $resource);
        }

        if (!$configuration->isHtmlRequest()) {
            return $this->viewHandler->handle($configuration, View::create($form, Response::HTTP_BAD_REQUEST));
        }

        $view = View::create()
            ->setData([
                'configuration' => $configuration,
                $this->metadata->getName() => $resource,
                'form' => $form->createView(),
                'cart' => $resource,
            ])
            ->setTemplate($configuration->getTemplate(ResourceActions::UPDATE . '.html'))
        ;

        return $this->viewHandler->handle($configuration, $view);
    }

}
