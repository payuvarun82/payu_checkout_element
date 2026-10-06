<?php

declare(strict_types=1);

/**
 * PayU Checkout Elements — merchant page.
 *
 * Frontend follows:
 *   docs/PayU-Checkout-JS-SDK-Integration-Guide.md
 *   docs/Checkout-Elements-Configurable-Fields.md
 *
 * Backend Create Order (HMAC) supplies transaction.encryptedOrderId for PayuCheckout.init.
 */

session_start();

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/PayUCheckoutElements.php';

// ── Combined form POST handler ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'save_settings') {
    $env    = ($_POST['env'] ?? 'uat') === 'production' ? 'production' : 'uat';
    $key    = trim((string) ($_POST['merchant_key']    ?? ''));
    $secret = trim((string) ($_POST['merchant_secret'] ?? ''));
    $amount = trim((string) ($_POST['amount']          ?? ''));

    // Production always requires own credentials — never allow default mode for production
    $merchantMode = ($env === 'production')
        ? 'own'
        : (($_POST['merchant_mode'] ?? 'default') === 'own' ? 'own' : 'default');

    $_SESSION['payu_env']           = $env;
    $_SESSION['payu_merchant_mode'] = $merchantMode;

    if ($merchantMode === 'default') {
        // UAT default — use config credentials, clear any saved overrides
        unset($_SESSION['payu_merchant_key'], $_SESSION['payu_merchant_secret']);
    } else {
        // Own merchant (UAT own or any production) — both key AND salt are mandatory
        if ($key === '' || $secret === '') {
            // Bounce back with an error rather than silently using mismatched credentials
            header('Location: index.php?err=missing_credentials');
            exit;
        }
        $_SESSION['payu_merchant_key']    = $key;
        $_SESSION['payu_merchant_secret'] = $secret;
    }

    $safeAmt  = filter_var($amount, FILTER_VALIDATE_FLOAT);
    $redirect = ($safeAmt !== false && $safeAmt > 0)
        ? 'index.php?amount=' . urlencode((string) $safeAmt)
        : 'index.php';
    header('Location: ' . $redirect);
    exit;
}

// ── Merge session credentials into config ────────────────────────────────────
$sessionEnv          = $_SESSION['payu_env']           ?? null;
$sessionMerchantMode = $_SESSION['payu_merchant_mode'] ?? 'default';
$sessionKey          = $_SESSION['payu_merchant_key']    ?? null;
$sessionSecret       = $_SESSION['payu_merchant_secret'] ?? null;

// Only apply overrides when "own merchant" mode is active
if ($sessionMerchantMode === 'own') {
    // Guard: if mode is 'own' but credentials were already consumed (page refresh),
    // bounce back to the form instead of silently using default credentials.
    if ($sessionKey === null && $sessionSecret === null) {
        unset($_SESSION['payu_merchant_mode']);
        header('Location: index.php?err=missing_credentials');
        exit;
    }
    if ($sessionKey !== null && $sessionKey !== '') {
        $config['merchant_key'] = $sessionKey;
    }
    if ($sessionSecret !== null && $sessionSecret !== '') {
        $config['merchant_secret'] = $sessionSecret;
        $config['merchant_salt']   = $sessionSecret;
    }
    // Clear credentials AND mode from session — used once, then gone.
    unset(
        $_SESSION['payu_merchant_key'],
        $_SESSION['payu_merchant_secret'],
        $_SESSION['payu_merchant_mode']
    );
}
if ($sessionEnv === 'production') {
    $config['checkout_api_url'] = $config['production']['checkout_api_url'] ?? 'https://api.payu.in';
    $config['elements_sdk_url'] = $config['production']['elements_sdk_url']
        ?? 'https://jssdk.payu.in/checkout/payu-checkout-elements.umd.js';
}

$envBadge = ($sessionEnv === 'production') ? 'PRODUCTION' : 'UAT';
$sdkUrl   = e((string) $config['elements_sdk_url']);
$apiUrl   = e((string) $config['checkout_api_url']);

// ── Amount gate ───────────────────────────────────────────────────────────────
$requestedAmount = null;
$rawAmt = $_GET['amount'] ?? '';
if ($rawAmt !== '') {
    $parsed = filter_var($rawAmt, FILTER_VALIDATE_FLOAT);
    if ($parsed !== false && $parsed > 0) {
        $requestedAmount = $parsed;
    }
}

