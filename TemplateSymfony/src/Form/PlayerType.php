<?php

namespace App\Form;

use App\Entity\Player;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class PlayerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'attr'  => ['placeholder' => 'Kylian'],
                'constraints' => [new NotBlank()],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'attr'  => ['placeholder' => 'Mbappé'],
                'constraints' => [new NotBlank()],
            ])
            ->add('number', IntegerType::class, [
                'label' => 'Numéro de maillot',
                'attr'  => ['placeholder' => '10', 'min' => 1, 'max' => 99],
                'constraints' => [new NotBlank(), new Range(['min' => 1, 'max' => 99])],
            ])
            ->add('position', ChoiceType::class, [
                'label'   => 'Poste',
                'choices' => Player::POSITIONS,
                'constraints' => [new NotBlank()],
            ])
            ->add('strongFoot', ChoiceType::class, [
                'label'    => 'Pied fort',
                'required' => false,
                'choices'  => Player::STRONG_FEET,
                'placeholder' => 'Non renseigné',
            ])
            ->add('dateOfBirth', DateType::class, [
                'label'    => 'Date de naissance',
                'required' => false,
                'widget'   => 'single_text',
            ])
            ->add('height', IntegerType::class, [
                'label'    => 'Taille (cm)',
                'required' => false,
                'attr'     => ['placeholder' => '180'],
            ])
            ->add('weight', IntegerType::class, [
                'label'    => 'Poids (kg)',
                'required' => false,
                'attr'     => ['placeholder' => '75'],
            ])
            ->add('photoFile', FileType::class, [
                'label'    => 'Photo (optionnel)',
                'mapped'   => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize'          => '2M',
                        'mimeTypes'        => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Formats acceptés : JPG, PNG, WebP',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Player::class]);
    }
}
