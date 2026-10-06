<?php

declare(strict_types=1);

/**
 * Shared PayU callback renderer (inspired by payu-s2s-store).
 *
 * User-facing summary first; technical raw payload cleaned & collapsible.
 *
 * @param 'success'|'failure'|'cancel' $pageHint
 */
function render_callback_page(string $pageHint): void
{
    // Session needed to retrieve per-order salt for hash verification (Bug 3 fix)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $config = require dirname(__DIR__) . '/config.php';
    require_once __DIR__ . '/helpers.php';
    require_once __DIR__ . '/PayUResponseHash.php';

    $params = array_merge($_GET, $_POST);
    @mkdir(dirname(__DIR__) . '/storage', 0755, true);
    @file_put_contents(
        dirname(__DIR__) . '/storage/callbacks.log',
        date('c') . ' | ' . strtoupper($pageHint) . ' | ' . json_encode($params, JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND
    );

    $statusRaw = strtolower(trim((string) ($params['status'] ?? '')));
    if ($statusRaw === '' && $pageHint === 'cancel') {
        $statusRaw = 'cancelled';
    }
    if ($statusRaw === '' && $pageHint === 'failure') {
        $statusRaw = 'failure';
    }
    if ($statusRaw === '' && $pageHint === 'success') {
        $statusRaw = 'unknown';
    }

    $isSuccess = in_array($statusRaw, ['success', 'captured'], true);
    $isPending = in_array($statusRaw, ['pending', 'auth', 'enrolled', 'initiated'], true);
    $isCancel = in_array($statusRaw, ['cancelled', 'canceled', 'usercancelled'], true)
        || $pageHint === 'cancel';

    $bankMsg = trim((string) ($params['field9'] ?? $params['error_Message'] ?? $params['error_message'] ?? ''));
    if (strcasecmp($bankMsg, 'No Error') === 0) {
        $bankMsg = '';
    }

    if ($isSuccess) {
        $tone = 'ok';
        $icon = '✓';
        $title = 'Payment successful';
        $message = $bankMsg !== '' ? $bankMsg : 'Your payment was confirmed. Thank you!';
        $badge = 'Success';
    } elseif ($isPending) {
        $tone = 'pending';
        $icon = '…';
        $title = 'Payment pending';
        $message = $bankMsg !== '' ? $bankMsg : 'We are still confirming this payment with your bank.';
        $badge = 'Pending';
    } elseif ($isCancel) {
        $tone = 'muted';
        $icon = '–';
        $title = 'Payment cancelled';
        $message = 'You cancelled before the payment was completed. No money was charged.';
        $badge = 'Cancelled';
    } else {
        $tone = 'bad';
        $icon = '!';
        $title = 'Payment failed';
        $message = $bankMsg !== '' ? $bankMsg : 'Something went wrong. Please try another card or payment method.';
        $badge = 'Failed';
    }

    // Use the per-order salt stored at createOrder time (supports own-mode merchants).
    // Falls back to config default when not found (e.g. default UAT merchant).
    $txnSaltKey = 'payu_salt_' . trim((string) ($params['txnid'] ?? ''));
    $salt = (string) ($_SESSION[$txnSaltKey]
        ?? $config['merchant_salt']
        ?? $config['merchant_secret']
        ?? '');
    $hashValid = null;
    if ($salt !== '' && isset($params['hash']) && (string) $params['hash'] !== '') {
        $hashValid = PayUResponseHash::verify($params, $salt);
    }

    $amount = isset($params['amount']) && (string) $params['amount'] !== ''
        ? format_inr($params['amount'])
        : '—';
    $txnid = trim((string) ($params['txnid'] ?? ''));
    $product = trim((string) ($params['productinfo'] ?? ''));
    $customer = trim(trim((string) ($params['firstname'] ?? '') . ' ' . (string) ($params['lastname'] ?? '')));
    $email = trim((string) ($params['email'] ?? ''));
    $phone = trim((string) ($params['phone'] ?? ''));

    $cardType    = meaningful_value($params['card_type']    ?? '');
    $issuingBank = meaningful_value($params['issuing_bank'] ?? '');
    $cardnum     = trim((string) ($params['cardnum'] ?? $params['card_no'] ?? ''));
    $mode        = trim((string) ($params['mode'] ?? ''));

    // Expand short mode codes into readable labels
    $modeLabels = ['CC' => 'Credit Card', 'DC' => 'Debit Card', 'NB' => 'Net Banking',
                   'UPI' => 'UPI', 'EMI' => 'EMI', 'WALLET' => 'Wallet', 'CASH' => 'Cash'];
    $cardTypeDisplay = $cardType !== '' ? $cardType : ($modeLabels[strtoupper($mode)] ?? meaningful_value($mode));

    // Format masked card number as •••• •••• •••• 1234
    $cardnumDisplay = '';
    if ($cardnum !== '') {
        $digits = preg_replace('/\D/', '', $cardnum);
        if (strlen($digits) >= 4) {
            $cardnumDisplay = '•••• •••• •••• ' . substr($digits, -4);
        } else {
            $cardnumDisplay = preg_replace('/[xX*]+/', '••••', $cardnum) ?: $cardnum;
        }
    }

    $methodParts = array_filter([$cardTypeDisplay, $issuingBank]);
    $methodLabel = $methodParts !== [] ? implode(' · ', $methodParts) : '—';
    if ($cardnumDisplay !== '') {
        $methodLabel .= ($methodLabel !== '—' ? ' · ' : '') . $cardnumDisplay;
    }

    $paidOn = trim((string) ($params['addedon'] ?? ''));
    $payuId = trim((string) ($params['mihpayid'] ?? ''));
    $bankRef = trim((string) ($params['bank_ref_num'] ?? $params['bank_ref_no'] ?? ''));

    $userRows = [
        'Amount paid' => $amount,
        'Order ID' => $txnid,
        'For' => $product,
        'Paid with' => $methodLabel,
        'Paid by' => $customer,
        'Email' => $email,
        'Mobile' => $phone,
        'Date & time' => $paidOn,
    ];

    // Failure-only user message
    if (!$isSuccess && !$isCancel) {
        $errCode = trim((string) ($params['error'] ?? ''));
        if ($errCode !== '' && $errCode !== 'E000') {
            $userRows['Reason code'] = $errCode;
        }
    }

    $userRowsHtml = '';
    foreach ($userRows as $label => $value) {
        $value = trim((string) $value);
        if ($value === '' || $value === '—') {
            continue;
        }
        $userRowsHtml .= '<div class="result-row"><span>' . e($label) . '</span><strong>' . e($value) . '</strong></div>';
    }
    if ($userRowsHtml === '') {
        $userRowsHtml = '<div class="result-row"><span>Details</span><strong>No payment details received</strong></div>';
    }

    $techRows = [
        'PayU ID' => $payuId,
        'Bank reference' => $bankRef,
        'Status' => $statusRaw !== '' ? ucfirst($statusRaw) : '',
        'Gateway status' => trim((string) ($params['unmappedstatus'] ?? '')),
        'Payment source' => trim((string) ($params['payment_source'] ?? '')),
        'PG type' => trim((string) ($params['PG_TYPE'] ?? '')),
        'Hash check' => $hashValid === true ? 'Verified' : ($hashValid === false ? 'Failed' : ''),
    ];
    $techRowsHtml = '';
    foreach ($techRows as $label => $value) {
        $value = trim((string) $value);
        if ($value === '') {
            continue;
        }
        $cls = '';
        if ($label === 'Hash check' && $hashValid === true) {
            $cls = ' class="text-ok"';
        } elseif ($label === 'Hash check' && $hashValid === false) {
            $cls = ' class="text-bad"';
        }
        $techRowsHtml .= '<div class="result-row"><span>' . e($label) . '</span><strong' . $cls . '>' . e($value) . '</strong></div>';
    }

    $rawForDisplay = callback_clean_raw_params($params);
    $rawJson = e(json_encode(
        $rawForDisplay,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    ) ?: '{}');

    $iconClass = $tone === 'ok' ? 'ok' : ($tone === 'bad' ? 'bad' : ($tone === 'pending' ? 'pending' : 'muted'));
    $amountHero = $amount !== '—' ? $amount : '';

    $techBlock = $techRowsHtml !== ''
        ? '<details class="tech-fold"><summary>Technical details</summary><div class="result-details tech-details">' . $techRowsHtml . '</div></details>'
        : '';

    $amountBlock = '';
    if ($amountHero !== '') {
        $amountBlock = '<div class="callback-amount">' . e($amountHero) . '</div>';
        if ($product !== '') {
            $amountBlock .= '<p class="callback-product">' . e($product) . '</p>';
        }
    }

    $body = <<<HTML
<div class="result-page panel panel-pad callback-card">
  <div class="result-icon {$iconClass}" aria-hidden="true">{$icon}</div>
  <h1>{$title}</h1>
  <p class="sub">{$message}</p>
  {$amountBlock}
  <div class="result-details user-details">
    {$userRowsHtml}
  </div>
  {$techBlock}
  <div class="debug raw-fold" style="margin-top:1rem;">
    <details>
      <summary>Raw response from PayU</summary>
      <div class="debug-body">
        <pre class="raw-json">{$rawJson}</pre>
      </div>
    </details>
  </div>
  <div class="btn-row" style="margin-top:1.25rem;justify-content:center;">
    <a class="btn btn-primary" href="index.php" style="width:auto;min-width:160px;">New payment</a>
  </div>
</div>
HTML;

    header('Content-Type: text/html; charset=UTF-8');
    echo layout($title, $body, '', [
        'badge' => $badge,
        'subtitle' => 'Payment result',
    ]);
}

/**
 * Return the trimmed value if it carries real information, empty string otherwise.
 * Treats generic placeholders like "UNKNOWN", "NA", "N/A", "NULL", "NONE", "-", "0" as empty.
 */
function meaningful_value(mixed $raw): string
{
    $v = strtoupper(trim((string) $raw));
    static $noise = ['', 'UNKNOWN', 'NA', 'N/A', 'N\\A', 'NULL', 'NONE', '-', '--', '0', 'NIL', 'NOT AVAILABLE'];
    return in_array($v, $noise, true) ? '' : trim((string) $raw);
}

/**
 * Clean raw callback params for readable JSON (drop empties, parse nested JSON, shorten hash).
 *
 * @param array<string, mixed> $params
 * @return array<string, mixed>
 */
function callback_clean_raw_params(array $params): array
{
    $skipEmptyKeys = true;
    $out = [];

    foreach ($params as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        if (is_array($value)) {
            $nested = callback_clean_raw_params($value);
            if ($nested !== []) {
                $out[$key] = $nested;
            }
            continue;
        }

        $str = trim((string) $value);
        if ($skipEmptyKeys && $str === '') {
            continue;
        }

        // Shorten noisy hash for display
        if (strtolower($key) === 'hash' && strlen($str) > 24) {
            $out[$key] = substr($str, 0, 16) . '…' . substr($str, -8) . ' (' . strlen($str) . ' chars)';
            continue;
        }

        // Pretty nested JSON strings (e.g. splitInfo)
        if (
            (str_starts_with($str, '{') && str_ends_with($str, '}'))
            || (str_starts_with($str, '[') && str_ends_with($str, ']'))
        ) {
            $decoded = json_decode($str, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $out[$key] = is_array($decoded) ? callback_clean_raw_params($decoded) : $decoded;
                continue;
            }
        }

        $out[$key] = $str;
    }

    return $out;
}
