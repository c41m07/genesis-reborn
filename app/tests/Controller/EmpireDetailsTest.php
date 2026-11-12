<?php
namespace App\Tests\Controller;

use App\Entity\{Fleet,FleetShip,Planet,PlanetResource,Universe};
use App\Service\Colony\EmpireDetails;
use App\Service\Player\RegistrationService;
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EmpireDetailsTest extends WebTestCase
{
    public function testDetailsArePrivateAndDoNotMaterializeStocks(): void
    {
        $client=static::createClient([],['HTTP_HOST'=>'genesis-reborn.localhost']);$client->disableReboot();
        $em=static::getContainer()->get(EntityManagerInterface::class);
        $params=$em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test' || ($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $schema=new SchemaTool($em);$metadata=$em->getMetadataFactory()->getAllMetadata();$schema->dropSchema($metadata);$schema->createSchema($metadata);
        static::getContainer()->get(UniverseImporter::class)->import();
        $members=[];
        foreach(['DetailsOwner','DetailsOther'] as $name) {
            $data=new RegistrationData();$data->email=strtolower($name).'@example.test';$data->username=$name;$data->plainPassword='A-test-password-2026';
            $player=static::getContainer()->get(RegistrationService::class)->register($data);
            $universe=$em->getRepository(Universe::class)->findOneBy(['slug'=>'omega-1']);
            static::getContainer()->get(UniverseService::class)->select($player,$universe->id);
            $members[]=$player->currentPlayerUniverse;
        }
        [$owner,$other]=$members;
        $home=$em->getRepository(Planet::class)->findOneBy(['owner'=>$owner]);
        $away=$em->getRepository(Planet::class)->findOneBy(['owner'=>$other]);
        $own=new Fleet();$own->playerUniverse=$owner;$own->homePlanet=$home;$own->status='traveling';$own->destination=$away;$own->mission='transport';$own->cargo=['metal'=>321,'hydrogen'=>77,'nano'=>0];
        $foreign=new Fleet();$foreign->playerUniverse=$other;$foreign->homePlanet=$away;$foreign->status='traveling';$foreign->cargo=['nano'=>9999];
        $em->persist($own);$em->persist($foreign);
        foreach([[$own,'fighter','basic',4],[$own,'fighter','intermediate',2],[$own,'transport','basic',0],[$foreign,'bomber','basic',9]] as [$fleet,$slug,$tier,$quantity]) {
            $ship=new FleetShip();$ship->fleet=$fleet;$ship->slug=$slug;$ship->tier=$tier;$ship->quantity=$quantity;$em->persist($ship);
        }
        $em->flush();
        $before=$em->getConnection()->fetchAllAssociative('SELECT id, current_stock, last_updated_at FROM planet_resource WHERE planet_id = ? ORDER BY id',[$home->id]);
        $details=static::getContainer()->get(EmpireDetails::class);
        $planets=$details->planets($owner);
        self::assertSame([$home->id],array_keys($planets));
        self::assertArrayHasKey('metal',$planets[$home->id]['resources']);
        $fleets=$details->fleets($owner,[$own,$foreign]);
        self::assertSame([$own->id],array_keys($fleets));
        self::assertCount(2,$fleets[$own->id]['ships']);
        self::assertSame([4,2],array_column($fleets[$own->id]['ships'],'quantity'));
        self::assertSame(['Basique','Intermédiaire'],array_column($fleets[$own->id]['ships'],'tier'));
        self::assertSame(321.0,$fleets[$own->id]['resources']['metal']['stock']);
        self::assertSame(77.0,$fleets[$own->id]['resources']['hydrogen']['stock']);
        self::assertArrayNotHasKey('nano',$fleets[$own->id]['resources']);
        $em->flush();
        self::assertSame($before,$em->getConnection()->fetchAllAssociative('SELECT id, current_stock, last_updated_at FROM planet_resource WHERE planet_id = ? ORDER BY id',[$home->id]));
        $client->loginUser($owner->player);
        $client->request('GET','/planetes');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.empire-details summary');
        self::assertSelectorTextNotContains('.empire-details','9999');
        $client->request('GET','/planetes/'.$away->id);
        self::assertResponseStatusCodeSame(404);
    }
}
