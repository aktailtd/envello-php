<?php

declare(strict_types=1);

namespace Envello;

/**
 * Verifies the `Envello-Signature` header on incoming webhook requests.
 *
 * Header format: `t=<unix-seconds>,v1=<hex hmac-sha256>[,v1=<hex>...]`.
 * Several `v1` values appear during secret rotation; any match passes.
 * The signed content is `<t>.<raw body>`, keyed with the endpoint secret
 * exactly as shown in the dashboard, `whsec_` prefix included.
 */
final class Webhook
{
    public const DEFAULT_TOLERANCE_SECONDS = 300;

    private const HMAC_ALGORITHM = 'sha256';
    private const TIMESTAMP_KEY = 't';
    private const SIGNATURE_KEY = 'v1';

    /**
     * Returns true only if the header carries a fresh timestamp and at least
     * one v1 signature matching `$rawBody`. Any malformed input returns false;
     * this never throws for bad input.
     *
     * @param string      $secret           Endpoint secret, including the `whsec_` prefix.
     * @param string      $rawBody          Request body exactly as received. Never re-encode parsed JSON.
     * @param string|null $header           Value of the `Envello-Signature` header.
     * @param int         $toleranceSeconds Max allowed |now - t| in seconds.
     * @param int|null    $now              Unix time override, for tests. Defaults to time().
     */
    public static function verify(
        string $secret,
        string $rawBody,
        ?string $header,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS,
        ?int $now = null,
    ): bool {
        if ($secret === '' || $header === null || $header === '') {
            return false;
        }

        $parsed = self::parseHeader($header);
        if ($parsed === null) {
            return false;
        }
        [$timestamp, $signatures] = $parsed;

        $currentTime = $now ?? time();
        if (abs($currentTime - $timestamp) > $toleranceSeconds) {
            return false;
        }

        $expected = hash_hmac(self::HMAC_ALGORITHM, $timestamp . '.' . $rawBody, $secret);

        // Check every candidate with no early exit, so timing doesn't reveal
        // which (or whether an earlier) rotation signature matched.
        $matched = false;
        foreach ($signatures as $signature) {
            $matched = hash_equals($expected, $signature) || $matched;
        }

        return $matched;
    }

    /**
     * @return array{int, list<string>}|null Timestamp and v1 signatures, or null if malformed.
     */
    private static function parseHeader(string $header): ?array
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) {
                return null;
            }
            [$key, $value] = $pair;

            if ($key === self::TIMESTAMP_KEY) {
                if ($timestamp !== null) {
                    return null;
                }
                $timestamp = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($timestamp === false) {
                    return null;
                }
            } elseif ($key === self::SIGNATURE_KEY) {
                $signatures[] = $value;
            }
            // Unknown keys are ignored so future signature schemes don't break old SDKs.
        }

        if ($timestamp === null || $signatures === []) {
            return null;
        }

        return [$timestamp, $signatures];
    }
}
