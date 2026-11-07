<?php
namespace App\Form;

use App\Validator\DTO\PasswordResetData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\{PasswordType,RepeatedType};
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PasswordResetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder,array $options):void
    {
        $builder->add('plainPassword',RepeatedType::class,[
            'type'=>PasswordType::class,
            'invalid_message'=>'Les mots de passe ne correspondent pas.',
            'first_options'=>['label'=>'Nouveau mot de passe (12 caractères minimum)','attr'=>['autocomplete'=>'new-password']],
            'second_options'=>['label'=>'Confirmer le nouveau mot de passe','attr'=>['autocomplete'=>'new-password']],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefaults(['data_class'=>PasswordResetData::class]);
    }
}
