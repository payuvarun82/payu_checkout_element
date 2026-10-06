# Checkout Elements — Configurable fields (source of truth)

| | |
| --- | --- |
| **Space** | PayU Checkout · Elements JS SDK |
| **Audience** | Integrators, frontend engineers |
| **Status** | Current |

---

## Summary

This page lists **every field** you can set when integrating the PayU Elements JS SDK. The SDK **does not** apply built-in style presets: you pass a `style` object from your app (or rely on defaults merged inside the **checkout application** in the iframe). Styling is controlled explicitly via `inputStyle`, `cardPreview`, `containerStyle`, and layout/sizing fields—not via named “themes.”

**Related:** [Integration guide](./PayU-Checkout-JS-SDK-Integration-Guide.md) · [Architecture overview](./SDK-Architecture-Overview.md)

---

## 1. SDK initialization

### `PayuCheckout.init(config)`

| Field | Type | Required | Description |
| --- | --- | --- | --- |
| `orderId` | `string` | Yes | Unique order identifier from your backend |

---

## 2. Element creation options

### `PayuCheckout.create(elementType, options)`

| Field | Type | Required | Element types | Description |
| --- | --- | --- | --- | --- |
| `style` | `object` | No | All | Visual/layout configuration (see §3) |
| `offerKeys` | `string[]` | No | All | Offer keys to pre-apply |
| `bankCode` | `string` | For EMI | `emiCardForm` | Bank code (e.g. `'HDFCCC03'`) |
| `layout` | `array` | No | `cardForm`, `emiCardForm` | Row layout (see §4) |

### Element types

| `elementType` | Checkout route (suffix) | Description |
| --- | --- | --- |
| `cardForm` | `cards-form-elements` | Card number, name, expiry, CVV, preview |
| `emiCardForm` | `emiCardsForm` | Card form with EMI / tenure |
| `UATP` | `UATP` | UATP airline card form |

---

## 3. Style config (`options.style`)

Passed through the SDK to the checkout app via `postMessage` (`sdk:updateStyle`). The SDK does not merge a default style object; **your** object (or checkout-side defaults after merge) drives appearance.

### 3.1 Top-level

| Field | Type | Description |
| --- | --- | --- |
| `width` | `string` | Iframe / container width (SDK uses `?? '100%'` for iframe width if omitted) |
| `minHeight` | `string` | Minimum iframe height. The iframe's actual `height` is content-driven (the inner app reports its measured height to the SDK) — `minHeight` only acts as a floor and as the initial render height before the first measurement arrives |
| `maxWidth` | `string` | Maximum container width |
| `inputStyle` | `object` | Field-level styling (§3.2) |
| `cardPreview` | `object` | Preview chrome + inner `style` (§3.3) |
| `containerStyle` | `object` | Gaps, flex/grid hints (§3.4) |
| `containerClassName` | `string` | Optional class on form container (checkout app) |

### 3.2 `inputStyle`

| Field | Type | Typical default* | Description |
| --- | --- | --- | --- |
| `borderStyle` | `string` | — | CSS `border-style` |
| `borderWidth` | `number` | `1` | Border width (px) |
| `borderColor` | `string` | `#E5E7EB` | Default border |
| `borderColorFocus` | `string` | `#3B82F6` | Focus border |
| `borderColorError` | `string` | `#EF4444` | Error border |
| `borderRadius` | `number` | `8` | Radius (px) |
| `backgroundColor` | `string` | `#FFFFFF` | Input background |
| `textColor` | `string` | `#1F2937` | Input text |
| `placeholderColor` | `string` | `#9CA3AF` | Placeholder |
| `labelColor` | `string` | `#6B7280` | Label text |
| `labelFontSize` | `number` | `14` | Label (px) |
| `labelFontWeight` | `number` | `400` | Label weight |
| `fontSize` | `number` | `16` | Input (px) |
| `fontWeight` | `number` | `400` | Input weight |
| `height` | `number` | `60` | Input height (px) |
| `paddingLeft` / `paddingRight` | `number` | `16` | Horizontal padding |
| `paddingTop` / `paddingBottom` | `number` | `12` | Vertical padding |
| `errorColor` | `string` | `#EF4444` | Error text |
| `errorFontSize` | `number` | `12` | Error text (px) |
| `errorFontWeight` | `number` | `400` | Error weight |
| `errorFontStyle` | `string` | `normal` | Error style |
| `errorMarginTop` | `number` | `4` | Space above error |
| `errorBackground` | `string \| null` | `null` | Error background |
| `errorPadding` | `number` | `0` | Error padding |
| `errorBorderRadius` | `number` | `0` | Error radius |

