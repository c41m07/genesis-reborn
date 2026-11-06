<?php
namespace App\Service\Operations;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class AlertNotifier
{
    public function __construct(
        private MailerInterface $mailer, private ClockInterface $clock,
        #[Autowire('%kernel.project_dir%/var/operations')] private string $directory,
        #[Autowire('%env(bool:OPS_ALERT_ENABLED)%')] private bool $enabled,
        #[Autowire('%env(OPS_ALERT_TO)%')] private string $recipient,
        #[Autowire('%env(MAILER_FROM)%')] private string $sender,
        #[Autowire('%env(DEFAULT_URI)%')] private string $baseUrl,
        #[Autowire('%env(int:OPS_ALERT_REMINDER_SECONDS)%')] private int $reminderSeconds=3600,
    ) { if ($reminderSeconds<1) throw new \InvalidArgumentException('Délai de rappel invalide.'); }

    public function run(array $conditions, bool $send): array
    {
        if (!$send || !$this->enabled || $this->recipient==='') return ['mode'=>'simulation','sent'=>0];
        if (!is_dir($this->directory) && !mkdir($this->directory,0700,true) && !is_dir($this->directory)) throw new \RuntimeException('État des alertes inaccessible.');
        $lock=fopen($this->directory.'/alerts.lock','c');
        if (!$lock) throw new \RuntimeException('Verrou des alertes inaccessible.');
        try {
            if (!flock($lock,LOCK_EX|LOCK_NB)) throw new \RuntimeException('Une surveillance est déjà en cours.');
            $file=$this->directory.'/alerts.json';
            $state=is_file($file)?json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR):[];
            if (!is_array($state)) throw new \RuntimeException('État des alertes invalide.');
            foreach ($state as $entry) {
                if (!is_array($entry) || !isset($entry['since'],$entry['active']) || !array_key_exists('last_sent',$entry) || !is_int($entry['since']) || !is_bool($entry['active']) || ($entry['last_sent']!==null && !is_int($entry['last_sent']))) throw new \RuntimeException('État des alertes invalide.');
            }
            $now=$this->clock->now()->getTimestamp();$sent=0;
            foreach ($conditions as $key=>$condition) {
                if ($condition['status']==='unknown') continue;
                $previous=$state[$key]??['since'=>$now,'active'=>false,'last_sent'=>null];
                if ($condition['status']==='ok') {
                    if ($previous['active']) {
                        $this->send('Rétablissement',$condition['message']);++$sent;
                    }
                    unset($state[$key]);
                } else {
                    $due=$now-$previous['since']>=($condition['grace']??0);
                    if ($due && (!$previous['active'] || $now-$previous['last_sent']>=$this->reminderSeconds)) {
                        $this->send($previous['active']?'Rappel d’alerte':'Alerte',$condition['message']);
                        $previous['active']=true;$previous['last_sent']=$now;++$sent;
                    }
                    $state[$key]=$previous;
                }
                // Enregistrer après chaque mail réussi pour préserver les autres déduplications si le suivant échoue.
                $this->save($file,$state);
            }
            return ['mode'=>'envoi','sent'=>$sent];
        } finally { flock($lock,LOCK_UN);fclose($lock); }
    }

    private function send(string $type,string $message): void
    {
        $this->mailer->send((new Email())->from($this->sender)->to($this->recipient)
            ->subject('[Genesis Reborn] '.$type.' d’exploitation')
            ->text($type.' — '.$this->baseUrl."\n".$message."\nRelevé UTC : ".$this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')."\nConsulter les journaux et le guide d’exploitation avant toute reprise."));
    }

    private function save(string $file,array $state): void
    {
        $temporary=tempnam($this->directory,'alerts-');
        if ($temporary===false) throw new \RuntimeException('Écriture des alertes impossible.');
        try {
            chmod($temporary,0600);
            if (file_put_contents($temporary,json_encode($state,JSON_THROW_ON_ERROR))===false || !rename($temporary,$file)) throw new \RuntimeException('Enregistrement des alertes impossible.');
        } finally { if (is_file($temporary)) unlink($temporary); }
    }
}
