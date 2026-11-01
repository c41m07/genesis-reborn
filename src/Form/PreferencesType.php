<?php
namespace App\Form;
use App\Validator\DTO\PreferencesData;
use Symfony\Component\Form\{AbstractType,FormBuilderInterface};
use Symfony\Component\Form\Extension\Core\Type\{CheckboxType,ChoiceType};
use Symfony\Component\OptionsResolver\OptionsResolver;
final class PreferencesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b,array $options):void
    {
        $zones=\DateTimeZone::listIdentifiers();$choices=[];foreach($zones as $zone)$choices[$zone]=$zone;
        $b->add('theme',ChoiceType::class,['label'=>'Thème de l’interface','choices'=>['Sombre — recommandé'=>'dark','Clair'=>'light'],'empty_data'=>'dark'])
          ->add('timezone',ChoiceType::class,['label'=>'Fuseau horaire de l’horloge','choices'=>$choices,'attr'=>['autocomplete'=>'off']])
          ->add('reducedMotion',CheckboxType::class,['label'=>'Réduire les animations de l’interface','required'=>false])
          ->add('realtimeNotifications',CheckboxType::class,['label'=>'Afficher les alertes de jeu en temps réel','required'=>false]);
    }
    public function configureOptions(OptionsResolver $r):void{$r->setDefaults(['data_class'=>PreferencesData::class]);}
}
