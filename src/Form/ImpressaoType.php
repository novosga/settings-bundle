<?php

declare(strict_types=1);

/*
 * This file is part of the Novo SGA project.
 *
 * (c) Rogerio Lino <rogeriolino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Novosga\SettingsBundle\Form;

use Novosga\Entity\ConfiguracaoImpressaoInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ImpressaoType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('cabecalho', TextareaType::class, [
                'attr' => [
                    'rows' => 4
                ]
            ])
            ->add('rodape', TextareaType::class, [
                'attr' => [
                    'rows' => 4
                ]
            ])
            ->add('exibirData', CheckboxType::class, [
                'required' => false
            ])
            ->add('exibirPrioridade', CheckboxType::class, [
                'required' => false
            ])
            ->add('exibirNomeUnidade', CheckboxType::class, [
                'required' => false
            ])
            ->add('exibirNomeServico', CheckboxType::class, [
                'required' => false
            ])
            ->add('exibirMensagemServico', CheckboxType::class, [
                'required' => false
            ])
        ;
    }

    /** {@inheritdoc} */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefaults([
                'data_class' => ConfiguracaoImpressaoInterface::class,
                'csrf_protection' => false,
            ]);
    }

    public function getBlockPrefix()
    {
        return '';
    }
}
