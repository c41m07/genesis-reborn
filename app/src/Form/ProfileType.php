<?php
namespace App\Form;
use App\Validator\DTO\ProfileData;
use Symfony\Component\Form\{AbstractType,FormBuilderInterface};
use Symfony\Component\Form\Extension\Core\Type\{EmailType,TextType,PasswordType,RepeatedType};
use Symfony\Component\OptionsResolver\OptionsResolver;
final class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b,array $options):void
    {
        $b->add('email',EmailType::class,['label'=>'Adresse email','attr'=>['autocomplete'=>'email']])
            ->add('username',TextType::class,['label'=>'Nom de commandant','attr'=>['autocomplete'=>'nickname']])
            ->add('currentPassword',PasswordType::class,['label'=>'Mot de passe actuel','attr'=>['autocomplete'=>'current-password']])
            ->add('newPassword',RepeatedType::class,['type'=>PasswordType::class,'required'=>false,'invalid_message'=>'Les mots de passe ne correspondent pas.','first_options'=>['label'=>'Nouveau mot de passe (facultatif)','attr'=>['autocomplete'=>'new-password']],'second_options'=>['label'=>'Confirmer le nouveau mot de passe','attr'=>['autocomplete'=>'new-password']]]);
    }
    public function configureOptions(OptionsResolver $r):void { $r->setDefaults(['data_class'=>ProfileData::class]); }
}
