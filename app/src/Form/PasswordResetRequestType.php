<?php
namespace App\Form;

use App\Validator\DTO\PasswordResetRequestData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PasswordResetRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder,array $options):void
    {
        $builder->add('email',EmailType::class,['label'=>'Adresse email','attr'=>['autocomplete'=>'email']]);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefaults(['data_class'=>PasswordResetRequestData::class]);
    }
}
