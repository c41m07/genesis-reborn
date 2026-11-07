<?php
namespace App\Validator\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class PasswordResetRequestData
{
    #[Assert\NotBlank(message:'Indiquez votre adresse email.')]
    #[Assert\Email(message:'Adresse email invalide.')]
    #[Assert\Length(max:180)]
    public string $email = '';
}
