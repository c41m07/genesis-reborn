<?php
namespace App\Tests\Service;

use App\Entity\Player;
use App\Manager\GameManager;
use App\Service\Player\PreferencesService;
use App\Validator\DTO\PreferencesData;
use PHPUnit\Framework\TestCase;

final class PreferencesServiceTest extends TestCase
{
    public function testUninitializedPreferencesUseDarkDefaults(): void
    {
        $player = new Player();
        unset($player->preferences);
        $manager = (new \ReflectionClass(GameManager::class))->newInstanceWithoutConstructor();
        $data = new PreferencesData();

        (new PreferencesService($manager))->hydrate($player, $data);

        self::assertSame('Europe/Paris', $data->timezone);
        self::assertSame('dark', $data->theme);
        self::assertSame('allies', $data->transportReception);
        self::assertFalse($data->reducedMotion);
        self::assertTrue($data->realtimeNotifications);
    }
}
