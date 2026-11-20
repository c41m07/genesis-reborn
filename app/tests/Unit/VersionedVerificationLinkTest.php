<?php

namespace App\Tests\Unit;

use App\Service\Player\{VerificationLinkGeneration, VerificationLinkKeyResolver, VersionedVerificationLink};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class VersionedVerificationLinkTest extends TestCase
{
    private function keys(array $keys): VerificationLinkKeyResolver
    {
        return new class($keys) implements VerificationLinkKeyResolver {
            public function __construct(private array $keys)
            {
            }

            public function resolve(string $keyId): ?string
            {
                return $this->keys[$keyId] ?? null;
            }
        };
    }

    public function testPersistedV1VectorIsStableAcrossInstances(): void
    {
        $clock = new MockClock('@1893455900');
        $keys = $this->keys(['test-1' => str_repeat('a', 32)]);
        // Vecteur connu : mêmes métadonnées après relecture dans un autre processus.
        $link = 'vkQ9iT8zkY92N7P5WNV0T-FV4ukhbIOmfypE2Mvzpkc';
        $generation = new VerificationLinkGeneration(5, 2, 1, 'test-1', str_repeat('ab', 32), 1893456000, '21a49bdd351c87216986c82d4901e4db1137212ec44377ab0e689c96438d3a88');
        $codec = new VersionedVerificationLink($clock);
        self::assertSame($link, $codec->reproduce($generation, $keys));
        self::assertTrue($codec->matches($generation, $link));
        self::assertSame(hash('sha256', $link), $generation->fingerprint);
        $restored = new VerificationLinkGeneration(...get_object_vars($generation));
        self::assertSame($link, (new VersionedVerificationLink($clock))->reproduce($restored, $keys));
    }

    public function testNewGenerationRequiresExplicitReplacementAndUsesHistoricalAlphabet(): void
    {
        $clock = new MockClock();
        $codec = new VersionedVerificationLink($clock);
        $keys = $this->keys(['test-1' => str_repeat('a', 32), 'test-2' => str_repeat('b', 32)]);
        $expiry = $clock->now()->modify('+24 hours');
        $original = $codec->generate(5, 1, 'test-1', $expiry, $keys);
        $link = $codec->reproduce($original, $keys);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/D', $link);
        self::assertSame($expiry->getTimestamp(), $original->expiresAt);
        self::assertSame($link, $codec->reproduce($original, $keys));
        $next = $codec->generate(5, 2, 'test-2', $expiry, $keys);
        self::assertFalse($codec->matches($next, $link));
        self::assertTrue($codec->matches($next, $codec->reproduce($next, $keys)));
        self::assertFalse($codec->matches($original, $link.'='));
    }

    public function testExpiryIsStrictAndMissingKeyNeverProducesReplacement(): void
    {
        $clock = new MockClock();
        $codec = new VersionedVerificationLink($clock);
        $keys = $this->keys(['test-1' => str_repeat('a', 32)]);
        $generation = $codec->generate(5, 1, 'test-1', $clock->now()->modify('+2 seconds'), $keys);
        $link = $codec->reproduce($generation, $keys);
        self::assertNull($codec->reproduce($generation, $this->keys([])));
        self::assertNull($codec->reproduce($generation, $this->keys(['test-1' => str_repeat('b', 32)])));
        self::assertNull($codec->reproduce($generation, $this->keys(['test-1' => 'short'])));
        // L'empreinte vérifie un ancien lien sans nécessiter de clé. Un retrait
        // pour compromission doit donc aussi révoquer la génération active.
        self::assertTrue($codec->matches($generation, $link));
        $clock->sleep(2);
        self::assertNull($codec->reproduce($generation, $keys));
        self::assertFalse($codec->matches($generation, $link));
    }

    public function testUnknownAlgorithmAndMismatchedMetadataFailClosed(): void
    {
        $clock = new MockClock();
        $codec = new VersionedVerificationLink($clock);
        $keys = $this->keys(['test-1' => str_repeat('a', 32)]);
        $generation = $codec->generate(5, 1, 'test-1', $clock->now()->modify('+10 seconds'), $keys);
        $link = $codec->reproduce($generation, $keys);
        $metadata = get_object_vars($generation);
        $unknown = new VerificationLinkGeneration(...array_replace($metadata, ['algorithm' => 99]));
        self::assertNull($codec->reproduce($unknown, $keys));
        self::assertFalse($codec->matches($unknown, $link));
        foreach (['playerId' => 6, 'version' => 2, 'expiresAt' => $generation->expiresAt + 10] as $field => $value) {
            self::assertNull($codec->reproduce(new VerificationLinkGeneration(...array_replace($metadata, [$field => $value])), $keys));
        }
    }

    public function testGenerationFailsWithUnavailableKey(): void
    {
        $clock = new MockClock();
        $this->expectException(\DomainException::class);
        (new VersionedVerificationLink($clock))->generate(1, 1, 'unknown', $clock->now()->modify('+10 seconds'), $this->keys([]));
    }
}