\*Typical defaults apply in checkout when merging partial config—not in the JS SDK.

### 3.3 `cardPreview`

`cardPreview` is now **purely visual**. Whether the preview shows and where
it sits relative to the form is controlled by the `cardPreview` slot row in
`layout` (see §4). The `enabled` / `position` keys still work for legacy
integrations but are deprecated.

| Field | Type | Description |
| --- | --- | --- |
| `width` | `number` | Preview width (px) |
| `height` | `number` | Preview height (px) |
| `showOnMobile` | `boolean` | Show on small viewports |
| `style` | `object` | Visual details (typography, gradients, placeholders, labels, logo sizes, etc.) — see §7 |
| `enabled` *(deprecated)* | `boolean` | **Deprecated.** Use the presence of the `cardPreview` slot row in `layout` instead. When `false`, hides the preview regardless of layout. |
| `position` *(deprecated)* | `'right' \| 'top'` | **Deprecated.** Use the position of the `cardPreview` slot row in `layout`. Honoured only when no slot row is provided. |

**`cardPreview.style` (high level):** `background`, `borderRadius`, `borderWidth`, `borderColor`, `boxShadow`, `padding`, `showBackgroundPattern`, `patternColor`, number band + card number / holder / expiry colors and fonts, label styling, `logoWidth` / `logoHeight`, and copy fields such as `cardHolderLabelText`, `expiryLabelText`, `cardNumberPlaceholder`, `cardHolderPlaceholder`, `expiryPlaceholder`.

### 3.4 `containerStyle`

| Field | Type | Description |
| --- | --- | --- |
| `fieldGap` | `number` | Gap between fields (px) |
| `rowGap` | `number` | Gap between rows (px) |
| `formPreviewGap` | `number` | Gap between form and preview |
| `formPadding` | `number` | Form padding |
| `display` | `string` | e.g. `flex` |
| `flexDirection` | `string` | Flex axis |
| `justifyContent` | `string` | Main-axis alignment |
| `alignItems` | `string` | Cross-axis alignment |
| `flexWrap` | `string` | Wrap behaviour |
| `gridTemplateColumns` / `Rows` / `Areas` | `string` | Grid layout |

---

## 4. Layout config (`options.layout`)

Array of **row** objects.

| Field | Type | Required | Description |
| --- | --- | --- | --- |
| `id` | `string` | Yes | Row id |
| `fields` | `string[]` | Yes | Field ids in this row |
| `className` | `string` | No | Row CSS classes |
| `gap` | `number` | No | Gap between fields (px) |
| `wrap` | `boolean` | No | Allow wrap |
| `fieldWidths` | `object` | No | Map field id → flex/width class |

### Field ids

| Field | Id |
| --- | --- |
| Card number | `cardNumber` |
| Name on card | `nameOnCard` |
| Expiry | `expiry` |
| CVV | `cvv` |
| Store card | `storeCard` |

### Layout slot ids

A row whose `fields` array contains a **slot id** is rendered by the host
(around the form column) rather than as a form input. Slots are how
non-field UI gets a placement in the layout timeline.

