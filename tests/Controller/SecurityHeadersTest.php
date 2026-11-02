<?php
namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityHeadersTest extends WebTestCase
{
    public function testBaselineSecurityHeadersAreAddedWithoutOverridingSpecificPolicies(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'genesis-reborn.localhost']);
        $client->request('GET', '/connexion');
        self::assertResponseIsSuccessful();
        $headers = $client->getResponse()->headers;
        self::assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        self::assertSame('DENY', $headers->get('X-Frame-Options'));
        self::assertSame('strict-origin-when-cross-origin', $headers->get('Referrer-Policy'));
        self::assertSame('camera=(), geolocation=(), microphone=()', $headers->get('Permissions-Policy'));

        $client->request('GET', '/mot-de-passe/reinitialiser/invalid-token');
        self::assertSame('no-referrer', $client->getResponse()->headers->get('Referrer-Policy'));
        self::assertSame('nosniff', $client->getResponse()->headers->get('X-Content-Type-Options'));
    }
}
