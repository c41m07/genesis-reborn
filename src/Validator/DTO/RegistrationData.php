<?php
namespace App\Validator\DTO;
use Symfony\Component\Validator\Constraints as Assert;
final class RegistrationData
{
    #[Assert\NotBlank(message:'Indiquez votre adresse email.'), Assert\Email(message:'Adresse email invalide.'), Assert\Length(max:180)]
    public string $email='';
    #[Assert\NotBlank, Assert\Regex(pattern:'/^[\p{L}\p{N}_ -]{3,40}$/u',message:'Pseudo : 3 à 40 lettres, chiffres, espaces ou tirets.')]
    public string $username='';
    #[Assert\NotBlank, Assert\Length(min:12,max:128,minMessage:'Utilisez au moins 12 caractères.')]
    public string $plainPassword='';
}
