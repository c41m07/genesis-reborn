<?php

namespace App\Tests\Integration;

use App\Entity\AdminAuditLog;
use App\Repository\AdminReadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AdminReadRepositoryTest extends KernelTestCase
{
    public function testBoundedPreviewsKeepTheFullIncidentCountAndReviewHistory(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée.');
        }
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $now = new \DateTimeImmutable('2026-11-14T12:00:00Z');
        $incidents = [];
        for ($index = 0; $index < 75; ++$index) {
            $incident = new AdminAuditLog();
            $incident->action = 'admin_access_denied';
            $incident->targetType = 'route';
            $incident->targetId = 0;
            $incident->createdAt = $now->modify('-'.$index.' minutes');
            $em->persist($incident);
            $incidents[] = $incident;
        }
        $em->flush();
        $review = new AdminAuditLog();
        $review->action = 'admin_access_reviewed';
        $review->targetType = 'admin_access_incident';
        $review->targetId = $incidents[0]->id;
        $review->createdAt = $now;
        $em->persist($review);
        $em->flush();

        $reads = self::getContainer()->get(AdminReadRepository::class);
        self::assertSame(74, $reads->securityIncidentCount());
        self::assertCount(50, $reads->securityIncidents());
        self::assertNotContains($incidents[0], $reads->securityIncidents());
        self::assertSame($incidents[74]->id, $reads->latestSecurityIncidentId());
        self::assertCount(5, $reads->recentActions());
        self::assertSame(76, $em->getRepository(AdminAuditLog::class)->count([]));
        self::assertSame(['playerCount' => 0, 'suspendedPlayerCount' => 0, 'openReportCount' => 0], $reads->counters());
        $archiveValues = [null, false, true, 0, 1, 0.0, 0.001, '', '0', 'null', 'false', [], [1], ['marker'=>1]];
        $expectedArchived = 0;
        foreach ($archiveValues as $index=>$value) {
            $event = new \App\Entity\GameEvent();
            $event->name = 'Archive synthétique '.$index;
            $event->type = 'announcement';
            $event->startsAt = $event->endsAt = $now;
            $event->parameters = ['archived_at'=>$value];
            $expectedArchived += !empty($value) ? 1 : 0;
            $em->persist($event);
        }
        $em->flush();
        self::assertSame($expectedArchived, $reads->gameEvents(true, 1)['total']);
        self::assertSame(count($archiveValues) - $expectedArchived, $reads->gameEvents(false, 1)['total']);
    }
}
