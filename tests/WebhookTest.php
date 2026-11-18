<?php

declare(strict_types=1);

namespace Envello\Tests;

use Envello\Webhook;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebhookTest extends TestCase
{
    private const SECRET = 'whsec_test_secret';
    private const OLD_SECRET = 'whsec_old';
    private const TIMESTAMP = 1795000000;
    private const BODY = '{"type":"email.delivered","data":{"id":"e_1"}}';
    private const SIGNATURE = '01fd7cd18054befb25b082d367dca85a0494a916825e50896c202a70daf7d20c';
    private const OLD_SIGNATURE = '2dd8bb6eb3a2f5b581fa0fa9b72bbc243b42fad944b73591035a7c34d521c271';

    private static function header(string ...$signatures): string
    {
        $parts = ['t=' . self::TIMESTAMP];
        foreach ($signatures as $signature) {
            $parts[] = 'v1=' . $signature;
        }

        return implode(',', $parts);
    }

    public function testSpecTestVectorPasses(): void
    {
        $this->assertTrue(Webhook::verify(self::SECRET, self::BODY, self::header(self::SIGNATURE), 300, self::TIMESTAMP));
    }

    public function testPartWithoutEqualsIsSkippedLikeTheServer(): void
    {
        $header = 't=' . self::TIMESTAMP . ',junk,v1=' . self::SIGNATURE;
        $this->assertTrue(Webhook::verify(self::SECRET, self::BODY, $header, 300, self::TIMESTAMP));
    }

    public function testRotationHeaderPassesForEitherSecret(): void
    {
        $header = self::header(self::OLD_SIGNATURE, self::SIGNATURE);

        $this->assertTrue(Webhook::verify(self::SECRET, self::BODY, $header, 300, self::TIMESTAMP));
        $this->assertTrue(Webhook::verify(self::OLD_SECRET, self::BODY, $header, 300, self::TIMESTAMP));
    }

    public function testWrongSecretFails(): void
    {
        $this->assertFalse(Webhook::verify('whsec_wrong', self::BODY, self::header(self::SIGNATURE), 300, self::TIMESTAMP));
    }

    public function testTamperedBodyFails(): void
    {
        $tampered = '{"type":"email.delivered","data":{"id":"e_2"}}';

        $this->assertFalse(Webhook::verify(self::SECRET, $tampered, self::header(self::SIGNATURE), 300, self::TIMESTAMP));
    }

    public function testTimestampAtToleranceEdgePasses(): void
    {
        $this->assertTrue(Webhook::verify(self::SECRET, self::BODY, self::header(self::SIGNATURE), 300, self::TIMESTAMP + 300));
    }

    public function testStaleTimestampFails(): void
    {
        $this->assertFalse(Webhook::verify(self::SECRET, self::BODY, self::header(self::SIGNATURE), 300, self::TIMESTAMP + 301));
    }

    public function testFutureTimestampFails(): void
    {
        $this->assertFalse(Webhook::verify(self::SECRET, self::BODY, self::header(self::SIGNATURE), 300, self::TIMESTAMP - 301));
    }

    public function testDefaultsUseCurrentTime(): void
    {
        // The vector's t is 2026-11-18 11:06:40 UTC; any real clock after that
        // window is more than 300 s away, so the default time() must reject it.
        $this->assertFalse(Webhook::verify(self::SECRET, self::BODY, self::header(self::SIGNATURE)));
    }

    /** @return array<string, array{?string}> */
    public static function malformedHeaders(): array
    {
        $signature = self::SIGNATURE;

        return [
            'missing header' => [null],
            'empty header' => [''],
            'garbage' => ['not a signature'],
            'no timestamp' => ["v1={$signature}"],
            'no v1' => ['t=1795000000'],
            'empty timestamp' => ["t=,v1={$signature}"],
            'non-numeric timestamp' => ["t=abc,v1={$signature}"],
            'zero timestamp' => ["t=0,v1={$signature}"],
            'negative timestamp' => ["t=-1795000000,v1={$signature}"],
            'decimal timestamp' => ["t=1795000000.5,v1={$signature}"],
            'overflowing timestamp' => ["t=99999999999999999999999,v1={$signature}"],
            'duplicate timestamp' => ["t=1795000000,t=1795000000,v1={$signature}"],
            'part without equals' => ["t=1795000000,v1{$signature}"],
            'empty v1' => ['t=1795000000,v1='],
            'truncated v1' => ['t=1795000000,v1=01fd7c'],
            'only commas' => [',,,'],
        ];
    }

    #[DataProvider('malformedHeaders')]
    public function testMalformedOrMissingHeaderReturnsFalse(?string $header): void
    {
        $this->assertFalse(Webhook::verify(self::SECRET, self::BODY, $header, 300, self::TIMESTAMP));
    }

    public function testEmptySecretReturnsFalse(): void
    {
        $this->assertFalse(Webhook::verify('', self::BODY, self::header(self::SIGNATURE), 300, self::TIMESTAMP));
    }
}
