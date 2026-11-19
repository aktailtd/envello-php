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
The base URL must use `https`. Plain `http` is only accepted for `localhost`,
`127.0.0.1` and `[::1]`; anything else throws `EnvelloException` so the API key
never goes out unencrypted.

## Verifying webhooks

Every webhook request carries an `Envello-Signature` header. Check it against
the raw request body before trusting the payload:

```php
<?php

require 'vendor/autoload.php';

use Envello\Webhook;

$secret = (string) getenv('ENVELLO_WEBHOOK_SECRET'); // whsec_..., from the dashboard
$rawBody = file_get_contents('php://input');
$header = $_SERVER['HTTP_ENVELLO_SIGNATURE'] ?? null;

if ($rawBody === false || !Webhook::verify($secret, $rawBody, $header)) {
    http_response_code(400);
    exit;
}

$event = json_decode($rawBody, true);
http_response_code(200);
```

`Webhook::verify(string $secret, string $rawBody, ?string $header, int $toleranceSeconds = 300, ?int $now = null): bool`
returns `false` for a bad signature, a timestamp more than `$toleranceSeconds`
away from now (either direction), or a malformed or missing header. It never
throws for bad input. Pass the body exactly as received: re-encoding parsed
JSON changes the bytes and breaks the signature. During secret rotation the
header carries one `v1` per active secret and any match passes.

## API surface

- `new Envello(string $apiKey, string $baseUrl = 'https://api.envello.dev', int $timeoutSeconds = 10)`
- `sendEmail(...)`: maps to `POST /emails`. Named arguments mirror the
  Envello API's send-email request shape; at least one of `html`/`text` is
  required (throws `InvalidArgumentException` otherwise, before any
  network call). Returns `['id' => ..., 'status' => ...]`.
- `validateEmail(string $email)`: maps to `POST /emails/validate`, free on
  every plan. Returns `['email' => ..., 'valid' => ..., 'syntax_valid' => ...,
  'disposable' => ..., 'mx_found' => ..., 'reason' => ...]`.
- `getEmail(string $id)`: maps to `GET /emails/{id}`.

Non-2xx responses throw `Envello\EnvelloException`, with `getStatusCode()`,
`getErrorCode()` (the API's machine-readable `error` field, e.g.
`"rate_limited"`), `getMessage()`, and `getBody()` (the full decoded JSON
response).

This SDK is deliberately minimal (send + validate + status lookup via raw
cURL, no dependencies beyond `ext-curl`/`ext-json`) rather than a full
framework-integrated client. See the
[Python SDK](https://github.com/aktailtd/envello-python) for the
fully-featured reference SDK.

## Development

```bash
composer validate --strict
php -l src/Envello.php
php -l src/EnvelloException.php
php -l src/Webhook.php
composer install && composer test
```
