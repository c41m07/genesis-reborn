<?php
namespace App\Validator\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class PasswordResetData
{
    #[Assert\NotBlank(message:'Indiquez un nouveau mot de passe.')]
    #[Assert\Length(min:12, max:128, minMessage:'Utilisez au moins 12 caractères.')]
    public string $plainPassword = '';
}
