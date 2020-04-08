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

namespace App\Form\Extension;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Sylius\Bundle\OrderBundle\Form\Type\CartItemType;
use Symfony\Component\Form\Extension\Core\Type\TextType;


class CartItemTypeExtension extends AbstractTypeExtension
{
    
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('metier', TextType::class, [
                'required' => true,
                'label' => 'Votre profession',
                ]);
    }

    

    public static function getExtendedTypes(): iterable
    {
        return [CartItemType::class];
    }
}