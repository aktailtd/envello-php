<?php

declare(strict_types=1);

namespace Envello;

/**
 * Minimal client for the Envello transactional email API
 * (https://envello.dev). Targets the real POST /emails, GET /emails/{id},
 * and POST /emails/validate request/response shapes defined in
 * the Envello API's request/response shapes — keep the two in sync if they
 * changes.
 */
class Envello
{
    private const INSECURE_BASE_URL_MESSAGE = 'The API URL must use https (http is only allowed for localhost).';
    private const LOOPBACK_HOSTS = ['localhost', '127.0.0.1', '[::1]'];

    private string $apiKey;
    private string $baseUrl;
    private int $timeoutSeconds;

    /**
     * @throws EnvelloException If $baseUrl is not https (http is only allowed for localhost).
     */
    public function __construct(
        string $apiKey,
        string $baseUrl = 'https://api.envello.dev',
        int $timeoutSeconds = 10,
    ) {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('apiKey is required');
        }
        self::assertSecureBaseUrl($baseUrl);
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeoutSeconds = $timeoutSeconds;
    }

    /** Refuse to send the API key over plain http to anything but loopback. */
    private static function assertSecureBaseUrl(string $baseUrl): void
    {
        $parts = parse_url($baseUrl);
        if ($parts === false || !isset($parts['scheme'], $parts['host']) || $parts['host'] === '') {
            throw new EnvelloException(self::INSECURE_BASE_URL_MESSAGE);
        }
        // parse_url keeps IPv6 brackets ("[::1]") and the original case.
        $scheme = strtolower($parts['scheme']);
        $isLoopback = in_array(strtolower($parts['host']), self::LOOPBACK_HOSTS, true);
        if ($scheme !== 'https' && !($scheme === 'http' && $isLoopback)) {
            throw new EnvelloException(self::INSECURE_BASE_URL_MESSAGE);
        }
    }

    /**
     * Send a single email. Maps to `POST /emails`.
     *
     * Mirrors the Envello API's `sendEmailRequestSchema` request shape.
     * At least one of `$html` or `$text` is required.
     *
     * @param string|string[] $to Up to 50 (MAX_RECIPIENTS_PER_FIELD) addresses.
     * @param string|string[]|null $cc
     * @param string|string[]|null $bcc
     * @param array<string, string>|null $headers
     * @param array<int, array{filename?: string, contentType?: string, content?: string, url?: string}>|null $attachments
     *   Base64 attachments set `content`; remote attachments set `url` — don't set both on the same item.
     * @param string|null $sendAt ISO 8601 timestamp with a UTC offset, e.g. "2026-08-01T09:00:00+00:00". Omit to send immediately.
     * @param string|null $idempotencyKey Sent as the `Idempotency-Key` header for safe retries. Must not contain CR or LF.
     * @return array{id: string, status: string}
     * @throws EnvelloException
     */
    public function sendEmail(
        string $from,
        string|array $to,
        string $subject,
        ?string $html = null,
        ?string $text = null,
        string|array|null $cc = null,
        string|array|null $bcc = null,
        ?string $replyTo = null,
        ?array $headers = null,
        ?array $attachments = null,
        ?string $sendAt = null,
        ?string $idempotencyKey = null,
    ): array {
        if ($html === null && $text === null) {
            throw new \InvalidArgumentException('At least one of $html or $text is required');
        }
        // The key goes straight into a raw header line, so CR/LF would inject headers.
        if ($idempotencyKey !== null && strpbrk($idempotencyKey, "\r\n") !== false) {
            throw new EnvelloException('idempotencyKey must not contain CR or LF characters');
        }

        $payload = [
            'from' => $from,
            'to' => $to,
            'subject' => $subject,
        ];
        if ($cc !== null) {
            $payload['cc'] = $cc;
        }
        if ($bcc !== null) {
            $payload['bcc'] = $bcc;
        }
        if ($replyTo !== null) {
            $payload['reply_to'] = $replyTo;
        }
        if ($html !== null) {
            $payload['html'] = $html;
        }
        if ($text !== null) {
            $payload['text'] = $text;
        }
        if ($headers !== null) {
            $payload['headers'] = $headers;
        }
        if ($attachments !== null) {
            $payload['attachments'] = $attachments;
        }
        if ($sendAt !== null) {
            $payload['send_at'] = $sendAt;
        }

        $extraHeaders = [];
        if ($idempotencyKey !== null) {
            $extraHeaders[] = 'Idempotency-Key: ' . $idempotencyKey;
        }

        /** @var array{id: string, status: string} */
        return $this->request('POST', '/emails', $payload, $extraHeaders);
    }

    /**
     * Validate an email address (syntax, disposable-domain check,
     * role-account check, MX lookup). Free on every plan, but still requires
     * a valid API key. Maps to `POST /emails/validate`.
     *
     * `role_account` (e.g. support@, admin@) is informational only and does
     * not affect `valid` - a role address is a real, permanently deliverable
     * mailbox, unlike `disposable`.
     *
     * @return array{email: string, valid: bool, syntax_valid: bool, disposable: bool, role_account: bool, mx_found: bool, reason?: string}
     * @throws EnvelloException
     */
    public function validateEmail(string $email): array
    {
        /** @var array{email: string, valid: bool, syntax_valid: bool, disposable: bool, role_account: bool, mx_found: bool, reason?: string} */
        return $this->request('POST', '/emails/validate', ['email' => $email]);
    }

    /**
     * Fetch the current status of a send. Maps to `GET /emails/{id}`.
     *
     * @return array{id: string, status: string, to: string[], subject: string, send_at: ?string, sent_at: ?string, error: ?string, created_at: string}
     * @throws EnvelloException
     */
    public function getEmail(string $id): array
    {
        /** @var array{id: string, status: string, to: string[], subject: string, send_at: ?string, sent_at: ?string, error: ?string, created_at: string} */
        return $this->request('GET', '/emails/' . rawurlencode($id));
    }

    /**
     * @param array<string, mixed>|null $body
     * @param string[] $extraHeaders
     * @return array<string, mixed>
     * @throws EnvelloException
     */
    private function request(string $method, string $path, ?array $body = null, array $extraHeaders = []): array
    {
        $ch = curl_init($this->baseUrl . $path);
        if ($ch === false) {
            throw new EnvelloException('Failed to initialize cURL handle');
        }

        $headers = array_merge([
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: envello-php/0.1.0',
        ], $extraHeaders);

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
        }

        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new EnvelloException('cURL request failed: ' . $error);
        }
        if (!is_string($responseBody)) {
            $responseBody = '';
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = $responseBody !== '' ? json_decode($responseBody, true) : [];
        if (!is_array($decoded)) {
            $decoded = [];
        }

        if ($statusCode >= 400) {
            $errorCode = is_string($decoded['error'] ?? null) ? $decoded['error'] : null;
            $message = is_string($decoded['message'] ?? null) ? $decoded['message'] : null;
            throw new EnvelloException(
                sprintf(
                    'Envello API error (%d): %s%s',
                    $statusCode,
                    $errorCode ?? 'unknown_error',
                    $message !== null ? " - {$message}" : '',
                ),
                $statusCode,
                $errorCode,
                $decoded,
            );
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
