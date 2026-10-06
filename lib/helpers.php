<?php

declare(strict_types=1);

function app_base_url(): string
{
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8090';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

    return $scheme . '://' . $host . ($dir === '' ? '' : $dir);
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function format_inr(string|float|int $amount): string
{
    $n = (float) $amount;

    return '₹' . number_format($n, 2, '.', ',');
}

/**
 * @param array{badge?:string,subtitle?:string} $options
 */
function layout(string $title, string $body, string $extraHead = '', array $options = []): string
{
    $titleEsc  = e($title);
    $badge     = e((string) ($options['badge'] ?? 'UAT'));
    $subtitle  = e((string) ($options['subtitle'] ?? 'Secure card checkout'));
    $chipClass = strtoupper($badge) === 'UAT' ? 'env-chip--uat' : 'env-chip--prod';

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$titleEsc}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,500;8..60,600&display=swap" rel="stylesheet">
  <style>
    :root {
      --ink: #10231f;
      --muted: #5c736d;
      --line: #d7e3de;
      --surface: #ffffff;
      --surface-soft: #f3f7f5;
      --bg: #e8f0ec;
      --accent: #0b7a66;
      --accent-deep: #085c4d;
      --accent-soft: #d8f3ec;
      --warn: #9a6700;
      --warn-soft: #fff6e0;
      --danger: #b42318;
      --danger-soft: #fef3f2;
      --ok: #067647;
      --ok-soft: #ecfdf3;
      --shadow: 0 18px 50px rgba(16, 35, 31, 0.08);
      --radius: 18px;
      --font: "Outfit", system-ui, sans-serif;
      --display: "Source Serif 4", Georgia, serif;
    }

    * { box-sizing: border-box; }
    html, body { margin: 0; min-height: 100%; }
    body {
      font-family: var(--font);
      color: var(--ink);
      background:
        radial-gradient(ellipse 80% 50% at 10% -10%, rgba(11, 122, 102, 0.18), transparent 55%),
        radial-gradient(ellipse 60% 40% at 100% 0%, rgba(16, 35, 31, 0.06), transparent 50%),
        linear-gradient(180deg, #f4f8f6 0%, var(--bg) 48%, #dfe9e4 100%);
      -webkit-font-smoothing: antialiased;
    }

    .shell {
      width: min(1120px, calc(100% - 2rem));
      margin: 0 auto;
      padding: 1.25rem 0 2.75rem;
    }

    .topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      margin-bottom: 1.5rem;
      animation: rise .5s ease both;
    }
    .brand {
      display: flex;
      align-items: center;
      gap: .75rem;
      text-decoration: none;
      color: inherit;
    }
    .brand-mark {
      width: 42px;
      height: 42px;
      border-radius: 12px;
      background: linear-gradient(145deg, var(--accent) 0%, var(--accent-deep) 100%);
      color: #fff;
      display: grid;
      place-items: center;
      font-weight: 700;
      font-size: .95rem;
      letter-spacing: .04em;
      box-shadow: 0 10px 24px rgba(11, 122, 102, 0.28);
    }
    .brand-copy strong {
      display: block;
      font-size: 1rem;
      font-weight: 650;
      letter-spacing: -.01em;
    }
    .brand-copy span {
      display: block;
      color: var(--muted);
      font-size: .8rem;
      margin-top: .1rem;
    }
    .env-chip {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      padding: .4rem .75rem;
      border-radius: 999px;
      font-size: .78rem;
      font-weight: 650;
    }
    .env-chip--uat {
      background: var(--accent-soft);
      color: var(--accent-deep);
      border: 1px solid #a7dfd4;
    }
    .env-chip--uat::before {
      content: "";
      width: .45rem;
      height: .45rem;
      border-radius: 50%;
      background: currentColor;
      box-shadow: 0 0 0 3px rgba(11, 122, 102, 0.15);
    }
    .env-chip--prod {
      background: var(--warn-soft);
      color: var(--warn);
      border: 1px solid #f5e0a8;
    }
    .env-chip--prod::before {
      content: "";
      width: .45rem;
      height: .45rem;
      border-radius: 50%;
      background: currentColor;
      box-shadow: 0 0 0 3px rgba(154, 103, 0, 0.15);
    }

    .checkout-grid {
      display: grid;
      grid-template-columns: minmax(280px, 0.92fr) minmax(0, 1.2fr);
      gap: 1.15rem;
      align-items: start;
    }

    .panel {
      background: var(--surface);
      border: 1px solid rgba(215, 227, 222, 0.95);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      overflow: hidden;
    }
    .panel-pad { padding: 1.35rem 1.4rem 1.5rem; }

    .summary {
      background:
        linear-gradient(165deg, #12352e 0%, #0b7a66 58%, #14967d 100%);
      color: #f4fffb;
      position: relative;
      isolation: isolate;
      animation: rise .55s ease .05s both;
    }
    .summary::after {
      content: "";
      position: absolute;
      inset: auto -20% -35% 20%;
      height: 70%;
      background: radial-gradient(circle, rgba(255,255,255,.16), transparent 65%);
      pointer-events: none;
      z-index: -1;
    }
    .summary .eyebrow {
      margin: 0 0 .85rem;
      font-size: .72rem;
      letter-spacing: .14em;
      text-transform: uppercase;
      opacity: .78;
      font-weight: 600;
    }
    .summary h1 {
      margin: 0;
      font-family: var(--display);
      font-size: clamp(1.55rem, 2.4vw, 2rem);
      font-weight: 600;
      letter-spacing: -.02em;
      line-height: 1.2;
    }
    .summary .lede {
      margin: .55rem 0 0;
      opacity: .82;
      font-size: .92rem;
      line-height: 1.45;
      max-width: 28ch;
    }
    .amount-hero {
      margin-top: 1.6rem;
      padding-top: 1.25rem;
      border-top: 1px solid rgba(255,255,255,.18);
    }
    .amount-hero .label {
      font-size: .78rem;
      letter-spacing: .08em;
      text-transform: uppercase;
      opacity: .7;
      font-weight: 600;
    }
    .amount-hero .value {
      margin-top: .25rem;
      font-size: clamp(2rem, 4vw, 2.55rem);
      font-weight: 700;
      letter-spacing: -.03em;
      font-variant-numeric: tabular-nums;
    }
    .amount-hero .product {
      margin-top: .35rem;
      opacity: .85;
      font-size: .95rem;
    }

    .meta-list {
      list-style: none;
      margin: 1.35rem 0 0;
      padding: 0;
      display: grid;
      gap: .7rem;
    }
    .meta-list li {
      display: flex;
      justify-content: space-between;
      gap: 1rem;
      font-size: .88rem;
    }
    .meta-list span { opacity: .72; }
    .meta-list strong {
      font-weight: 600;
      text-align: right;
      word-break: break-all;
      font-variant-numeric: tabular-nums;
    }

    .trust {
      display: flex;
      flex-wrap: wrap;
      gap: .5rem;
      margin-top: 1.5rem;
    }
    .trust span {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      padding: .35rem .65rem;
      border-radius: 999px;
      background: rgba(255,255,255,.12);
      border: 1px solid rgba(255,255,255,.16);
      font-size: .72rem;
      font-weight: 600;
      letter-spacing: .02em;
    }

    .pay-panel { animation: rise .55s ease .1s both; }
    .section-title {
      margin: 0 0 .25rem;
      font-size: 1.15rem;
      font-weight: 650;
      letter-spacing: -.015em;
    }
    .section-sub {
      margin: 0 0 1.15rem;
      color: var(--muted);
      font-size: .9rem;
      line-height: 1.45;
    }

    .steps {
      display: flex;
      gap: .4rem;
      margin: 0 0 1.2rem;
      padding: 0;
      list-style: none;
    }
    .steps li {
      flex: 1;
      height: 4px;
      border-radius: 99px;
      background: var(--line);
      overflow: hidden;
    }
    .steps li.active { background: var(--accent); }
    .steps li.done { background: #7ec8b8; }

    #payu-card-element-container {
      min-height: 300px;
      padding: .15rem 0 .35rem;
    }
    #payu-card-element-container iframe {
      width: 100% !important;
      min-height: 300px;
    }

    .bin-chip {
      display: none;
      margin-top: .75rem;
      padding: .55rem .85rem;
      border-radius: 10px;
      background: var(--surface-soft);
      border: 1px solid var(--line);
      font-size: .82rem;
      color: var(--muted);
      line-height: 1.4;
    }
    .bin-chip.is-visible { display: block; }
    .bin-chip strong { color: var(--ink); font-weight: 650; }

    .response-panel {
      margin-top: 1rem;
      border: 1px solid var(--line);
      border-radius: 14px;
      background: #13241f;
      overflow: hidden;
    }
    .response-panel[hidden] { display: none !important; }
    .response-panel-head {
      display: flex;
      flex-wrap: wrap;
      align-items: baseline;
      justify-content: space-between;
      gap: .5rem 1rem;
      padding: .75rem 1rem;
      background: rgba(255,255,255,.06);
      border-bottom: 1px solid rgba(255,255,255,.08);
      color: #d7ebe4;
      font-size: .82rem;
    }
    .response-panel-head strong { font-weight: 650; color: #fff; }
    .response-hint {
      color: #9ecabb;
      font-size: .78rem;
      line-height: 1.35;
      max-width: 100%;
    }
    .response-panel pre {
      margin: 0;
      max-height: 360px;
      border-radius: 0;
      background: transparent;
      color: #d7ebe4;
      padding: 1rem;
      font-size: .72rem;
    }

    .fallback-box {
      display: none;
      margin-top: 1rem;
      padding: 1rem;
      border-radius: 14px;
      background: var(--warn-soft);
      border: 1px solid #f5d99a;
      color: var(--warn);
      font-size: .9rem;
      line-height: 1.45;
    }
    .fallback-box.is-visible { display: block; }

    .status-line, .alert {
      display: none;
      margin-top: 1rem;
      padding: .85rem 1rem;
      border-radius: 12px;
      font-size: .9rem;
      line-height: 1.45;
    }
    .status-line.is-visible, .alert.is-visible { display: block; }
    .status-line, .alert-info {
      background: var(--accent-soft);
      color: var(--accent-deep);
      border: 1px solid #b7e5d8;
    }
    .alert-error {
      background: var(--danger-soft);
      color: var(--danger);
      border: 1px solid #fecdca;
    }
    .alert-ok {
      background: var(--ok-soft);
      color: var(--ok);
      border: 1px solid #abefc6;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: .45rem;
      border: 0;
      border-radius: 12px;
      padding: .9rem 1.15rem;
      font-family: inherit;
      font-size: .95rem;
      font-weight: 650;
      cursor: pointer;
      text-decoration: none;
      transition: transform .15s ease, background .15s ease, box-shadow .15s ease, opacity .15s ease;
    }
    .btn:hover { transform: translateY(-1px); }
    .btn:active { transform: translateY(0); }
    .btn-primary {
      width: 100%;
      margin-top: 1rem;
      background: linear-gradient(180deg, #10957d 0%, var(--accent) 100%);
      color: #fff;
      box-shadow: 0 12px 24px rgba(11, 122, 102, 0.25);
    }
    .btn-primary:hover { background: linear-gradient(180deg, #0e8872 0%, var(--accent-deep) 100%); }
    .btn-primary:disabled {
      opacity: .42;
      cursor: not-allowed;
      transform: none;
      box-shadow: none;
    }
    .btn-ghost {
      background: var(--surface);
      color: var(--ink);
      border: 1px solid var(--line);
    }
    .btn-ghost:hover { background: var(--surface-soft); }
    .btn-row {
      display: flex;
      flex-wrap: wrap;
      gap: .65rem;
      margin-top: 1rem;
    }
    .btn-row .btn-primary { width: auto; min-width: 148px; margin-top: 0; }

    .debug {
      margin-top: 1.15rem;
      animation: rise .55s ease .15s both;
    }
    .debug details {
      background: rgba(255,255,255,.72);
      border: 1px solid var(--line);
      border-radius: 14px;
      overflow: hidden;
      backdrop-filter: blur(8px);
    }
    .debug summary {
      cursor: pointer;
      list-style: none;
      padding: .95rem 1.15rem;
      font-weight: 650;
      font-size: .9rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
    }
    .debug summary::-webkit-details-marker { display: none; }
    .debug summary::after {
      content: "Show";
      color: var(--muted);
      font-weight: 500;
      font-size: .8rem;
    }
    .debug details[open] summary::after { content: "Hide"; }
    .debug-body {
      padding: 0 1.15rem 1.15rem;
      border-top: 1px solid var(--line);
    }
    .kv {
      width: 100%;
      border-collapse: collapse;
      font-size: .86rem;
      margin: 1rem 0;
    }
    .kv th, .kv td {
      text-align: left;
      padding: .55rem 0;
      border-bottom: 1px solid var(--line);
      vertical-align: top;
    }
    .kv th {
      width: 32%;
      color: var(--muted);
      font-weight: 600;
    }
    code, pre {
      word-break: break-all;
      white-space: pre-wrap;
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    }
    pre {
      margin: 0;
      background: #13241f;
      color: #d7ebe4;
      padding: 1rem;
      border-radius: 12px;
      font-size: .75rem;
      line-height: 1.45;
      max-height: 280px;
      overflow: auto;
    }

    .result-page {
      max-width: 560px;
      margin: 1.5rem auto 0;
      animation: rise .5s ease both;
      text-align: center;
    }
    .result-icon {
      width: 64px;
      height: 64px;
      border-radius: 18px;
      display: grid;
      place-items: center;
      font-size: 1.5rem;
      font-weight: 700;
      margin: 0 auto 1rem;
    }
    .result-icon.ok { background: var(--ok-soft); color: var(--ok); }
    .result-icon.bad { background: var(--danger-soft); color: var(--danger); }
    .result-icon.muted { background: var(--surface-soft); color: var(--muted); }
    .result-icon.pending { background: var(--warn-soft); color: var(--warn); }
    .result-page h1 {
      margin: 0;
      font-family: var(--display);
      font-size: 2rem;
      font-weight: 600;
    }
    .result-page .sub {
      margin: .45rem 0 1rem;
      color: var(--muted);
      line-height: 1.5;
    }
    .callback-card { text-align: center; }
    .callback-amount {
      margin: .35rem 0 0;
      font-size: clamp(2rem, 5vw, 2.6rem);
      font-weight: 700;
      letter-spacing: -.03em;
      font-variant-numeric: tabular-nums;
      color: var(--ink);
    }
    .callback-product {
      margin: .15rem 0 1.1rem;
      color: var(--muted);
      font-size: .95rem;
    }
    .result-details {
      text-align: left;
      margin: 1.1rem 0 0;
      border: 1px solid var(--line);
      border-radius: 14px;
      overflow: hidden;
      background: #fff;
    }
    .result-details.user-details {
      box-shadow: inset 0 0 0 1px rgba(11, 122, 102, 0.04);
    }
    .result-row {
      display: grid;
      grid-template-columns: minmax(110px, 38%) 1fr;
      gap: .75rem;
      padding: .85rem 1.05rem;
      font-size: .92rem;
      border-bottom: 1px solid var(--line);
      align-items: baseline;
    }
    .result-row:last-child { border-bottom: none; }
    .result-row span { color: var(--muted); font-weight: 500; }
    .result-row strong {
      font-weight: 650;
      text-align: right;
      overflow-wrap: anywhere;
      word-break: normal;
      font-variant-numeric: tabular-nums;
    }
    .tech-fold, .raw-fold details {
      margin-top: .85rem;
      text-align: left;
      border: 1px solid var(--line);
      border-radius: 14px;
      background: rgba(255,255,255,.7);
      overflow: hidden;
    }
    .tech-fold > summary,
    .raw-fold summary {
      cursor: pointer;
      list-style: none;
      padding: .85rem 1.05rem;
      font-weight: 650;
      font-size: .88rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .tech-fold > summary::-webkit-details-marker,
    .raw-fold summary::-webkit-details-marker { display: none; }
    .tech-fold > summary::after,
    .raw-fold summary::after {
      content: "Show";
      color: var(--muted);
      font-weight: 500;
      font-size: .78rem;
    }
    .tech-fold[open] > summary::after,
    .raw-fold details[open] summary::after { content: "Hide"; }
    .tech-details {
      margin: 0;
      border: 0;
      border-top: 1px solid var(--line);
      border-radius: 0;
      background: var(--surface-soft);
    }
    .raw-fold .debug-body { padding: 0 1.05rem 1.05rem; border-top: 1px solid var(--line); }
    pre.raw-json {
      margin: 1rem 0 0;
      background: #13241f;
      color: #d7ebe4;
      padding: 1rem 1.1rem;
      border-radius: 12px;
      font-size: .78rem;
      line-height: 1.55;
      max-height: 360px;
      overflow: auto;
      white-space: pre;
      word-break: normal;
      overflow-wrap: normal;
      tab-size: 2;
    }
    .text-ok { color: var(--ok); }
    .text-bad { color: var(--danger); }

    .footer-note {
      margin-top: 1.5rem;
      text-align: center;
      color: var(--muted);
      font-size: .78rem;
    }

    @keyframes rise {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 860px) {
      .checkout-grid { grid-template-columns: 1fr; }
      .shell { width: min(100% - 1.25rem, 1120px); }
      .summary h1 { max-width: none; }
    }
  </style>
  {$extraHead}
</head>
<body>
  <div class="shell">
    <header class="topbar">
      <a class="brand" href="index.php">
        <div class="brand-mark">PayU</div>
        <div class="brand-copy">
          <strong>Checkout Elements</strong>
          <span>{$subtitle}</span>
        </div>
      </a>
      <span class="env-chip {$chipClass}">{$badge}</span>
    </header>
    {$body}
    <p class="footer-note">Card data is captured inside PayU’s secure iframe · PCI-friendly</p>
  </div>
</body>
</html>
HTML;
}
