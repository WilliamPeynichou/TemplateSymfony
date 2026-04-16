<?php

namespace App\Form;

use App\Entity\MatchNote;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class MatchNoteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('matchLabel', TextType::class, [
                'label' => 'Adversaire / Intitulé du match',
                'constraints' => [new NotBlank()],
            ])
            ->add('matchDate', DateType::class, [
                'label'  => 'Date du match',
                'widget' => 'single_text',
                'input'  => 'datetime_immutable',
            ])
            ->add('content', TextareaType::class, [
                'label'       => 'Observations post-match',
                'attr'        => ['rows' => 8, 'placeholder' => 'Décrivez vos observations : performance collective, joueurs en forme ou en difficulté, axes tactiques à retravailler...'],
                'constraints' => [new NotBlank()],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => MatchNote::class]);
    }
}