| Slot | Id | Placement rule |
| --- | --- | --- |
| Card preview | `cardPreview` | Slot row before all field rows → preview on top. Slot row after all field rows → preview on the right (desktop) / bottom (mobile when `showOnMobile`). Omit the slot row to hide the preview. |

### Example

```javascript
layout: [
  { id: "cardNumberRow", fields: ["cardNumber"], className: "w-full", gap: 16 },
  {
    id: "detailsRow",
    fields: ["nameOnCard", "expiry", "cvv"],
    className: "flex-nowrap",
    gap: 16,
    fieldWidths: {
      nameOnCard: "flex-[2.25]",
      expiry: "flex-[1]",
      cvv: "flex-[1]",
    },
  },
  // Preview rendered to the right of (below on mobile) the form column.
  // Move this row to index 0 to render it above the form instead, or
  // omit it entirely to hide the preview.
  { id: "cardPreviewSlot", fields: ["cardPreview"] },
];
```

---

## 5. Process payment options

### `PayuCheckout.processPayment(options)`

| Field | Type | Required | Description |
| --- | --- | --- | --- |
| `additionalInformation` | `object` | No | Extra payload for gateway |
| `additionalInformation.billingInformation` | `object` | Sometimes | Billing address (e.g. international) |

Billing object commonly includes: `addressLine1`, `addressLine2`, `city`, `state`, `country`, `postalCode`.

---

## 6. Runtime: `element.updateStyle(style)`

Merges a **partial** object into the current style (same shape as §3), updates iframe dimensions when `width` / `minHeight` change, and sends `sdk:updateStyle` to the iframe. Note: the iframe's actual `height` is driven by the inner app's measured content height (auto-resizes), so changing `minHeight` only adjusts the floor — it won't shrink the iframe below the content.

```javascript
cardElement.updateStyle({
  minHeight: "320px",
  inputStyle: { borderColor: "#333" },
});
```

---

## 7. Full `cardPreview.style` field list

| Field | Type | Typical default* |
| --- | --- | --- |
| `background` | `string` | Gradient / solid |
| `borderRadius` | `number` | `10` |
| `borderWidth` | `number` | `0` |
| `borderColor` | `string` | `transparent` |
| `boxShadow` | `string` | `""` |
| `padding` | `number` | `14` |
| `showBackgroundPattern` | `boolean` | `true` |
| `patternColor` | `string` | `rgba(255,255,255,0.06)` |
| `cardNumberColor` | `string` | `#FFFFFF` |
| `cardNumberFontSize` | `number` | `18` |
| `cardNumberFontWeight` | `number` | `700` |
| `cardNumberFontStyle` | `string` | `normal` |
| `cardNumberLetterSpacing` | `string` | `0.09em` |
| `numberBandBackground` | `string \| null` | `rgba(2,2,3,0.10)` |
| `numberBandBorderRadius` | `number \| null` | `4` |
| `numberBandPaddingV` / `H` | `number \| null` | `8` |
| `cardHolderColor` | `string` | `#FFFFFF` |
| `cardHolderFontSize` | `number` | `11` |
| `cardHolderFontWeight` | `number` | `600` |
| `expiryColor` | `string` | `#FFFFFF` |
| `expiryFontSize` | `number` | `11` |
| `expiryFontWeight` | `number` | `600` |
| `labelColor` | `string` | `rgba(255,255,255,0.6)` |
| `labelFontSize` | `number` | `7` |
| `labelFontWeight` | `number` | `400` |
| `labelLetterSpacing` | `string` | `0.05em` |
| `labelTextTransform` | `string` | `uppercase` |
| `logoWidth` / `logoHeight` | `number` | `32` / `20` |
| `cardHolderLabelText` | `string` | `Card Holder Name` |
| `expiryLabelText` | `string` | `Valid Thru` |
| `cardNumberPlaceholder` | `string` | Masked dots pattern |
| `cardHolderPlaceholder` | `string` | `Name` |
| `expiryPlaceholder` | `string` | `MM/YY` |

---

*Last updated: March 2026*
