<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/**
 * PayU Checkout Elements — Create Order (HMAC-SHA512).
 *
 * Frontend contract: pass transaction.encryptedOrderId to PayuCheckout.init({ orderId }).
 * @see docs/PayU-Checkout-JS-SDK-Integration-Guide.md
 */
final class PayUCheckoutElements
{
    public function __construct(private array $config)
    {
    }

    /**
     * hashString = body + "|" + date + "|" + merchantSecret
     * signature  = SHA512(hashString) lowercase hex, padded to 128 chars
     *
     * @return array{Date: string, Authorization: string, Content-Type: string, Accept: string}
     */
    public static function hmacHeaders(string $body, string $merchantKey, string $merchantSecret): array
    {
        $date = gmdate('D, d M Y H:i:s') . ' GMT';
        $signature = str_pad(
            hash('sha512', $body . '|' . $date . '|' . $merchantSecret),
            128,
            '0',
            STR_PAD_LEFT
        );

        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Date' => $date,
            'Authorization' => sprintf(
                'hmac username="%s", algorithm="sha512", headers="date", signature="%s"',
                $merchantKey,
                $signature
            ),
        ];
    }

    /**
     * POST /v1/checkout/l1
     *
     * @param array<string, mixed> $overrides
     * @return array{request: array, response: array, raw_body: string, http_status: int, headers: array}
     */
    public function createOrder(array $overrides = []): array
    {
        $merchantKey = (string) ($this->config['merchant_key'] ?? '');
        $merchantSecret = (string) ($this->config['merchant_secret'] ?? '');
        $apiBase = rtrim((string) ($this->config['checkout_api_url'] ?? 'https://apitest.payu.in'), '/');
        $appBase = app_base_url();
        $defaults = $this->config['default_order'] ?? [];

        if ($merchantKey === '' || $merchantSecret === '') {
            throw new RuntimeException('Set PAYU_MERCHANT_KEY and PAYU_MERCHANT_SECRET.');
        }

        $productinfo = trim((string) ($defaults['productinfo'] ?? 'Order'));
        if ($productinfo === '') {
            $productinfo = 'Order';
        }

        $payload = array_replace_recursive([
            'orderId' => 'ORD' . (string) (int) (microtime(true) * 1000) . bin2hex(random_bytes(4)),
            'currency' => 'INR',
            'paymentSource' => 'direct',
            // Top-level productinfo is mandatory for the downstream PayU transaction
            'productinfo' => $productinfo,
            'order' => [
                'amount' => (float) ($defaults['amount'] ?? 100),
                'productinfo' => $productinfo,
            ],
            'customer' => [
                'firstName' => (string) ($defaults['firstname'] ?? 'Guest'),
                'lastName' => (string) ($defaults['lastname'] ?? ''),
                'email' => (string) ($defaults['email'] ?? ''),
                'phoneNumber' => (string) ($defaults['phone'] ?? ''),
            ],
            'callBackActions' => [
                'successUrl' => $appBase . '/success.php',
                'failureUrl' => $appBase . '/failure.php',
                'cancelUrl' => $appBase . '/cancel.php',
            ],
            'udf1' => (string) ($defaults['udf1'] ?? ''),
            'udf2' => (string) ($defaults['udf2'] ?? ''),
            'udf5' => (string) ($defaults['udf5'] ?? ''),
            'additionalPaymentParams' => $this->config['additionalPaymentParams'] ?? [],
        ], $overrides);

        // Ensure productinfo survives partial overrides that only set order.amount
        if (!isset($payload['productinfo']) || trim((string) $payload['productinfo']) === '') {
            $payload['productinfo'] = $productinfo;
        }
        if (!isset($payload['order']) || !is_array($payload['order'])) {
            $payload['order'] = [];
        }
        if (!isset($payload['order']['productinfo']) || trim((string) $payload['order']['productinfo']) === '') {
            $payload['order']['productinfo'] = (string) $payload['productinfo'];
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            throw new RuntimeException('Failed to encode Create Order JSON.');
        }

        $headers = self::hmacHeaders($body, $merchantKey, $merchantSecret);
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $ch = curl_init($apiBase . '/v1/checkout/l1');
        if ($ch === false) {
            throw new RuntimeException('Unable to init curl.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $errno !== 0) {
            throw new RuntimeException('Create Order request failed: ' . ($error ?: 'curl error'));
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Non-JSON response (HTTP ' . $status . '): ' . substr($raw, 0, 400));
        }

        if ($status < 200 || $status >= 300) {
            $message = (string) ($decoded['message'] ?? $decoded['error'] ?? $raw);
            throw new RuntimeException('Create Order failed (HTTP ' . $status . '): ' . $message);
        }

        $encryptedOrderId = $decoded['transaction']['encryptedOrderId'] ?? null;
        if (!is_string($encryptedOrderId) || $encryptedOrderId === '') {
            throw new RuntimeException('Response missing transaction.encryptedOrderId.');
        }

        return [
            'request' => $payload,
            'response' => $decoded,
            'raw_body' => $body,
            'http_status' => $status,
            'headers' => $headers,
        ];
    }
}
