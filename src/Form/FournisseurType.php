<?php

namespace App\Form;

use App\Entity\Fournisseur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Type;

class FournisseurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom',TextType::class, array(
                'label' => 'Nom fournisseur',
                'attr' => array(
                    'class' => 'form-control form-group'),
                'required' => true,
            ))

            ->add('telephone',TextType::class, array(
                'label' => 'Telephone',
                'attr' => array(
                    'class' => 'form-control form-group'),
                'required' => false,
                'constraints' => array(
                    new Type('numeric'),
                )
            ))

            ->add('Ajouter', SubmitType::class, array(
                'attr' =>array('class' => 'btn btn-primary form-group')
            ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Fournisseur::class,
        ]);
    }
}