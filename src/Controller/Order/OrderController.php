<?php

declare(strict_types=1);

namespace App\Controller\Order;

use App\Entity\Order\Order;
use Webmozart\Assert\Assert;
use FOS\RestBundle\View\View;
use App\Entity\Order\OrderItem;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\Order\OrderResourceController;
use Sylius\Component\Order\SyliusCartEvents;
use Symfony\Component\HttpFoundation\Request;
use Sylius\Component\Resource\ResourceActions;
use Symfony\Component\HttpFoundation\Response;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;

class OrderController extends OrderResourceController
{
    
    public function summaryAction(Request $request, EntityManagerInterface $em): Response
    {
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);
        $cart = $this->getCurrentCart();
        // je vais récuperer le metier enregistré  par le client afin de pouvoir l'afficher.

        // je commence par récuperer  l'order en cours
            $order =$em->getRepository(Order::class)->find($cart);

        // je récupere l'id' de l'order en cours
            $orderId = $order->getId();

        // je récupere le metier de l'order en cours
            $metier = $em->getRepository(OrderItem::class)->findBy(['order'=>$orderId]);

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
              'metier' => $metier,
                'form' => $form->createView(),
            ])
        ;
        
        return $this->viewHandler->handle($configuration, $view);
    }

    public function widgetAction(Request $request): Response
    {
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);

        $cart = $this->getCurrentCart();

        if (!$configuration->isHtmlRequest()) {
            return $this->viewHandler->handle($configuration, View::create($cart));
        }

        $view = View::create()
            ->setTemplate($configuration->getTemplate('summary.html'))
            ->setData(['cart' => $cart])
        ;

        return $this->viewHandler->handle($configuration, $view);
    }

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

    public function clearAction(Request $request): Response
    {
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);

        $this->isGrantedOr403($configuration, ResourceActions::DELETE);
        $resource = $this->getCurrentCart();

        if ($configuration->isCsrfProtectionEnabled() && !$this->isCsrfTokenValid((string) $resource->getId(), $request->get('_csrf_token'))) {
            throw new HttpException(Response::HTTP_FORBIDDEN, 'Invalid csrf token.');
        }

        $event = $this->eventDispatcher->dispatchPreEvent(ResourceActions::DELETE, $configuration, $resource);

        if ($event->isStopped() && !$configuration->isHtmlRequest()) {
            throw new HttpException($event->getErrorCode(), $event->getMessage());
        }
        if ($event->isStopped()) {
            $this->flashHelper->addFlashFromEvent($configuration, $event);

            return $this->redirectHandler->redirectToIndex($configuration, $resource);
        }

        $this->repository->remove($resource);
        $this->eventDispatcher->dispatchPostEvent(ResourceActions::DELETE, $configuration, $resource);

        if (!$configuration->isHtmlRequest()) {
            return $this->viewHandler->handle($configuration, View::create(null, Response::HTTP_NO_CONTENT));
        }

        $this->flashHelper->addSuccessFlash($configuration, ResourceActions::DELETE, $resource);

        return $this->redirectHandler->redirectToIndex($configuration, $resource);
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

    protected function getOrderRepository(): OrderRepositoryInterface
    {
        return $this->get('sylius.repository.order');
    }

    protected function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->container->get('event_dispatcher');
    }

    public function thankYouAction(Request $request): Response
    {
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);

        $orderId = $request->getSession()->get('sylius_order_id', null);

        if (null === $orderId) {
            $options = $configuration->getParameters()->get('after_failure');

            return $this->redirectHandler->redirectToRoute(
                $configuration,
                $options['route'] ?? 'sylius_shop_homepage',
                $options['parameters'] ?? []
            );
        }

        $request->getSession()->remove('sylius_order_id');
        $order = $this->repository->find($orderId);
        Assert::notNull($order);

        $view = View::create()
            ->setData([
                'order' => $order,
            ])
            ->setTemplate($configuration->getParameters()->get('template'))
        ;

        return $this->viewHandler->handle($configuration, $view);
    }

}
