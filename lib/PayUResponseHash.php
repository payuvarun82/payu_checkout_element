<?php

declare(strict_types=1);

/**
 * PayU reverse response hash verification (same variants as payu-s2s-store).
 */
final class PayUResponseHash
{
    public static function verify(array $response, string $salt): bool
    {
        $response = self::normalize($response);
        $posted = strtolower(trim((string) ($response['hash'] ?? '')));
        if ($posted === '' || !isset($response['status'])) {
            return false;
        }

        foreach (self::variants($response, $salt) as $calculated) {
            if (hash_equals($calculated, $posted)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private static function variants(array $response, string $salt): array
    {
        $status = (string) ($response['status'] ?? '');
        $additional = self::field($response, 'additionalCharges');
        $split = self::field($response, 'splitInfo');
        $empty = ['', '', '', '', ''];
        $tail = [
            (string) ($response['udf5'] ?? ''),
            (string) ($response['udf4'] ?? ''),
            (string) ($response['udf3'] ?? ''),
            (string) ($response['udf2'] ?? ''),
            (string) ($response['udf1'] ?? ''),
            (string) ($response['email'] ?? ''),
            (string) ($response['firstname'] ?? ''),
            (string) ($response['productinfo'] ?? ''),
            (string) ($response['amount'] ?? ''),
            (string) ($response['txnid'] ?? ''),
            (string) ($response['key'] ?? ''),
        ];

        $out = [];
        if ($additional !== '' && $split !== '') {
            $out[] = self::h([$additional, $salt, $status, $split, ...$empty, ...$tail]);
        }
        if ($additional !== '') {
            $out[] = self::h([$additional, $salt, $status, ...$empty, ...$tail]);
        }
        if ($split !== '') {
            $out[] = self::h([$salt, $status, $split, ...$empty, ...$tail]);
        }
        $out[] = self::h([$salt, $status, ...$empty, ...$tail]);

        return array_values(array_unique($out));
    }

    private static function h(array $parts): string
    {
        return strtolower(hash('sha512', implode('|', $parts)));
    }

    private static function normalize(array $response): array
    {
        if (isset($response['additional_charges']) && !isset($response['additionalCharges'])) {
            $response['additionalCharges'] = $response['additional_charges'];
        }
        if (isset($response['split_info']) && !isset($response['splitInfo'])) {
            $response['splitInfo'] = $response['split_info'];
        }

        return $response;
    }

    private static function field(array $response, string $key): string
    {
        if (!array_key_exists($key, $response) || $response[$key] === null) {
            return '';
        }

        return trim((string) $response[$key]);
    }
}
