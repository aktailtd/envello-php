<?php

declare(strict_types=1);

namespace Envello\Tests;

use Envello\Envello;
use Envello\EnvelloException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EnvelloTest extends TestCase
{
    private const INSECURE_BASE_URL_MESSAGE = 'The API URL must use https (http is only allowed for localhost).';

    /** @return array<string, array{string}> */
    public static function rejectedBaseUrls(): array
    {
        return [
            'remote http' => ['http://api.envello.dev'],
            'localhost as subdomain prefix' => ['http://localhost.evil.com'],
            'localhost as userinfo' => ['http://localhost@evil.com'],
            'other scheme' => ['ftp://localhost'],
            'not a url' => ['not a url'],
            'empty' => [''],
            'no host' => ['https://'],
            'broken ipv6' => ['http://[::1'],
        ];
    }

    #[DataProvider('rejectedBaseUrls')]
    public function testRejectsInsecureOrInvalidBaseUrl(string $baseUrl): void
    {
        $this->expectException(EnvelloException::class);
        $this->expectExceptionMessage(self::INSECURE_BASE_URL_MESSAGE);
        new Envello('env_test_abc123', $baseUrl);
    }

    /** @return array<string, array{string}> */
    public static function acceptedBaseUrls(): array
    {
        return [
            'https' => ['https://api.envello.dev'],
            'https with path' => ['https://self-hosted.example.com/envello/'],
            'http localhost' => ['http://localhost:3000'],
            'uppercase http localhost' => ['HTTP://LOCALHOST:3000'],
            'http 127.0.0.1' => ['http://127.0.0.1:3000'],
            'http ipv6 loopback' => ['http://[::1]:3000'],
        ];
    }

    #[DataProvider('acceptedBaseUrls')]
    public function testAcceptsHttpsAndHttpLoopback(string $baseUrl): void
    {
        $client = new Envello('env_test_abc123', $baseUrl);
        $this->assertInstanceOf(Envello::class, $client);
    }

    /** @return array<string, array{string}> */
    public static function headerInjectionKeys(): array
    {
        return [
            'CRLF' => ["abc\r\nX-Injected: 1"],
            'LF only' => ["abc\nX-Injected: 1"],
            'CR only' => ["abc\rX-Injected: 1"],
        ];
    }

    #[DataProvider('headerInjectionKeys')]
    public function testRejectsIdempotencyKeyWithLineBreaks(string $idempotencyKey): void
    {
        // Port 9 (discard) on loopback: if validation were missing, the call
        // would fail with a cURL error instead of this message.
        $client = new Envello('env_test_abc123', 'http://127.0.0.1:9');
        $this->expectException(EnvelloException::class);
        $this->expectExceptionMessage('idempotencyKey must not contain CR or LF characters');
        $client->sendEmail(
            from: 'you@yourdomain.com',
            to: 'user@example.com',
            subject: 'Hello',
            text: 'Hi',
            idempotencyKey: $idempotencyKey,
        );
    }
}
