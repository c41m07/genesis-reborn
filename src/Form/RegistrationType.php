<?php
namespace App\Form;
use App\Validator\DTO\RegistrationData;
use Symfony\Component\Form\{AbstractType,FormBuilderInterface};
use Symfony\Component\Form\Extension\Core\Type\{EmailType,TextType,PasswordType,RepeatedType};
use Symfony\Component\OptionsResolver\OptionsResolver;
final class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder,array $options): void
    {
        $builder->add('email',EmailType::class,['label'=>'Adresse email','attr'=>['autocomplete'=>'email']])
            ->add('username',TextType::class,['label'=>'Nom de commandant','attr'=>['autocomplete'=>'nickname']])
            ->add('plainPassword',RepeatedType::class,['type'=>PasswordType::class,'invalid_message'=>'Les mots de passe ne correspondent pas.','first_options'=>['label'=>'Mot de passe (12 caractères minimum)','attr'=>['autocomplete'=>'new-password']],'second_options'=>['label'=>'Confirmer le mot de passe','attr'=>['autocomplete'=>'new-password']]]);
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class'=>RegistrationData::class]); }
}
