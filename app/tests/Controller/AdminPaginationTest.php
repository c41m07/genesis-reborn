<?php

namespace App\Tests\Controller;

use App\Entity\{AdminAuditLog, Player};
use App\Repository\AdminReadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminPaginationTest extends WebTestCase
{
    public function testFiltersPaginationFragmentsAndFullExportKeepTheCompleteHistory(): void
    {
        $client = self::createClient([], ['HTTP_HOST'=>'genesis-reborn.localhost']);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $admin = null;
        for ($index = 0; $index < 175; ++$index) {
            $player = new Player();
            $player->username = sprintf('Pagination-%03d', $index);
            $player->email = 'pagination-'.$index.'@example.test';
            $player->emailVerifiedAt = new \DateTimeImmutable();
            $player->password = 'unused';
            if ($index === 0) {
                $player->roles = ['ROLE_ADMIN'];
                $admin = $player;
            } elseif ($index % 2 === 0) {
                $player->lockedAt = new \DateTimeImmutable();
            }
            $em->persist($player);
            $entry = new AdminAuditLog();
            $entry->actor = $admin;
            $entry->action = $index % 2 === 0 ? 'test_even' : 'test_odd';
            $entry->targetType = $index === 0 ? '=sensitive-formula' : 'synthetic';
            $entry->targetId = $index;
            $entry->details = ['marker'=>sprintf('preuve-%03d', $index), 'literal'=>'100%_'];
            $entry->createdAt = new \DateTimeImmutable('2026-11-15T12:00:00Z');
            $em->persist($entry);
            $feedback = new \App\Entity\UserFeedback();
            $feedback->player = $player;
            $feedback->type = 'bug';
            $feedback->priority = 'medium';
            $feedback->title = sprintf('Retour-%03d', $index);
            $feedback->description = 'Description synthétique';
            $feedback->status = $index % 2 === 0 ? 'open' : 'archived';
            $feedback->createdAt = $entry->createdAt;
            $em->persist($feedback);
            $note = new \App\Entity\PatchNote();
            $note->title = sprintf('Note-%03d', $index);
            $note->version = 'test';
            $note->summary = 'Résumé synthétique';
            $note->content = 'Contenu synthétique';
            $note->createdAt = $note->updatedAt = $entry->createdAt;
            $em->persist($note);
            $event = new \App\Entity\GameEvent();
            $event->name = 'Événement synthétique '.$index;
            $event->type = 'announcement';
            $event->startsAt = $event->endsAt = $entry->createdAt;
            if ($index % 2 !== 0) $event->parameters['archived_at'] = '2026-11-15T12:00:00Z';
            $em->persist($event);
        }
        $em->flush();
        $reads = self::getContainer()->get(AdminReadRepository::class);
        $filters = ['q'=>'', 'status'=>'', 'role'=>'', 'action'=>''];
        $first = $reads->players($filters, 1);
        self::assertCount(50, $first['entries']);
        self::assertSame(175, $first['total']);
        self::assertSame(4, $reads->players($filters, PHP_INT_MAX)['page']);
        self::assertSame(1, $reads->players(array_replace($filters, ['q'=>'Pagination-000']), 100)['total']);
        self::assertSame(1, $reads->players(array_replace($filters, ['role'=>'admin']), 1)['total']);
        self::assertSame(87, $reads->players(array_replace($filters, ['status'=>'suspended', 'role'=>'player']), 1)['total']);
        self::assertSame(1, $reads->audit(array_replace($filters, ['q'=>'preuve-000']), 1)['total']);
        self::assertSame(175, $reads->audit(array_replace($filters, ['q'=>'100%_']), 1)['total']);
        self::assertSame(0, $reads->audit(array_replace($filters, ['q'=>'100%_inconnu']), 1)['total']);
        self::assertSame(88, $reads->audit(array_replace($filters, ['action'=>'test_even']), 1)['total']);
        self::assertCount(175, iterator_to_array($reads->auditExport($filters), false));
        self::assertSame(['test_even', 'test_odd'], $reads->auditActions());
        $feedbackFilters = ['q'=>'', 'type'=>'', 'priority'=>'', 'status'=>'', 'universe'=>''];
        self::assertSame(88, $reads->feedbacks($feedbackFilters, 1)['total']);
        $feedbackPage = $reads->feedbacks(array_replace($feedbackFilters, ['status'=>'all']), 4);
        self::assertSame(175, $feedbackPage['total']);
        self::assertSame(88, $feedbackPage['openCount']);
        self::assertSame($feedback->id, $feedbackPage['latest']);
        self::assertCount(25, $feedbackPage['entries']);
        self::assertSame(1, $reads->feedbacks(array_replace($feedbackFilters, ['q'=>'Retour-000']), 4)['total']);
        $notes = self::getContainer()->get(\App\Repository\PatchNoteRepository::class);
        self::assertCount(50, $notes->page('', '', 1)['patchNotes']);
        self::assertCount(25, $notes->page('', '', 4)['patchNotes']);
        self::assertSame(1, $notes->page('draft', 'Note-000', 4)['total']);
        self::assertSame(88, $reads->gameEvents(false, 1)['total']);
        self::assertSame(87, $reads->gameEvents(true, 1)['total']);
        self::assertCount(37, $reads->gameEvents(true, 2)['events']);

        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/joueurs?page=4');
        self::assertResponseIsSuccessful();
        self::assertCount(25, $crawler->filter('#admin-players tr[data-admin-filter-target="row"]'));
        $crawler = $client->request('GET', '/admin/joueurs?q=Pagination-000', [], [], ['HTTP_X_GENESIS_ADMIN_LIST'=>'1']);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-admin-list-target="results"] tr[data-admin-filter-target="row"]'));
        self::assertStringNotContainsString('<!DOCTYPE', $client->getResponse()->getContent());
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
        $client->request('GET', '/admin/journal?export=all&action=test_even');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $client->getInternalResponse()->getContent();
        self::assertStringContainsString("'=sensitive-formula #0", $csv);
        self::assertStringContainsString('preuve-000', $csv);
        self::assertStringNotContainsString('test_odd', $csv);
        $client->request('GET', '/admin/feedbacks?status=all&page=4');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(25, '.feedback-admin-card');
        self::assertSelectorTextContains('.feedback-admin-metrics', '175');
        $currentAdmin = self::getContainer()->get(EntityManagerInterface::class)->find(Player::class, $admin->id);
        self::assertSame($feedback->id, $currentAdmin->preferences['admin_last_seen']['feedbacks']);
        $client->request('GET', '/admin/patchnotes?page=4');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(25, '.patchnote-editor');
        $client->request('GET', '/admin/journal?q=zz-aucun-journal', [], [], ['HTTP_X_GENESIS_ADMIN_LIST'=>'1']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-admin-filter-target="empty"]', 'Aucune entrée ne correspond à cette recherche.');
        $client->request('GET', '/admin/events?archived=1&page=2');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('nav[aria-label="Pagination des événements"]', 'Page 2 / 2');

        $client->loginUser($player);
        $client->request('GET', '/admin/journal?export=all', [], [], ['HTTP_X_GENESIS_ADMIN_LIST'=>'1']);
        self::assertFalse($client->getResponse()->isSuccessful());
    }
}
