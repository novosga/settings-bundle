<?php

/*
 * This file is part of the Novo SGA project.
 *
 * (c) Rogerio Lino <rogeriolino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Novosga\SettingsBundle\Form;

use Novosga\Entity\ServicoUnidade;
use Novosga\Entity\Local;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ServicoUnidadeType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('sigla', TextType::class, [
                'attr' => [
                    'maxlength' => 3
                ]
            ])
            ->add('local', EntityType::class, [
                'class' => Local::class
            ])
            ->add('status', CheckboxType::class, [
                'required' => false
            ])
            ->add('peso', IntegerType::class)
            ->add('numeroInicial', IntegerType::class)
            ->add('numeroFinal', IntegerType::class)
            ->add('incremento', IntegerType::class)
            ->add('prioridade', CheckboxType::class, [
                'required' => false
            ])
            ->add('mensagem', TextareaType::class)
        ;
    }
    
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => ServicoUnidade::class
        ));
    }
    
    public function getBlockPrefix()
    {
        return null;
    }

}
