# PayU Checkout Elements (PHP · UAT)

Standalone PHP demo for PayU **Checkout Elements** (card form in a PayU iframe).

## Guides used

| Doc | Role |
| --- | --- |
| [docs/PayU-Checkout-JS-SDK-Integration-Guide.md](docs/PayU-Checkout-JS-SDK-Integration-Guide.md) | End-to-end SDK API: `init` → `create` → `mount` → events → `processPayment` |
| [docs/Checkout-Elements-Configurable-Fields.md](docs/Checkout-Elements-Configurable-Fields.md) | Source of truth for `style` / `layout` / `processPayment` options |

Create Order (server HMAC) is required so the page can pass `transaction.encryptedOrderId` into `PayuCheckout.init({ orderId })`.

## Requirements

- PHP 8.1+
- Extensions: `curl`, `json`, `openssl` (hash)

## Quick start

```bash
cd payu-checkout-elements-php
export PAYU_MERCHANT_KEY=your_key
export PAYU_MERCHANT_SECRET=your_secret
php -S localhost:8090 router.php
```

Open http://localhost:8090

## Flow

1. **Backend** — `POST /v1/checkout/l1` with HMAC-SHA512; keep `merchantSecret` server-side  
2. Pass **`transaction.encryptedOrderId`** to the browser as `orderId`  
3. **Frontend** (per JS SDK guide):
   - `PayuCheckout.init({ orderId })`
   - `PayuCheckout.create('cardForm', { style, layout })`
   - `element.mount('#payu-card-element-container')`
   - Enable Pay on `formValid`
   - `await PayuCheckout.processPayment()` → resolve = success, reject = failure  
4. Optional: listen to `binIdentified`, `showLoader`, `error`  
5. Customer may also land on `successUrl` / `failureUrl` / `cancelUrl` from Create Order after bank auth

Merchant does **not** implement Submit OTP or `acsTemplate` handling for Elements.

## Style & layout

`index.php` uses a `DEFAULT_STYLE` + `DEFAULT_LAYOUT` constant (guide recommendation). Keys match **Configurable Fields** (`inputStyle`, `cardPreview`, `containerStyle`, layout slots). Preview placement: `cardPreview` slot row **after** field rows → right on desktop.

## Environments

| Setting | UAT | Production |
|---------|-----|------------|
| API | `https://apitest.payu.in` | `https://api.payu.in` |
| SDK | `https://jssdk-uat.payu.in/checkout/payu-checkout-elements.umd.js` | `https://jssdk.payu.in/checkout/payu-checkout-elements.umd.js` |

## Project layout

```
payu-checkout-elements-php/
├── config.php
├── index.php
├── success.php / failure.php / cancel.php
├── router.php
├── docs/
│   ├── PayU-Checkout-JS-SDK-Integration-Guide.md
│   └── Checkout-Elements-Configurable-Fields.md
└── lib/
    ├── PayUCheckoutElements.php
    ├── helpers.php
    ├── PayUResponseHash.php
    └── render_callback.php
```
