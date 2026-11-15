<?php

namespace App\Tests\Unit;

use App\Service\Colony\LiveRegionResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class LiveRegionResponseTest extends TestCase
{
    public static function encodings(): iterable
    {
        yield ['', false];
        yield ['gzip', true];
        yield ['br, gzip;q=0.5', true];
        yield ['gzip;q=0', false];
        yield ['br', false];
        yield ['*;q=1', true];
        yield ['*;q=0', false];
        yield ['gzip;q=0, *;q=1', false];
    }

    #[DataProvider('encodings')]
    public function testFragmentsStayIdenticalAndRespectAcceptedEncodings(string $encoding, bool $compressed): void
    {
        $request = Request::create('/flottes');
        $request->headers->set('Accept-Encoding', $encoding);
        $regions = ['available-fleets'=>str_repeat('<p>Flotte · énergie &amp; métal</p>', 100)];
        $response = (new LiveRegionResponse())->create($request, $regions);
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertContains('Accept-Encoding', $response->getVary());
        self::assertSame($compressed, $response->headers->get('Content-Encoding') === 'gzip');
        $content = $response->getContent();
        if ($compressed) {
            self::assertLessThan(1024, strlen($content));
            $content = gzdecode($content);
        }
        self::assertSame(['regions'=>$regions], json_decode($content, true, 512, JSON_THROW_ON_ERROR));
    }
}
