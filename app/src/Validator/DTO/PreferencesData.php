<?php
namespace App\Validator\DTO;
final class PreferencesData
{
    public string $timezone = 'Europe/Paris';
    public string $transportReception = 'allies';
    public string $theme = 'dark';
    public float $galaxySpySpeed = 1.0;
    public float $galaxyColonizeSpeed = 1.0;
    public bool $reducedMotion = false;
    public bool $realtimeNotifications = true;
    public bool $showLockedItems = false;
}
