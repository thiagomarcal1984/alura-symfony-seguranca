<?php

namespace App\Form;

use App\DTO\SeriesCreateFromInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SeriesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(child: 'seriesName', options: [ 'label' => 'Nome:' ])
            ->add('seasonsQuantity', NumberType::class, options: [ 'label' => 'Qtd Temporadas:' ])
            ->add(child: 'episodesPerSeason', type: NumberType::class, options: [ 'label' => 'Ep por Temporada:' ])
            // O texto do botão submit varia em função do tipo de formulário: edição ou criação.
            ->add('save', SubmitType::class, [ 'label' => $options['is_edit'] ? 'Editar' : 'Adicionar' ])
            ->setMethod($options['is_edit'] ? 'PATCH' : 'POST')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SeriesCreateFromInput::class,
            'is_edit' => false, // O valor padrão para a opção 'is_edit' no formulário é false.
        ]);

        $resolver->setAllowedTypes('is_edit', 'bool'); // O valor para 'is_edit' deve ser booleano.
    }
}