// Show combined credentials + amount form when:
//   • no session yet (first visit), OR
//   • no valid amount in URL, OR
//   • ?settings=1 explicitly requested, OR
//   • credential validation failed on POST
if ($sessionEnv === null || $requestedAmount === null || isset($_GET['settings']) || isset($_GET['err'])) {

    $isUat      = $sessionEnv !== 'production';
    $isOwnMode  = ($sessionMerchantMode === 'own') || !$isUat; // production always = own

    // Key: pre-fill from session when in own mode; otherwise blank
    $curKey = e((string) ($isOwnMode && $sessionKey !== null ? $sessionKey : ''));
    // Salt placeholder — actual value never sent to browser; always prompt for entry
    $saltPlaceholder = $isUat ? 'Enter your merchant salt' : 'Enter your production salt';

    // Radio checked states
    $uatChecked     = $isUat      ? 'checked' : '';
    $prodChecked    = !$isUat     ? 'checked' : '';
    $ownChecked     = $isOwnMode  ? 'checked' : '';
    $defaultChecked = !$isOwnMode ? 'checked' : '';
    // Credential fields & merchant-type row visibility (CSS)
    $credDisplay = $isOwnMode ? 'block' : 'none';
    $uatDisplay  = $isUat    ? 'block' : 'none';
    // Only show the credential error when Own Merchant / Production mode is active.
    // If the user is (or has switched back to) Default mode, the error is irrelevant.
    $credErrHtml = (isset($_GET['err']) && $_GET['err'] === 'missing_credentials' && $isOwnMode)
        ? '<div id="cred-err" class="alert alert-error is-visible" style="margin-bottom:1rem;">Both Merchant Key and Merchant Salt are required.</div>'
        : '';
    $uatChecked  = $isUat  ? 'checked' : '';
    $prodChecked = !$isUat ? 'checked' : '';
    $defaultAmt  = number_format((float) ($config['default_order']['amount'] ?? 100), 2, '.', '');

    $combinedFormHtml = <<<HTML
<div class="result-page panel panel-pad" style="max-width:480px;text-align:left;">
  <p class="eyebrow" style="margin:0 0 .6rem;font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);font-weight:600;">New order</p>
  <h1 style="margin:0 0 .35rem;font-family:var(--display);font-size:1.7rem;font-weight:600;letter-spacing:-.02em;">Configure &amp; pay</h1>
  <p style="margin:0 0 1.5rem;color:var(--muted);font-size:.92rem;line-height:1.45;">Choose your environment and merchant, then enter the amount.</p>
  {$credErrHtml}

  <form method="post" action="index.php" autocomplete="off">
    <input type="hidden" name="_action" value="save_settings">

    <!-- ── Environment ── -->
    <p style="margin:0 0 .45rem;font-size:.82rem;font-weight:600;color:var(--muted);letter-spacing:.04em;text-transform:uppercase;">Environment</p>
    <div style="display:flex;gap:.65rem;margin-bottom:1.4rem;">
      <label style="flex:1;display:flex;align-items:center;gap:.6rem;padding:.75rem 1rem;border-radius:12px;border:1.5px solid var(--line);cursor:pointer;background:#fff;transition:border-color .15s,background .15s;" id="lbl-uat">
        <input type="radio" name="env" value="uat" id="env-uat" style="accent-color:var(--accent);" {$uatChecked} onchange="onEnvChange()">
        <span>
          <strong style="display:block;font-size:.9rem;">UAT</strong>
          <span style="font-size:.75rem;color:var(--muted);">https://apitest.payu.in</span>
        </span>
      </label>
      <label style="flex:1;display:flex;align-items:center;gap:.6rem;padding:.75rem 1rem;border-radius:12px;border:1.5px solid var(--line);cursor:pointer;background:#fff;transition:border-color .15s,background .15s;" id="lbl-prod">
        <input type="radio" name="env" value="production" id="env-prod" style="accent-color:var(--accent);" {$prodChecked} onchange="onEnvChange()">
        <span>
          <strong style="display:block;font-size:.9rem;">Production</strong>
          <span style="font-size:.75rem;color:var(--muted);">https://api.payu.in</span>
        </span>
      </label>
    </div>

    <!-- ── Merchant type (UAT only) ── -->
    <div id="merchant-type-row" style="display:{$uatDisplay};margin-bottom:1.4rem;">
      <p style="margin:0 0 .45rem;font-size:.82rem;font-weight:600;color:var(--muted);letter-spacing:.04em;text-transform:uppercase;">Merchant</p>
      <div style="display:flex;gap:.65rem;">
        <label style="flex:1;display:flex;align-items:center;gap:.6rem;padding:.75rem 1rem;border-radius:12px;border:1.5px solid var(--line);cursor:pointer;background:#fff;transition:border-color .15s,background .15s;" id="lbl-default">
          <input type="radio" name="merchant_mode" value="default" id="mode-default" style="accent-color:var(--accent);" {$defaultChecked} onchange="onModeChange()">
          <span>
            <strong style="display:block;font-size:.9rem;">Default</strong>
            <span style="font-size:.75rem;color:var(--muted);">PayU test credentials</span>
          </span>
        </label>
        <label style="flex:1;display:flex;align-items:center;gap:.6rem;padding:.75rem 1rem;border-radius:12px;border:1.5px solid var(--line);cursor:pointer;background:#fff;transition:border-color .15s,background .15s;" id="lbl-own">
          <input type="radio" name="merchant_mode" value="own" id="mode-own" style="accent-color:var(--accent);" {$ownChecked} onchange="onModeChange()">
          <span>
            <strong style="display:block;font-size:.9rem;">Own Merchant</strong>
            <span style="font-size:.75rem;color:var(--muted);">Your key &amp; salt</span>
          </span>
        </label>
      </div>
    </div>

    <!-- ── Credentials (shown for Own Merchant / Production only) ── -->
    <div id="cred-fields" style="display:{$credDisplay};">
      <label style="display:block;font-size:.82rem;font-weight:600;color:var(--muted);letter-spacing:.04em;text-transform:uppercase;margin-bottom:.45rem;" for="inp-key">Merchant Key</label>
      <div style="display:flex;align-items:center;border:1.5px solid var(--line);border-radius:12px;overflow:hidden;background:#fff;margin-bottom:1rem;transition:border-color .15s;" onfocusin="this.style.borderColor='var(--accent)'" onfocusout="this.style.borderColor='var(--line)'">
        <input id="inp-key" type="text" name="merchant_key" value="{$curKey}"
          style="flex:1;border:0;outline:0;padding:.9rem 1rem;font-family:inherit;font-size:.95rem;font-weight:500;color:var(--ink);background:transparent;"
          placeholder="Your merchant key" />
      </div>
      <label style="display:block;font-size:.82rem;font-weight:600;color:var(--muted);letter-spacing:.04em;text-transform:uppercase;margin-bottom:.45rem;" for="inp-secret">Merchant Salt</label>
      <div style="display:flex;align-items:center;border:1.5px solid var(--line);border-radius:12px;overflow:hidden;background:#fff;margin-bottom:1.4rem;transition:border-color .15s;" onfocusin="this.style.borderColor='var(--accent)'" onfocusout="this.style.borderColor='var(--line)'">
        <input id="inp-secret" type="password" name="merchant_secret" value="" required
          style="flex:1;border:0;outline:0;padding:.9rem 1rem;font-family:inherit;font-size:.95rem;font-weight:500;color:var(--ink);background:transparent;"
          placeholder="{$saltPlaceholder}" />
        <button type="button" onclick="toggleSecret()"
          style="background:none;border:0;cursor:pointer;padding:.9rem 1rem;color:var(--muted);font-size:.82rem;font-weight:600;white-space:nowrap;">Show</button>
      </div>
    </div>

    <!-- ── Amount ── -->
    <label style="display:block;font-size:.82rem;font-weight:600;color:var(--muted);letter-spacing:.04em;text-transform:uppercase;margin-bottom:.45rem;" for="amt-input">Amount (INR)</label>
    <div style="display:flex;align-items:center;border:1.5px solid var(--line);border-radius:12px;overflow:hidden;background:#fff;margin-bottom:1.4rem;transition:border-color .15s ease;" onfocusin="this.style.borderColor='var(--accent)'" onfocusout="this.style.borderColor='var(--line)'">
      <span style="padding:.9rem 0 .9rem 1rem;font-weight:650;color:var(--muted);font-size:1.05rem;user-select:none;">₹</span>
      <input id="amt-input" type="number" name="amount" min="1" step="0.01" value="{$defaultAmt}" required
        style="flex:1;border:0;outline:0;padding:.9rem .9rem .9rem .45rem;font-family:inherit;font-size:1.05rem;font-weight:650;color:var(--ink);background:transparent;font-variant-numeric:tabular-nums;"
        placeholder="100.00" />
    </div>

    <button type="submit" class="btn btn-primary">
      Continue to payment
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
  </form>
</div>
<script>
function onEnvChange() {
  var isUat = document.getElementById('env-uat').checked;
  // Show/hide merchant-type row (only relevant for UAT)
  document.getElementById('merchant-type-row').style.display = isUat ? 'block' : 'none';
  // Production always means own credentials
  if (!isUat) {
    document.getElementById('mode-own').checked = true;
    showCredFields(true);
  } else {
    onModeChange();
  }
  // Env toggle highlight
  var lU = document.getElementById('lbl-uat'), lP = document.getElementById('lbl-prod');
  lU.style.borderColor = isUat  ? 'var(--accent)' : 'var(--line)';
  lU.style.background  = isUat  ? 'var(--accent-soft)' : '#fff';
  lP.style.borderColor = !isUat ? 'var(--accent)' : 'var(--line)';
  lP.style.background  = !isUat ? 'var(--accent-soft)' : '#fff';
  // Top-bar badge — text + colour
  var chip = document.querySelector('.env-chip');
  if (chip) {
    chip.textContent = isUat ? 'UAT' : 'PRODUCTION';
    chip.classList.toggle('env-chip--uat',  isUat);
    chip.classList.toggle('env-chip--prod', !isUat);
  }
}
function onModeChange() {
  var isOwn = document.getElementById('mode-own').checked;
  showCredFields(isOwn);
  // Hide the "key & salt required" error when switching back to Default mode
  var errEl = document.getElementById('cred-err');
  if (errEl) errEl.style.display = isOwn ? '' : 'none';
  // Update salt placeholder
  var secretInp = document.getElementById('inp-secret');
  if (secretInp) {
    var isUat  = document.getElementById('env-uat').checked;
    var isProd = !isUat;
    secretInp.placeholder = isProd ? 'Enter your production salt' : 'Enter your merchant salt';
  }
  // Mode toggle highlight
  var lD = document.getElementById('lbl-default'), lO = document.getElementById('lbl-own');
  lD.style.borderColor = !isOwn ? 'var(--accent)' : 'var(--line)';
  lD.style.background  = !isOwn ? 'var(--accent-soft)' : '#fff';
  lO.style.borderColor = isOwn  ? 'var(--accent)' : 'var(--line)';
  lO.style.background  = isOwn  ? 'var(--accent-soft)' : '#fff';
}
function showCredFields(show) {
  var creds = document.getElementById('cred-fields');
  creds.style.display = show ? 'block' : 'none';
  // Add/remove required so the form doesn't block submission when fields are hidden,
  // and enforces both key AND salt when they are visible.
  var keyInp    = document.getElementById('inp-key');
  var secretInp = document.getElementById('inp-secret');
  if (keyInp)    keyInp.required    = show;
  if (secretInp) secretInp.required = show;
}
function toggleSecret() {
  var inp = document.getElementById('inp-secret');
  var btn = inp.nextElementSibling;
  if (inp.type === 'password') { inp.type = 'text'; btn.textContent = 'Hide'; }
  else { inp.type = 'password'; btn.textContent = 'Show'; }
}
// Init highlights on page load
onEnvChange();
onModeChange();
</script>
HTML;

    header('Content-Type: text/html; charset=UTF-8');
    echo layout('Checkout · PayU Elements', $combinedFormHtml, '', [
        'badge'    => $envBadge,
        'subtitle' => 'Secure card checkout',
    ]);
    exit;
}
// ── End combined form gate ───────────────────────────────────────────────────

