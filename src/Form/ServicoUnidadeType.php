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

use Novosga\Entity\DepartamentoInterface;
use Novosga\Entity\ServicoUnidadeInterface;
use Novosga\Repository\DepartamentoRepositoryInterface;
use Novosga\SettingsBundle\NovosgaSettingsBundle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ServicoUnidadeType extends AbstractType
{
    public function __construct(
        private readonly DepartamentoRepositoryInterface $departamentoRepository,
    ) {
    }

    /** {@inheritdoc} */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('sigla', TextType::class, [
                'attr' => [
                    'maxlength' => 3
                ]
            ])
            ->add('ativo', CheckboxType::class, [
                'required' => false
            ])
            ->add('peso', IntegerType::class)
            ->add('numeroInicial', IntegerType::class)
            ->add('numeroFinal', IntegerType::class, [
                'required' => false
            ])
            ->add('maximo', IntegerType::class, [
                'required' => false
            ])
            ->add('incremento', IntegerType::class)
            ->add('tipo', ChoiceType::class, [
                'required' => true,
                'choices'  => [
                    'label.attendance_type_all' => ServicoUnidadeInterface::ATENDIMENTO_TODOS,
                    'label.attendance_type_normal' => ServicoUnidadeInterface::ATENDIMENTO_NORMAL,
                    'label.attendance_type_priority' => ServicoUnidadeInterface::ATENDIMENTO_PRIORIDADE,
                ],
                'choice_translation_domain' => NovosgaSettingsBundle::getDomain(),
            ])
            ->add('mensagem', TextareaType::class, [
                'required' => false
            ])
            ->add('departamento', ChoiceType::class, [
                'label' => 'label.department',
                'placeholder' => 'Nenhum',
                'required' => false,
                'choice_value' => fn (?DepartamentoInterface $value) => $value?->getId(),
                'choice_label' => fn (?DepartamentoInterface $value) => $value?->getNome(),
                'choices' => $this->departamentoRepository->findAll(),
            ])
        ;
    }

    /** {@inheritdoc} */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ServicoUnidadeInterface::class,
            'csrf_protection' => false,
        ]);
    }

    public function getBlockPrefix()
    {
        return '';
    }
}
