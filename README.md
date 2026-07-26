# envello (PHP SDK)

Minimal PHP client for the [Envello](https://envello.dev) transactional
email API.

## Install

```bash
composer require envello/envello-php
```

## Usage

```php
<?php

require 'vendor/autoload.php';

use Envello\Envello;
use Envello\EnvelloException;

$client = new Envello('env_test_xxx');

try {
    $result = $client->sendEmail(
        from: 'you@yourdomain.com',
        to: 'user@example.com',
        subject: 'Hello from Envello',
        html: '<p>Hi there</p>',
    );
    echo $result['id'] . ' ' . $result['status'] . PHP_EOL;
} catch (EnvelloException $e) {
    echo $e->getStatusCode() . ' ' . $e->getErrorCode() . ' ' . $e->getMessage() . PHP_EOL;
}
```

Override the base URL for local dev or a self-hosted deployment via the
second constructor argument: `new Envello('env_test_xxx', 'http://localhost:3000')`.

## API surface

- `new Envello(string $apiKey, string $baseUrl = 'https://api.envello.dev', int $timeoutSeconds = 10)`
- `sendEmail(...)` — maps to `POST /emails`. Named arguments mirror the
  Envello API's send-email request shape; at least one of `html`/`text` is
  required (throws `InvalidArgumentException` otherwise, before any
  network call). Returns `['id' => ..., 'status' => ...]`.
- `validateEmail(string $email)` — maps to `POST /emails/validate`, free on
  every plan. Returns `['email' => ..., 'valid' => ..., 'syntax_valid' => ...,
  'disposable' => ..., 'mx_found' => ..., 'reason' => ...]`.
- `getEmail(string $id)` — maps to `GET /emails/{id}`.

Non-2xx responses throw `Envello\EnvelloException`, with `getStatusCode()`,
`getErrorCode()` (the API's machine-readable `error` field, e.g.
`"rate_limited"`), `getMessage()`, and `getBody()` (the full decoded JSON
response).

This SDK is deliberately minimal (send + validate + status lookup via raw
cURL, no dependencies beyond `ext-curl`/`ext-json`) rather than a full
framework-integrated client — see the
[Python SDK](https://github.com/Bergblumestudio/envello-python) for the
fully-featured reference SDK.

## Development

```bash
composer validate --strict
php -l src/Envello.php
php -l src/EnvelloException.php
```
