<?php

namespace App\Form;

use App\Entity\Team;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class TeamType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de l\'équipe',
                'attr'  => ['placeholder' => 'Ex : U17 A'],
                'constraints' => [new NotBlank()],
            ])
            ->add('club', TextType::class, [
                'label'    => 'Club',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex : AS Saint-Étienne'],
            ])
            ->add('season', TextType::class, [
                'label'    => 'Saison',
                'required' => false,
                'attr'     => ['placeholder' => '2025-2026'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Team::class]);
    }
}
