# PayU Checkout Elements — JS SDK integration guide

| | |
| --- | --- |
| **Space** | PayU Checkout · Elements JS SDK |
| **Version** | 1.0.0 |
| **Last updated** | March 2026 |
| **Status** | Current |

---

## Summary

This guide explains how to embed PayU **card / EMI / UATP** checkout UI on your site using the **Elements JS SDK**. The SDK loads your order into an **iframe** on the PayU checkout domain and uses **`postMessage`** for commands and events.

> **Styling:** There is **no** separate “theme” switch in the SDK. You pass a **`style`** object (and optional **`layout`**) when creating the element, or rely on defaults applied **inside the checkout application** after it merges your payload. The authoritative list of keys is **[Checkout-Elements-Configurable-Fields.md](./Checkout-Elements-Configurable-Fields.md)**.

---

## Documentation map (Confluence-style hierarchy)

| Page | Use when you need… |
| --- | --- |
| **This guide** | End-to-end integration, API usage, events, flows |
| [**Configurable fields**](./Checkout-Elements-Configurable-Fields.md) | Every `style` / `layout` / `init` / `processPayment` field (**source of truth**) |
| [**Architecture overview**](./SDK-Architecture-Overview.md) | Merchant vs iframe vs bridge, security notes, sequence |
| [**Mind map**](./SDK-MindMap.md) | One-page structural reference |
| [**Requirements status notes**](./SDK-Requirements-Status-Notes.md) | JIRA-aligned status (white-label, offers UI, 3DS, Phase 1 scope, DX, fallback & branding checklists) |
| [**JIRA requirements × status**](./SDK-JIRA-Card-Element-Requirements-Status.md) | Epic mapped to M1 / M2 / Later / Not planned / Partial / Other |
| `Checkout Elements - JS SDK Overview.png` | Visual overview (if diagram text conflicts with these pages, **trust the markdown**) |

---

## Table of contents

