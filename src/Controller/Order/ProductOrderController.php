<?php

declare(strict_types=1);

namespace App\Controller\Order;

use App\Form\JobType;
use App\Entity\Order\Metier;
use FOS\RestBundle\View\View;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sylius\Bundle\OrderBundle\Controller\OrderController;


class ProductOrderController extends OrderController
{
    
    public function summaryAction(Request $request): Response
    {
        $metier = new Metier();
        $formjob = $this->createForm(JobType::class, $metier);

        $formjob->handleRequest($request);
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
                'metier' => $metier,
                'form' => $form->createView(),
                'formjob' => $formjob->createView(),
            ])
        ;
       if ($form->isSubmitted() && $form->isValid()) {
      
           //  $metier->setMetier($metier);
             $manager = $this->getDoctrine()->getManager();
             $manager->persist($metier);
             $manager->flush();

             $this->addFlash('success', 'Votre profession a bien été enregistrée!');
         
         return $this->viewHandler->handle($configuration, $view);
         
        
      }
        return $this->viewHandler->handle($configuration, $view);
    }
}
