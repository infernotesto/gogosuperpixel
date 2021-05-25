<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CategoryLiteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', null, ['required' => true, 'label' => 'Nom du groupe']) // TODO translation
            ->add('index', null, ['required' => false, 'label' => 'Position']) // TODO translation
            ->add('pickingOptionText', null, ['required' => true, 'label' => "Text à afficher dans le formulaire : Choisissez..."]) // TODO translation
            ->add('id', null, ['required' => false, 'label' => 'Plus de paramètres', 'attr' => ['class' => 'gogo-route-id', 'data-route-id' => 'admin_app_category_edit']]) // TODO translation
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
          'data_class' => 'App\Document\Category',
      ]);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'gogo_form_category_lite';
    }
}
