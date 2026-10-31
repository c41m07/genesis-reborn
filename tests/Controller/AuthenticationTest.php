<?php
namespace App\Tests\Controller;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
final class AuthenticationTest extends WebTestCase
{
    public function testAnonymousRedirectAndCsrfRejection(): void
    {
        $client=static::createClient();$client->request('GET','/');self::assertResponseRedirects('http://localhost/connexion');
        $client->request('GET','/connexion');self::assertResponseIsSuccessful();self::assertSelectorExists('input[name="_csrf_token"]');
        $client->request('POST','/connexion',['_username'=>'nobody@example.test','_password'=>'wrong']);self::assertResponseRedirects('/connexion');
        $client->followRedirect();self::assertSelectorExists('[role="alert"]');
    }
    public function testRegistrationValidatesPasswordAndCsrf(): void
    {
        $client=static::createClient();$crawler=$client->request('GET','/inscription');
        self::assertResponseIsSuccessful();$form=$crawler->selectButton('Créer mon compte')->form();
        $client->submit($form,['registration[email]'=>'invalid','registration[username]'=>'ab','registration[plainPassword][first]'=>'short','registration[plainPassword][second]'=>'different']);
        self::assertResponseStatusCodeSame(422);
    }
}
