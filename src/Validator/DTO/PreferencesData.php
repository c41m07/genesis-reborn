<?php
namespace App\Validator\DTO;
final class PreferencesData
{
    public string $timezone = 'Europe/Paris';
    public string $theme = 'dark';
    public bool $reducedMotion = false;
    public bool $realtimeNotifications = true;
}