1. [Overview](#1-overview)  
2. [Architecture](#2-architecture)  
3. [Quick start](#3-quick-start)  
4. [Include the SDK](#4-include-the-sdk)  
5. [SDK initialization](#5-sdk-initialization)  
6. [Create payment elements](#6-create-payment-elements)  
7. [Styling and layout](#7-styling-and-layout)  
8. [Mount and unmount](#8-mount-and-unmount)  
9. [Event handling](#9-event-handling)  
10. [Card form validation and BIN](#10-card-form-validation-and-bin)  
11. [Offers](#11-offers)  
12. [Process payment](#12-process-payment)  
13. [Element methods](#13-element-methods)  
14. [SDK lifecycle and SPAs](#14-sdk-lifecycle-and-spas)  
15. [Error handling](#15-error-handling)  
16. [Best practices](#16-best-practices)  
17. [API quick reference](#17-api-quick-reference)

---

## 1. Overview

The SDK provides:

- **PCI-friendly** card capture inside an isolated iframe  
- **Real-time** validation and **`formValid`** updates  
- **BIN identification** (`binIdentified`) for network / issuer / offer hints  
- **Offers** via `applyOffer` / `removeOffer` and **`offerValidated`**  
- **Payment** via `processPayment()` (Promise resolves on success, rejects on failure)

---

## 2. Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│ Merchant page                                                    │
│  PayuCheckout.init / create / processPayment / on('error', …)   │
│  PaymentElement.mount → <iframe src="…/checkout/{orderId}/…">   │
│         ▲                              │                         │
│         │     postMessage (payu-sdk)   ▼                         │
│         └────────────── Checkout app in iframe                   │
└─────────────────────────────────────────────────────────────────┘
```

For a fuller breakdown (commands vs events, URL pattern, security), see [**SDK-Architecture-Overview.md**](./SDK-Architecture-Overview.md).

---

## 3. Quick start

```javascript
PayuCheckout.init({ orderId: "ORDER_123456789" });

const cardElement = PayuCheckout.create("cardForm", {
  style: { width: "100%", minHeight: "280px" },
});

cardElement.mount("#payu-card-element-container");

cardElement.on("formValid", (data) => {
  document.getElementById("pay-now-btn").disabled = !data.isValid;
});

document.getElementById("pay-now-btn").onclick = async () => {
  try {
    const result = await PayuCheckout.processPayment();
    console.log("Success:", result);
  } catch (error) {
    console.error("Failed:", error);
  }
};
```

---

## 4. Include the SDK

**Module bundler (recommended)**

```javascript
import PayuCheckout from "./path/to/payu-checkout.js";
```

**Classic script (if exposed on `window`)**

```html
<script type="module" src="payu-checkout.js"></script>
```

The module attaches **`window.PayuCheckout`** when `window` is defined.

---

## 5. SDK initialization

### `PayuCheckout.init(config)`

| Parameter | Type | Required | Description |
| --- | --- | --- | --- |
| `orderId` | `string` | Yes | Order id from your backend |

```javascript
PayuCheckout.init({ orderId: "ORDER_123456789" });
```

```javascript
if (PayuCheckout.initialized) {
  console.log("SDK ready");
}
```

---

## 6. Create payment elements

### `PayuCheckout.create(elementType, options)`

Returns a **`PaymentElement`**.

| Option | Type | Required | Description |
| --- | --- | --- | --- |
| `style` | `object` | No | Appearance / sizing — see [Configurable fields](./Checkout-Elements-Configurable-Fields.md) |
| `offerKeys` | `string[]` | No | Pre-applied offer keys |
| `bankCode` | `string` | For **`emiCardForm`** | Bank code (e.g. `HDFCCC03`) |
| `layout` | `array` | No | Row layout for card / EMI forms |

### Element types

| `elementType` | Description |
| --- | --- |
| `cardForm` | Card number, name, expiry, CVV, preview |
| `emiCardForm` | EMI flow (tenure, etc.) |
| `UATP` | UATP airline card |

### Examples

```javascript
const card = PayuCheckout.create("cardForm", {
  offerKeys: ["HDFC_10_PERCENT"],
  style: { width: "100%", minHeight: "280px" },
});
```

```javascript
const emi = PayuCheckout.create("emiCardForm", {
  bankCode: "HDFCCC03",
  offerKeys: ["EMI_NO_COST_OFFER"],
  style: { minHeight: "320px" },
});
```

---

## 7. Styling and layout

- Pass **`options.style`** with any subset of fields documented in [**Checkout-Elements-Configurable-Fields.md**](./Checkout-Elements-Configurable-Fields.md) (`width`, `minHeight`, `maxWidth`, `inputStyle`, `cardPreview`, `containerStyle`, `containerClassName`).  
- Pass **`options.layout`** to control rows and field ids (`cardNumber`, `nameOnCard`, `expiry`, `cvv`, `storeCard`).  
- At runtime, use **`element.updateStyle(partial)`** to merge updates and push them to the iframe.

**Default sample object:** Your integration can keep a shared `DEFAULT_STYLE` constant (as in sample merchant pages) so the checkout app receives a full baseline; the SDK itself does not inject that baseline.

---

## 8. Mount and unmount

### `element.mount(selector)`

- Accepts a CSS selector **or** a DOM element.  
- **Singleton:** already mounted → second `mount` is ignored until **`unmount()`**.  
- Clears the container, injects the iframe, shows loader until ready.

| Behaviour | Detail |
| --- | --- |
| Container | Must exist; otherwise **`MOUNT_ERROR`** on SDK `error` |
| Loader | `showLoader` events from SDK |
| Ready | `ready` on the element when iframe is usable |

```javascript
cardElement.mount("#payu-card-element-container");
cardElement.on("ready", () => console.log("Ready"));
```

### `element.unmount()`

Removes the iframe and listeners; allows **`mount`** again.

### State

| Property | Meaning |
| --- | --- |
| `mounted` | Iframe is in the DOM |
| `ready` | Load / handshake complete |

---

## 9. Event handling

Subscribe with **`element.on(event, handler)`** or **`PayuCheckout.on(event, handler)`**. Unsubscribe with **`off`**.

### Element-level events

| Event | Description | Payload (typical) |
| --- | --- | --- |
| `ready` | Element / iframe ready | `{}` |
| `formValid` | Validation aggregate changed | `{ isValid: boolean }` |
| `change` | Field value changed | `{ field, value, complete }` |
| `focus` / `blur` | Field focus | `{ field }` |
| `escape` | Escape key | — |
| `binIdentified` | BIN / card context | `{ cardType, issuer, isCardDomestic, availableOfferKeys, … }` |

### SDK-level events

| Event | Description | Payload (typical) |
| --- | --- | --- |
| `offerValidated` | Offer validation result | `{ offerKeys, isValid, availableOfferKeys }` |
| `showLoader` | Loader visibility | `{ visible: boolean }` |
| `error` | SDK / mount / init errors | `{ message, code, … }` |

### Payment result (internal + your `processPayment` Promise)

The iframe emits **`payment_success`** and **`payment_failure`**. The SDK listens on the internal bus and **resolves or rejects** **`PayuCheckout.processPayment()`** — you do not need to `on('payment_success')` unless you want parallel side effects.

---

## 10. Card form validation and BIN

```javascript
cardElement.on("formValid", (data) => {
  payBtn.disabled = !data.isValid;
});

cardElement.on("binIdentified", (data) => {
  console.log(data.cardType, data.issuer, data.availableOfferKeys);
});
```

---

## 11. Offers

```javascript
PayuCheckout.applyOffer(["HDFC_OFFER_KEY", "CASHBACK_OFFER"]);
PayuCheckout.removeOffer();

PayuCheckout.on("offerValidated", (data) => {
  if (data.isValid) {
    /* … */
  }
});
```

`applyOffer` / `removeOffer` broadcast to **mounted** elements only.

---

## 12. Process payment

```javascript
try {
  const result = await PayuCheckout.processPayment();
} catch (error) {
  /* failure payload from iframe / gateway */
}
```

### Billing (example)

```javascript
await PayuCheckout.processPayment({
  additionalInformation: {
    billingInformation: {
      addressLine1: "123 Main Street",
      addressLine2: "Apt 4B",
      city: "Mumbai",
      state: "Maharashtra",
      country: "India",
      postalCode: "400001",
    },
  },
});
```

`offerKeys` and `bankCode` belong in **`create()`** (and `applyOffer`), not in a non-standard `processPayment({ offers: … })` shape.

---

## 13. Element methods

| Method | Description |
| --- | --- |
| `mount(selector)` | Attach iframe |
| `unmount()` | Remove iframe |
| `clear()` | Clear fields |
| `focus()` / `blur()` | Focus control |
| `updateStyle(style)` | Partial style merge + post to iframe |
| `on` / `off` | Event subscription |

---

## 14. SDK lifecycle and SPAs

### `PayuCheckout.destroy()`

Unmounts all elements, clears listeners and config, resets **`initialized`**.

### React example

```javascript
useEffect(() => {
  PayuCheckout.init({ orderId });
  const el = PayuCheckout.create("cardForm", { style: { minHeight: "280px" } });
  el.mount("#payu-card-element-container");
  return () => PayuCheckout.destroy();
}, [orderId]);
```

---

## 15. Error handling

```javascript
PayuCheckout.on("error", (error) => {
  console.error(error.code, error.message);
});
```

| Code | When |
| --- | --- |
| `INIT_ERROR` | Missing `orderId`, `create` before `init`, bad element type |
| `MOUNT_ERROR` | Container not found |
| `PAYMENT_ERROR` | e.g. no mounted element for `processPayment` |
| `ALREADY_MOUNTED` | Second `mount` without `unmount` |
| `NETWORK_ERROR` / `TIMEOUT_ERROR` / `VALIDATION_ERROR` / `OFFER_ERROR` | As returned by checkout / gateway |

```javascript
async function pay() {
  try {
    const result = await PayuCheckout.processPayment();
    handleSuccess(result);
  } catch (error) {
    handleError(error);
  }
}
```

---

## 16. Best practices

1. Always **`init`** before **`create`**.  
2. Keep **Pay disabled** until **`formValid`** reports `isValid`.  
3. Call **`destroy()`** on SPA route change or before **`init`** with a new order.  
4. Subscribe to **`error`** once globally.  
5. Prefer **`updateStyle`** for responsive tweaks instead of remounting.

---

## 17. API quick reference

### `PayuCheckout`

| Member | Description |
| --- | --- |
| `initialized` | Boolean |
| `init(config)` | Set `orderId` |
| `create(type, options)` | New `PaymentElement` |
| `processPayment(opts?)` | Promise → success / failure |
| `applyOffer(keys)` / `removeOffer()` | Offers |
| `on` / `off` | SDK events |
| `destroy()` | Full teardown |

### `PaymentElement`

| Member | Description |
| --- | --- |
| `mounted`, `ready` | State |
| `mount` / `unmount` | DOM |
| `clear`, `focus`, `blur` | UX |
| `updateStyle` | Style merge |
| `on` / `off` | Element events |

---

*PayU Checkout Elements JS SDK · Documentation set: Integration guide + Configurable fields + Architecture overview*
