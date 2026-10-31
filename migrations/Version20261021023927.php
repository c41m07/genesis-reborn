<?php

declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Yaml\Yaml;

final class Version20261021023927 extends AbstractMigration
{
    public function getDescription(): string { return 'Mémoriser les coûts payés ; convertir les anciens extracteurs d’énergie en centrales sans perdre leurs niveaux.'; }
    public function up(Schema $schema): void
    {
        $this->abortIf((int)$this->connection->fetchOne("SELECT COUNT(*) FROM queue_item WHERE item_slug='mine_energy'")>0,'Terminer ou annuler les anciennes commandes mine_energy avant la conversion.');
        $this->addSql('ALTER TABLE queue_item ADD paid_costs JSON DEFAULT NULL');
        $root=dirname(__DIR__).'/config/game';
        // Freeze the costs of operations already paid before enabling refunds.
        foreach($this->connection->fetchAllAssociative('SELECT * FROM queue_item') as $q) {
            $group=match($q['queue_type']){'building'=>'buildings','research'=>'technologies',default=>is_file($root.'/defenses/'.$q['item_slug'].'.yaml')?'defenses':'ships'};
            $d=Yaml::parseFile($root.'/'.$group.'/'.$q['item_slug'].'.yaml');$cost=[];
            foreach($d['costs_base']??$d['costs']??[] as $resource=>$base) $cost[$resource]=(int)ceil($base*($d['costs_scaling']??1)**((int)($q['item_level']??1)-1)*(int)$q['quantity']);
            $this->addSql('UPDATE queue_item SET paid_costs=? WHERE id=?',[json_encode($cost,JSON_THROW_ON_ERROR),(int)$q['id']]);
        }
        $rules=Yaml::parseFile($root.'/rules.yaml');$plant=Yaml::parseFile($root.'/buildings/power_plant.yaml');$now=new \DateTimeImmutable('now',new \DateTimeZone('UTC'));
        $rows=$this->connection->fetchAllAssociative("SELECT old.id,old.planet_id,old.level,p.position,u.slug AS universe_slug,central.id AS central_id,central.level AS central_level,storage.level AS storage_level,r.id AS resource_id,r.current_stock,r.production_rate,r.last_updated_at FROM planet_building old JOIN planet p ON p.id=old.planet_id JOIN solar_system s ON s.id=p.solar_system_id JOIN galaxy g ON g.id=s.galaxy_id JOIN universe u ON u.id=g.universe_id LEFT JOIN planet_building central ON central.planet_id=p.id AND central.slug='power_plant' LEFT JOIN planet_building storage ON storage.planet_id=p.id AND storage.slug='storage_energy' LEFT JOIN planet_resource r ON r.planet_id=p.id AND r.resource_type='energy' WHERE old.slug='mine_energy'");
        foreach($rows as $r) {
            $level=(int)$r['level']+(int)($r['central_level']??0);
            if($r['resource_id']) {
                $cap=$rules['base_storage_capacity']+(int)($r['storage_level']??0)*$rules['storage_capacity_per_level'];
                $last=new \DateTimeImmutable($r['last_updated_at'],new \DateTimeZone('UTC'));$effective=max($last,$now);
                $stock=min($cap,(float)$r['current_stock']+(float)$r['production_rate']*($effective->getTimestamp()-$last->getTimestamp()));
                $universe=Yaml::parseFile($root.'/universes/'.$r['universe_slug'].'.yaml');
                $rate=($rules['base_production']['energy']+$plant['production_per_level']*$level)*(1.5-(int)$r['position']/10)*$universe['multipliers']['resource_production'];
                $this->addSql('UPDATE planet_resource SET current_stock=?,production_rate=?,last_updated_at=? WHERE id=?',[$stock,$rate,$effective->format('Y-m-d H:i:s'),(int)$r['resource_id']]);
            }
            if($r['central_id']) {
                $this->addSql('UPDATE planet_building SET level=? WHERE id=?',[$level,(int)$r['central_id']]);
                $this->addSql('DELETE FROM planet_building WHERE id=?',[(int)$r['id']]);
            } else $this->addSql("UPDATE planet_building SET slug='power_plant' WHERE id=?",[(int)$r['id']]);
        }
    }
    public function down(Schema $schema): void { $this->throwIrreversibleMigrationException('La conversion conserve les niveaux cumulés ; leur répartition historique ne peut pas être reconstruite.'); }
}