$errorHtml = '';
$encryptedOrderId = '';
$merchantOrderId = '';
$amount = '';
$product = '';
$debugJson = '';

try {
    $created = (new PayUCheckoutElements($config))->createOrder([
        'order' => ['amount' => $requestedAmount],
    ]);
    $encryptedOrderId = (string) $created['response']['transaction']['encryptedOrderId'];
    $merchantOrderId = (string) (
        $created['response']['transaction']['orderid']
        ?? $created['request']['orderId']
    );
    // Store the salt used for this order so the callback page can verify the hash
    // against the correct salt even if own-mode credentials were already cleared.
    if ($merchantOrderId !== '') {
        $_SESSION['payu_salt_' . $merchantOrderId] = $config['merchant_salt'] ?? $config['merchant_secret'] ?? '';
    }
    $amount = (string) ($created['response']['order']['amount'] ?? $created['request']['order']['amount']);
    $product = (string) ($created['request']['order']['productinfo'] ?? $created['request']['productinfo'] ?? '');
    $token = (string) ($created['response']['transaction']['accessToken'] ?? '');
    $debugJson = e(json_encode([
        'request' => $created['request'],
        'transaction' => [
            'orderid' => $merchantOrderId,
            'encryptedOrderId' => $encryptedOrderId,
            'accessToken' => $token !== '' ? substr($token, 0, 8) . '…' : '',
        ],
        'Date' => $created['headers']['Date'] ?? '',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
} catch (Throwable $e) {
    $errorHtml = '<div class="alert alert-error is-visible"><strong>Unable to create order</strong><br>'
        . e($e->getMessage()) . '</div>
        <div class="btn-row"><a class="btn btn-ghost" href="index.php">Try again</a></div>';
}

$orderIdJs = $encryptedOrderId !== ''
    ? json_encode($encryptedOrderId, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
    : 'null';

$amountPretty = e(format_inr($amount !== '' ? $amount : 0));
$productEsc = e($product);
$orderEsc = e($merchantOrderId);
$encEsc = e($encryptedOrderId);
$encShort = e($encryptedOrderId !== '' ? substr($encryptedOrderId, 0, 14) . '…' : '—');

if ($errorHtml !== '') {
    $body = <<<HTML
<div class="result-page panel panel-pad">
  {$errorHtml}
</div>
HTML;
} else {
    $body = <<<HTML
<div class="checkout-grid">
  <aside class="panel summary panel-pad">
    <p class="eyebrow">Order summary</p>
    <h1>Complete your payment</h1>
    <p class="lede">Pay securely with your debit or credit card.</p>

    <div class="amount-hero">
      <div class="label">Amount due</div>
      <div class="value">{$amountPretty}</div>
      <div class="product">{$productEsc}</div>
    </div>

    <ul class="meta-list">
      <li><span>Order ID</span><strong>{$orderEsc}</strong></li>
      <li><span>Environment</span><strong>{$envBadge}</strong></li>
      <li><span>Encrypted ID</span><strong>{$encShort}</strong></li>
    </ul>

    <div class="trust">
      <span>PCI iframe</span>
      <span>HMAC signed</span>
      <span>Elements JS SDK</span>
    </div>

    <a href="index.php" class="btn btn-ghost" style="margin-top:1.25rem;width:100%;justify-content:center;border-color:rgba(255,255,255,.22);color:#f4fffb;background:rgba(255,255,255,.1);">
      ← New order
    </a>
  </aside>

  <section class="panel pay-panel panel-pad">
    <ul class="steps" aria-hidden="true">
      <li class="done" id="step-1"></li>
      <li class="active" id="step-2"></li>
      <li id="step-3"></li>
    </ul>

    <h2 class="section-title">Card details</h2>
    <p class="section-sub">Card data is captured in PayU’s iframe.</p>

    <div id="payu-card-element-container"></div>
    <div id="bin-chip" class="bin-chip" aria-live="polite"></div>

    <div id="status" class="status-line" role="status"></div>
    <div id="error" class="alert alert-error" role="alert"></div>
    <div id="response-panel" class="response-panel" hidden>
      <div class="response-panel-head">
        <strong id="response-kind">processPayment response</strong>
        <span id="response-hint" class="response-hint"></span>
      </div>
      <pre id="response-json"></pre>
    </div>
    <div id="fallback" class="fallback-box" role="alert">
      Card payment is temporarily unavailable.
      <div class="btn-row">
        <a class="btn btn-ghost" href="index.php">Retry</a>
      </div>
    </div>

    <button type="button" id="pay-now-btn" class="btn btn-primary" disabled>
      Pay {$amountPretty}
    </button>
    <a href="index.php" class="btn btn-ghost" style="width:100%;margin-top:.65rem;justify-content:center;">
      ← New order
    </a>
  </section>
</div>

<div class="debug">
  <details>
    <summary>Developer details</summary>
    <div class="debug-body">
      <table class="kv">
        <tr><th>Create Order</th><td><code>{$apiUrl}/v1/checkout/l1</code></td></tr>
        <tr><th>Elements SDK</th><td><code>{$sdkUrl}</code></td></tr>
        <tr><th>encryptedOrderId</th><td><code>{$encEsc}</code></td></tr>
        <tr><th>Guides</th><td><code>docs/PayU-Checkout-JS-SDK-Integration-Guide.md</code><br><code>docs/Checkout-Elements-Configurable-Fields.md</code></td></tr>
      </table>
      <pre>{$debugJson}</pre>
    </div>
  </details>
</div>

<script src="{$sdkUrl}"></script>
<script>
(function () {
  // Integration guide §3 / §7 — shared baseline style + layout (Configurable Fields §3–§4).
  // SDK does not inject a theme; we pass the full style object on create().
  const DEFAULT_STYLE = {
    width: '100%',
    minHeight: '320px',
    maxWidth: '100%',
    containerClassName: 'payu-card-form',
    inputStyle: {
      borderWidth: 1,
      borderColor: '#d7e3de',
      borderColorFocus: '#0b7a66',
      borderColorError: '#b42318',
      borderRadius: 12,
      backgroundColor: '#ffffff',
      textColor: '#10231f',
      placeholderColor: '#8aa39c',
      labelColor: '#5c736d',
      labelFontSize: 13,
      labelFontWeight: 500,
      fontSize: 16,
      fontWeight: 500,
      height: 56,
      paddingLeft: 14,
      paddingRight: 14,
      paddingTop: 12,
      paddingBottom: 12,
      errorColor: '#b42318',
      errorFontSize: 12,
      errorFontWeight: 400,
      errorMarginTop: 4,
    },
    containerStyle: {
      fieldGap: 16,
      rowGap: 16,
      formPadding: 0,
      formPreviewGap: 24,
      display: 'flex',
      flexDirection: 'row',
      alignItems: 'flex-start',
    },
    cardPreview: {
      width: 248,
      height: 152,
      showOnMobile: false,
      style: {
        background: 'linear-gradient(145deg, #1a3a34 0%, #0b7a66 55%, #10957d 100%)',
        borderRadius: 14,
        borderWidth: 0,
        borderColor: 'transparent',
        boxShadow: '',
        padding: 14,
        showBackgroundPattern: true,
        patternColor: 'rgba(255,255,255,0.08)',
        cardNumberColor: '#FFFFFF',
        cardNumberFontSize: 13,
        cardNumberFontWeight: 600,
        cardNumberFontStyle: 'normal',
        cardNumberLetterSpacing: '0.04em',
        numberBandBackground: 'rgba(2,2,3,0.14)',
        numberBandBorderRadius: 6,
        numberBandPaddingV: 8,
        numberBandPaddingH: 10,
        cardHolderColor: '#FFFFFF',
        cardHolderFontSize: 11,
        cardHolderFontWeight: 600,
        expiryColor: '#FFFFFF',
        expiryFontSize: 11,
        expiryFontWeight: 600,
        labelColor: 'rgba(255,255,255,0.65)',
        labelFontSize: 7,
        labelFontWeight: 400,
        labelLetterSpacing: '0.06em',
        labelTextTransform: 'uppercase',
        logoWidth: 32,
        logoHeight: 20,
        cardHolderLabelText: 'Card Holder Name',
        expiryLabelText: 'Valid Thru',
        cardNumberPlaceholder: '0000 0000 0000 0000',
        cardHolderPlaceholder: 'Name',
        expiryPlaceholder: 'MM/YY',
      },
    },
  };

  // Configurable Fields §4 — preview slot after field rows → preview on the right (desktop).
  const DEFAULT_LAYOUT = [
    { id: 'cardNumberRow', fields: ['cardNumber'], className: 'w-full', gap: 16 },
    { id: 'nameRow', fields: ['nameOnCard'], className: 'w-full', gap: 16 },
    {
      id: 'expiryCvvRow',
      fields: ['expiry', 'cvv'],
      className: 'w-full flex-nowrap',
      gap: 16,
      fieldWidths: {
        expiry: 'flex-[1] min-w-[130px]',
        cvv: 'flex-[1] min-w-[110px]',
      },
    },
    { id: 'cardPreviewSlot', fields: ['cardPreview'] },
  ];

  const orderId = {$orderIdJs};
  const payBtn = document.getElementById('pay-now-btn');
  const statusEl = document.getElementById('status');
  const errorEl = document.getElementById('error');
  const fallbackEl = document.getElementById('fallback');
  const binChip = document.getElementById('bin-chip');
  const responsePanel = document.getElementById('response-panel');
  const responseKind = document.getElementById('response-kind');
  const responseHint = document.getElementById('response-hint');
  const responseJson = document.getElementById('response-json');
  const step2 = document.getElementById('step-2');
  const step3 = document.getElementById('step-3');

  function pick(obj, path) {
    return path.split('.').reduce((o, k) => (o && o[k] != null ? o[k] : null), obj);
  }

  function classifyPayload(payload) {
    const root = payload && typeof payload === 'object' ? payload : {};
    const meta = root.metaData || root.metadata || root;
    const bin = root.binData || {};
    const status = String(
      meta.txnStatus || meta.unmappedStatus || root.status || root.txnStatus || ''
    ).toLowerCase();
    const acs =
      pick(root, 'result.acsTemplate') ||
      pick(root, 'acsTemplate') ||
      pick(root, 'result.postToBank') ||
      pick(root, 'postToBank');
    const issuerUrl =
      pick(root, 'result.issuerUrl') ||
      pick(root, 'issuerUrl') ||
      pick(root, 'result.redirectUrl') ||
      pick(root, 'redirectUrl');
    const submitOtp = !!(meta.submitOtp || root.submitOtp);
    const pureS2S = !!(bin.pureS2SSupported || meta.pureS2SSupported || root.pureS2SSupported);
    const enrolled = status === 'enrolled' || status === 'pending' || status === 'in progress';
    const captured =
      status === 'captured' ||
      status === 'success' ||
      status === 'authenticated' ||
      root.status === 'success';

    if (acs || issuerUrl) {
      return {
        kind: 'ACS / bank redirect payload',
        hint: 'Bank 3DS HTML (acsTemplate) or issuerUrl present. In full Elements mode PayU posts this inside the iframe; merchant does not handle it.',
      };
    }
    if (enrolled && (submitOtp || pureS2S)) {
      return {
        kind: 'Native OTP / Pure S2S pending',
        hint: 'Auth not finished — typically submitOtp + Enrolled. In Elements, OTP UI should stay inside PayU iframe; merchant page only gets the final success/failure Promise.',
      };
    }
    if (captured) {
      return {
        kind: 'Final success / captured',
        hint: 'Promise resolved as payment_success. Common for UAT frictionless / auto-auth cards (e.g. 4111…) — no OTP step returned to merchant UI, so “Payment succeeded” stays on this page.',
      };
    }
    return {
      kind: 'Other / unknown shape',
      hint: 'Inspect JSON below for txnStatus, acsTemplate, submitOtp, binData.pureS2SSupported, merchantReturnUrl.',
    };
  }

  function printResponse(label, payload) {
    const info = classifyPayload(payload);
    responseKind.textContent = label + ' · ' + info.kind;
    responseHint.textContent = info.hint;
    try {
      responseJson.textContent = JSON.stringify(payload, null, 2);
    } catch (_) {
      responseJson.textContent = String(payload);
    }
    responsePanel.hidden = false;
    console.log(label, payload);
  }

  function showStatus(msg) {
    statusEl.classList.remove('alert-ok');
    statusEl.classList.add('is-visible');
    statusEl.textContent = msg;
  }
  function showError(msg) {
    errorEl.classList.add('is-visible');
    errorEl.textContent = (msg || '').toString();
  }
  function clearError() {
    errorEl.classList.remove('is-visible');
    errorEl.textContent = '';
  }
  function setStep(n) {
    if (n >= 3) {
      step2.classList.remove('active');
      step2.classList.add('done');
      step3.classList.add('active');
    } else {
      step2.classList.add('active');
      step2.classList.remove('done');
      step3.classList.remove('active');
    }
  }
  function showFallback() {
    fallbackEl.classList.add('is-visible');
    payBtn.disabled = true;
  }

  // Integration guide §15 — also dump raw payload so we can see ACS vs native OTP vs captured
  function handleSuccess(result) {
    setStep(3);
    printResponse('processPayment resolved (payment_success)', result);
    const info = classifyPayload(result);
    if (info.kind.indexOf('Native OTP') === 0) {
      showStatus('Promise resolved but payload looks OTP-pending — check JSON (Elements normally keeps OTP inside iframe).');
    } else if (info.kind.indexOf('ACS') === 0) {
      showStatus('Promise resolved with ACS fields — in Elements, PayU should handle redirect in iframe.');
    } else {
      showStatus('Payment succeeded (same page — Promise resolved; no merchant redirect required).');
      statusEl.classList.add('alert-ok');
    }
  }
  function handleError(error) {
    const payload = error && typeof error === 'object' ? error : { message: String(error) };
    printResponse('processPayment rejected (payment_failure)', payload);
    const msg = error && (error.message || error.code)
      ? ((error.code ? error.code + ': ' : '') + (error.message || JSON.stringify(error)))
      : String(error);
    showError(msg);
    payBtn.disabled = false;
    setStep(2);
  }

  if (!orderId) {
    showFallback();
    return;
  }
  if (typeof PayuCheckout === 'undefined') {
    showError('PayU Elements SDK failed to load.');
    showFallback();
    return;
  }

  try {
    // §5 init — orderId = transaction.encryptedOrderId from Create Order
    PayuCheckout.init({ orderId: orderId });

    // §6 create + §7 style/layout
    const cardElement = PayuCheckout.create('cardForm', {
      style: DEFAULT_STYLE,
      layout: DEFAULT_LAYOUT,
    });

    // §8 mount
    cardElement.mount('#payu-card-element-container');

    // Center the network/brand logo inside the card preview.
    // The SDK renders the preview into the host DOM; we watch for it and
    // apply centering regardless of the exact class name used.
    (function centerCardLogo() {
      const container = document.getElementById('payu-card-element-container');
      if (!container) return;

      function applyLogoCenter(root) {
        // Cast a wide net: any element whose class contains "logo", "network",
        // "brand", or "scheme" that lives inside the preview area.
        const candidates = root.querySelectorAll(
          '[class*="logo"],[class*="network"],[class*="brand"],[class*="scheme"],[class*="card-type"],[class*="cardType"]'
        );
        candidates.forEach(function (el) {
          el.style.setProperty('position', 'absolute', 'important');
          el.style.setProperty('left', '50%', 'important');
          el.style.setProperty('right', 'auto', 'important');
          el.style.setProperty('transform', 'translateX(-50%)', 'important');
          el.style.setProperty('margin', '0 auto', 'important');
          el.style.setProperty('text-align', 'center', 'important');
        });
      }

      // Run once for anything already in the DOM, then watch for SDK inserts.
      applyLogoCenter(container);
      const obs = new MutationObserver(function () { applyLogoCenter(container); });
      obs.observe(container, { childList: true, subtree: true, attributes: true });
    })();

    // §9 element events
    cardElement.on('ready', () => {
      showStatus('Card form ready — enter details to continue.');
    });

    cardElement.on('formValid', (data) => {
      payBtn.disabled = !(data && data.isValid);
    });

    // §10 BIN
    cardElement.on('binIdentified', (data) => {
      if (!data) return;
      const parts = [
        data.cardType,
        data.issuer,
        data.isCardDomestic === true ? 'Domestic' : (data.isCardDomestic === false ? 'International' : null),
      ].filter(Boolean);
      if (!parts.length) return;
      binChip.classList.add('is-visible');
      binChip.innerHTML = 'Detected: <strong>' + parts.map((p) => String(p)).join(' · ') + '</strong>';
    });

    // §9 SDK events — subscribe once
    PayuCheckout.on('error', (err) => {
      const code = err && err.code ? String(err.code) : '';
      const message = err && err.message ? String(err.message) : 'SDK error';
      showError((code ? code + ': ' : '') + message);
      if (code === 'MOUNT_ERROR' || code === 'INIT_ERROR') {
        showFallback();
      }
    });

    PayuCheckout.on('showLoader', (data) => {
      if (data && data.visible) {
        showStatus('Processing…');
      }
    });

    // §12 processPayment — Promise resolves on payment_success, rejects on payment_failure
    payBtn.addEventListener('click', async () => {
      payBtn.disabled = true;
      clearError();
      responsePanel.hidden = true;
      responseJson.textContent = '';
      showStatus('Processing payment…');
      setStep(3);
      try {
        const result = await PayuCheckout.processPayment();
        handleSuccess(result);
      } catch (error) {
        handleError(error);
      }
    });
  } catch (e) {
    handleError(e);
    showFallback();
  }
})();
</script>
HTML;
}

header('Content-Type: text/html; charset=UTF-8');
echo layout('Checkout · PayU Elements', $body, '', [
    'badge'    => $envBadge,
    'subtitle' => 'Secure card checkout',
]);
