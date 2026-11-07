<?php
namespace App\Service;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
final class ProcessHeartbeat
{
    public function __construct(#[Autowire('%kernel.project_dir%/var/run')] private string $dir) {}
    public function touch(string $name): void
    {
        if(!preg_match('/^[a-z]+$/D',$name)) throw new \InvalidArgumentException('Processus invalide.');
        if(!is_dir($this->dir)) mkdir($this->dir,0777,true);
        file_put_contents($this->dir.'/'.$name.'.heartbeat',(string)time());
        chmod($this->dir.'/'.$name.'.heartbeat',0666);
    }
}
