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
use Symfony\Component\Form\FormError;
use Sylius\Component\Order\CartActions;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\Order\ProductOrderRepository;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Sylius\Component\Order\Model\OrderItemInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Sylius\Bundle\OrderBundle\Controller\AddToCartCommandInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Bundle\OrderBundle\Controller\OrderItemController as BaseOrderItemController;


class OrderItemController extends BaseOrderItemController
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

    public function removeAction(Request $request): Response
    {
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);

        $this->isGrantedOr403($configuration, CartActions::REMOVE);
        /** @var OrderItemInterface $orderItem */
        $orderItem = $this->findOr404($configuration);

        $event = $this->eventDispatcher->dispatchPreEvent(CartActions::REMOVE, $configuration, $orderItem);

        if ($configuration->isCsrfProtectionEnabled() && !$this->isCsrfTokenValid((string) $orderItem->getId(), $request->request->get('_csrf_token'))) {
            throw new HttpException(Response::HTTP_FORBIDDEN, 'Invalid csrf token.');
        }

        if ($event->isStopped() && !$configuration->isHtmlRequest()) {
            throw new HttpException($event->getErrorCode(), $event->getMessage());
        }
        if ($event->isStopped()) {
            $this->flashHelper->addFlashFromEvent($configuration, $event);

            return $this->redirectHandler->redirectToIndex($configuration, $orderItem);
        }

        $cart = $this->getCurrentCart();
        if ($cart !== $orderItem->getOrder()) {
            $this->addFlash('error', $this->get('translator')->trans('sylius.cart.cannot_modify', [], 'flashes'));

            if (!$configuration->isHtmlRequest()) {
                return $this->viewHandler->handle($configuration, View::create(null, Response::HTTP_NO_CONTENT));
            }

            return $this->redirectHandler->redirectToIndex($configuration, $orderItem);
        }

        $this->getOrderModifier()->removeFromOrder($cart, $orderItem);

        $this->repository->remove($orderItem);

        $cartManager = $this->getCartManager();
        $cartManager->persist($cart);
        $cartManager->flush();

        $this->eventDispatcher->dispatchPostEvent(CartActions::REMOVE, $configuration, $orderItem);

        if (!$configuration->isHtmlRequest()) {
            return $this->viewHandler->handle($configuration, View::create(null, Response::HTTP_NO_CONTENT));
        }

        $this->flashHelper->addSuccessFlash($configuration, CartActions::REMOVE, $orderItem);

        return $this->redirectHandler->redirectToIndex($configuration, $orderItem);
    }

    protected function getOrderRepository(): OrderRepositoryInterface
    {
        return $this->get('sylius.repository.order');
    }

    protected function redirectToCartSummary(RequestConfiguration $configuration): Response
    {
        if (null === $configuration->getParameters()->get('redirect')) {
            return $this->redirectHandler->redirectToRoute($configuration, $this->getCartSummaryRoute());
        }

        return $this->redirectHandler->redirectToRoute($configuration, $configuration->getParameters()->get('redirect'));
    }

    protected function getCartSummaryRoute(): string
    {
        return 'sylius_cart_summary';
    }

    protected function getCurrentCart(): OrderInterface
    {
        return $this->getContext()->getCart();
    }

    protected function getContext(): CartContextInterface
    {
        return $this->get('sylius.context.cart');
    }

    protected function createAddToCartCommand(OrderInterface $cart, OrderItemInterface $cartItem): AddToCartCommandInterface
    {
        return $this->get('sylius.factory.add_to_cart_command')->createWithCartAndCartItem($cart, $cartItem);
    }

    protected function getFormFactory(): FormFactoryInterface
    {
        return $this->get('form.factory');
    }

    protected function getQuantityModifier(): OrderItemQuantityModifierInterface
    {
        return $this->get('sylius.order_item_quantity_modifier');
    }

    protected function getOrderModifier(): OrderModifierInterface
    {
        return $this->get('sylius.order_modifier');
    }

    protected function getCartManager(): EntityManagerInterface
    {
        return $this->get('sylius.manager.order');
    }

    protected function getCartItemErrors(OrderItemInterface $orderItem): ConstraintViolationListInterface
    {
        return $this
            ->get('validator')
            ->validate($orderItem, null, $this->getParameter('sylius.form.type.order_item.validation_groups'))
        ;
    }

    protected function getAddToCartFormWithErrors(ConstraintViolationListInterface $errors, FormInterface $form): FormInterface
    {
        foreach ($errors as $error) {
            $form->get('cartItem')->get($error->getPropertyPath())->addError(new FormError($error->getMessage()));
        }

        return $form;
    }

    protected function handleBadAjaxRequestView(RequestConfiguration $configuration, FormInterface $form): Response
    {
        return $this->viewHandler->handle(
            $configuration,
            View::create($form, Response::HTTP_BAD_REQUEST)->setData(['errors' => $form->getErrors(true, true)])
        );
    }

}
