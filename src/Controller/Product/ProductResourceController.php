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

namespace App\Controller\Product;


use App\Form\JobType;
use App\Entity\Order\Order;
use App\Entity\Order\Metier;
use FOS\RestBundle\View\View;
use App\Entity\Product\Product;
use App\Provider\ProductProvider;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\Request;
use Sylius\Component\Resource\ResourceActions;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Sylius\Bundle\ResourceBundle\Controller\SingleResourceProviderInterface;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController as BaseResourceController;

class ProductResourceController extends BaseResourceController 
{

   
    public function showAction(Request $request): Response
    {

        //$order = new Order();
        $metier = new Metier();
        $form = $this->createForm(JobType::class, $metier, [
            'method' => 'GET'
        ]);
        $form->handleRequest($request);
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);

        $this->isGrantedOr403($configuration, ResourceActions::SHOW);
        $product = $this->findOr404($configuration);

        // COMPRENDRE POURQUOI L'APPEL DU SERVICE NE MARCHE PAS
        //$recommendationService = $this->get('app.provider.product');

        //$recommendedProducts = $recommendationService->getRecommendedProducts($product);

        $this->eventDispatcher->dispatch(ResourceActions::SHOW, $configuration, $product);

        $view = View::create($product);

                  
            /* Test choix du metier */

           
        
            dump($metier);

           

                if ($configuration->isHtmlRequest()) {
                    $view
                        ->setTemplate($configuration->getTemplate(ResourceActions::SHOW . '.html'))
                        ->setTemplateVar($this->metadata->getName())
                        ->setData([
                            'configuration' => $configuration,
                            'metadata' => $this->metadata,
                            'resource' => $product,
                            'metier' => $metier,
                            //'order' => $metier,
                            //'recommendedProducts' => $recommendedProducts,
                            'form' => $form->createView(),
                            $this->metadata->getName() => $product,
                        ]);
                        
                             if ($form->isSubmitted() && $form->isValid()) {
                               //  $order->getMetier();
                                $manager = $this->getDoctrine()->getManager();
                                $manager->persist($metier);
                                $manager->flush();
    
                                $this->addFlash('success', 'Votre profession a bien été enregistrée!');
                            
                            return $this->viewHandler->handle($configuration, $view);
                            
                            }
                            
                    return $this->viewHandler->handle($configuration, $view);
  
                }
        
        }

        public function createAction(Request $request): Response
    {
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);

        $this->isGrantedOr403($configuration, ResourceActions::CREATE);
        $newResource = $this->newResourceFactory->create($configuration, $this->factory);

        $form = $this->resourceFormFactory->create($configuration, $newResource);

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $newResource = $form->getData();

            $event = $this->eventDispatcher->dispatchPreEvent(ResourceActions::CREATE, $configuration, $newResource);

            if ($event->isStopped() && !$configuration->isHtmlRequest()) {
                throw new HttpException($event->getErrorCode(), $event->getMessage());
            }
            if ($event->isStopped()) {
                $this->flashHelper->addFlashFromEvent($configuration, $event);

                $eventResponse = $event->getResponse();
                if (null !== $eventResponse) {
                    return $eventResponse;
                }

                return $this->redirectHandler->redirectToIndex($configuration, $newResource);
            }

            if ($configuration->hasStateMachine()) {
                $this->stateMachine->apply($configuration, $newResource);
            }

            $this->repository->add($newResource);

            if ($configuration->isHtmlRequest()) {
                $this->flashHelper->addSuccessFlash($configuration, ResourceActions::CREATE, $newResource);
            }

            $postEvent = $this->eventDispatcher->dispatchPostEvent(ResourceActions::CREATE, $configuration, $newResource);

            if (!$configuration->isHtmlRequest()) {
                return $this->viewHandler->handle($configuration, View::create($newResource, Response::HTTP_CREATED));
            }

            $postEventResponse = $postEvent->getResponse();
            if (null !== $postEventResponse) {
                return $postEventResponse;
            }

            return $this->redirectHandler->redirectToResource($configuration, $newResource);
        }

        if (!$configuration->isHtmlRequest()) {
            return $this->viewHandler->handle($configuration, View::create($form, Response::HTTP_BAD_REQUEST));
        }

        $initializeEvent = $this->eventDispatcher->dispatchInitializeEvent(ResourceActions::CREATE, $configuration, $newResource);
        $initializeEventResponse = $initializeEvent->getResponse();
        if (null !== $initializeEventResponse) {
            return $initializeEventResponse;
        }

        $view = View::create()
            ->setData([
                'configuration' => $configuration,
                'metadata' => $this->metadata,
                'resource' => $newResource,
                $this->metadata->getName() => $newResource,
                'form' => $form->createView(),
            ])
            ->setTemplate($configuration->getTemplate(ResourceActions::CREATE . '.html'))
        ;

        return $this->viewHandler->handle($configuration, $view);
    }

}    
