<?php
namespace App\Validator\DTO;
use Symfony\Component\Validator\Constraints as Assert;
final class ProfileData
{
    #[Assert\NotBlank,Assert\Email,Assert\Length(max:180)] public string $email='';
    #[Assert\NotBlank,Assert\Regex(pattern:'/^[\p{L}\p{N}_ -]{3,40}$/u',message:'Pseudo : 3 à 40 lettres, chiffres, espaces ou tirets.')] public string $username='';
    #[Assert\NotBlank(message:'Votre mot de passe actuel est nécessaire.')] public string $currentPassword='';
    #[Assert\Length(min:12,max:128,minMessage:'Utilisez au moins 12 caractères.')] public ?string $newPassword=null;
}
