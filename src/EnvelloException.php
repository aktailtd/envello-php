<?php

declare(strict_types=1);

namespace Envello;

/**
 * Thrown for any transport failure (cURL error) or non-2xx response from
 * the Envello API.
 */
class EnvelloException extends \RuntimeException
{
    /**
     * @param array<string, mixed> $body The full parsed JSON response body, if any.
     */
    public function __construct(
        string $message,
        private readonly ?int $statusCode = null,
        private readonly ?string $errorCode = null,
        private readonly array $body = [],
    ) {
        parent::__construct($message);
    }

    /** HTTP status code, or null for a transport-level failure (no response). */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    /** Machine-readable `error` field from the response body, e.g. "rate_limited". */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /** @return array<string, mixed> */
    public function getBody(): array
    {
        return $this->body;
    }
}
