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

use FOS\RestBundle\View\View;
use Sylius\Component\Order\CartActions;
use App\Repository\Order\ProductOrderRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sylius\Component\Order\Model\OrderItemInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Sylius\Bundle\OrderBundle\Controller\OrderItemController as BaseOrderItemController;

class ProductOrderItemController extends BaseOrderItemController
{

    
     /* TEST */

     public function addChoixMetier(Request $request, ProductOrderRepository $repometier)
     { 
        $metier = $repometier->find(['OrderItem' => 'metier']);
        $cart = $this->getCurrentCart();
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);
        $orderItem = $this->newResourceFactory->create($configuration, $this->factory);
       // $id = $this->getId();
       $form = $this->getFormFactory()->create(
        $configuration->getFormType(),
        $this->createAddToCartCommand($cart, $orderItem),
        $configuration->getFormOptions()
    );
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);
        

        $cart = $this->getCurrentCart();

        $cartManager = $this->getCartManager();
        $cartManager->persist($cart, $metier);
        $cartManager->flush();

       
        $resourceControllerEvent = $this->eventDispatcher->dispatchPostEvent(CartActions::ADD, $configuration, $cart, $metier);
        if ($resourceControllerEvent->hasResponse()) {
            return $resourceControllerEvent->getResponse();
        }

       
       
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

    public function addAction(Request $request): Response
    {
        $cart = $this->getCurrentCart();
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);

        $this->isGrantedOr403($configuration, CartActions::ADD);
        /** @var OrderItemInterface $orderItem */
        $orderItem = $this->newResourceFactory->create($configuration, $this->factory);

        $this->getQuantityModifier()->modify($orderItem, 1);

        $form = $this->getFormFactory()->create(
            $configuration->getFormType(),
            $this->createAddToCartCommand($cart, $orderItem),
            $configuration->getFormOptions()
        );

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            /** @var AddToCartCommandInterface $addToCartCommand */
            $addToCartCommand = $form->getData();

            $errors = $this->getCartItemErrors($addToCartCommand->getCartItem());
            if (0 < count($errors)) {
                $form = $this->getAddToCartFormWithErrors($errors, $form);

                return $this->handleBadAjaxRequestView($configuration, $form);
            }

            
            $event = $this->eventDispatcher->dispatchPreEvent(CartActions::ADD, $configuration, $orderItem);
            
            if ($event->isStopped() && !$configuration->isHtmlRequest()) {
                throw new HttpException($event->getErrorCode(), $event->getMessage());
            }
            if ($event->isStopped()) {
                $this->flashHelper->addFlashFromEvent($configuration, $event);

                return $this->redirectHandler->redirectToIndex($configuration, $orderItem);
            }

            
            $this->getOrderModifier()->addToOrder($addToCartCommand->getCart(), $addToCartCommand->getCartItem());

            $cartManager = $this->getCartManager();
            $cartManager->persist($cart);
            $cartManager->flush();

            $resourceControllerEvent = $this->eventDispatcher->dispatchPostEvent(CartActions::ADD, $configuration, $orderItem);
            if ($resourceControllerEvent->hasResponse()) {
                return $resourceControllerEvent->getResponse();
            }

            $this->flashHelper->addSuccessFlash($configuration, CartActions::ADD, $orderItem);

            if ($request->isXmlHttpRequest()) {
                return $this->viewHandler->handle($configuration, View::create([], Response::HTTP_CREATED));
            }

            return $this->redirectHandler->redirectToResource($configuration, $orderItem);
        }

        if (!$configuration->isHtmlRequest()) {
            return $this->handleBadAjaxRequestView($configuration, $form);
        }

        $view = View::create()
            ->setData([
                'configuration' => $configuration,
                $this->metadata->getName() => $orderItem,
                'form' => $form->createView(),
            ])
            ->setTemplate($configuration->getTemplate(CartActions::ADD . '.html'))
        ;

        return $this->viewHandler->handle($configuration, $view);
    }


}
