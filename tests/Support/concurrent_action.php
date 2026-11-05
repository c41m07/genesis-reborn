<?php
require dirname(__DIR__,2).'/tests/bootstrap.php';
$kernel=new App\Kernel('test',true);$kernel->boot();$container=$kernel->getContainer()->get('test.service_container');
$container->set('clock',new Symfony\Component\Clock\MockClock($argv[4]));
$em=$container->get(Doctrine\ORM\EntityManagerInterface::class);$params=$em->getConnection()->getParams();
if(($params['host']??'')!=='database-test'||($params['dbname']??'')!=='genesis_v2_test') exit(99);
try {
    $m=$em->find(App\Entity\PlayerUniverse::class,(int)$argv[1]);$planet=$em->find(App\Entity\Planet::class,(int)$argv[2]);
    $container->get(App\Service\Queue\QueueService::class)->add($m,$planet,'buildings','mine_metal',1,$argv[3]);
    exit(0);
} catch(DomainException $e) { exit(10); }
