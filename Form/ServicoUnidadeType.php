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

use Doctrine\ORM\EntityRepository;
use Novosga\Entity\Departamento;
use Novosga\Entity\ServicoUnidade;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Novosga\SettingsBundle\Controller\DefaultController;

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
                    'label.attendance_type_all'      => ServicoUnidade::ATENDIMENTO_TODOS,
                    'label.attendance_type_normal'   => ServicoUnidade::ATENDIMENTO_NORMAL,
                    'label.attendance_type_priority' => ServicoUnidade::ATENDIMENTO_PRIORIDADE,
                ],
                'choice_translation_domain' => DefaultController::DOMAIN,
            ])
            ->add('mensagem', TextareaType::class, [
                'required' => false
            ])
            ->add('departamento', EntityType::class, [
                'label'         => 'label.department',
                'class'         => Departamento::class,
                'placeholder'   => 'Nenhum',
                'required'      => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er
                        ->createQueryBuilder('e')
                        ->orderBy('e.nome', 'ASC');
                }
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => ServicoUnidade::class,
        ));
    }

    public function getBlockPrefix()
    {
        return '';
    }
}
