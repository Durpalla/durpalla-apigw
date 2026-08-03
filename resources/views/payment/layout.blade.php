<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="theme-color" content="#006D9C" />
    <meta name="robots" content="noindex, nofollow" />
    <link rel="icon" href="{{ config('payment.brand_icon_url') }}" type="image/png" />
    <link rel="apple-touch-icon" href="{{ config('payment.brand_icon_url') }}" />
    <title>@yield('title', 'Payment') — Durpalla</title>
    <style>
        :root {
            --brand: #006D9C;
            --brand-dark: #004A70;
            --brand-hover: #005A87;
            --brand-light: #E6F4F9;
            --brand-border: #B8DDEB;
            --brand-ring: rgba(0, 109, 156, 0.18);
            --success: #059669;
            --success-light: #ECFDF5;
            --success-ring: rgba(5, 150, 105, 0.2);
            --danger: #DC2626;
            --danger-light: #FEF2F2;
            --danger-ring: rgba(220, 38, 38, 0.18);
            --warn: #D97706;
            --warn-light: #FFFBEB;
            --text: #0F172A;
            --muted: #64748B;
            --surface: #FFFFFF;
            --bg-top: #F0F9FC;
            --shadow: 0 24px 48px -12px rgba(0, 77, 112, 0.14);
            --radius: 20px;
        }

        *, *::before, *::after { box-sizing: border-box; }

        html, body {
            margin: 0;
            min-height: 100%;
            overflow-x: hidden;
            -webkit-text-size-adjust: 100%;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
                "Helvetica Neue", Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            color: var(--text);
            background: linear-gradient(165deg, var(--bg-top) 0%, #FFFFFF 42%, #F8FAFC 100%);
        }

        body {
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
        }

        .page {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: max(28px, env(safe-area-inset-top)) 16px max(24px, env(safe-area-inset-bottom));
        }

        .brand-logo {
            display: block;
            object-fit: contain;
        }

        .brand-logo--hero {
            width: 52px;
            height: 52px;
        }

        /* Success: full brand color — reads as confirmed */
        .brand-logo--success {
            filter: none;
        }

        /* Failed: muted grey mark */
        .brand-logo--failed {
            filter: grayscale(1) saturate(0) brightness(1.05) opacity(0.42);
        }

        /* Pending / unknown: soft grey-blue */
        .brand-logo--pending,
        .brand-logo--neutral {
            filter: grayscale(0.35) saturate(0.45) brightness(1.08) opacity(0.55);
        }

        .card {
            width: 100%;
            max-width: 420px;
            background: var(--surface);
            border-radius: var(--radius);
            border: 1px solid var(--brand-border);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .card-body {
            padding: 28px 22px 24px;
            text-align: center;
        }

        .status-icon-wrap {
            position: relative;
            width: 88px;
            height: 88px;
            margin: 0 auto 20px;
        }

        .status-icon-wrap::before {
            content: "";
            position: absolute;
            inset: -6px;
            border-radius: 50%;
            opacity: 0.55;
            animation: pulse-ring 2.4s ease-out infinite;
        }

        .status-icon-wrap.success::before { background: var(--success-ring); }
        .status-icon-wrap.failed::before { background: rgba(100, 116, 139, 0.16); }
        .status-icon-wrap.pending::before { background: var(--brand-ring); }
        .status-icon-wrap.neutral::before { background: rgba(100, 116, 139, 0.15); }

        @keyframes pulse-ring {
            0% { transform: scale(0.92); opacity: 0.7; }
            70% { transform: scale(1.08); opacity: 0; }
            100% { transform: scale(1.08); opacity: 0; }
        }

        .status-icon {
            position: relative;
            width: 88px;
            height: 88px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid transparent;
        }

        .status-icon.success {
            background: var(--success-light);
            border-color: rgba(5, 150, 105, 0.22);
        }

        .status-icon.failed {
            background: #F1F5F9;
            border-color: rgba(100, 116, 139, 0.22);
        }

        .status-icon.pending {
            background: var(--brand-light);
            border-color: var(--brand-border);
        }

        .status-icon.neutral {
            background: #F1F5F9;
            border-color: rgba(100, 116, 139, 0.18);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .badge.success {
            background: var(--success-light);
            color: var(--success);
            border: 1px solid rgba(5, 150, 105, 0.25);
        }

        .badge.failed {
            background: var(--danger-light);
            color: var(--danger);
            border: 1px solid rgba(220, 38, 38, 0.2);
        }

        .badge.pending {
            background: var(--brand-light);
            color: var(--brand);
            border: 1px solid var(--brand-border);
        }

        h1 {
            margin: 0 0 8px;
            font-size: 1.45rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.25;
        }

        .subtitle {
            margin: 0 0 22px;
            font-size: 0.92rem;
            line-height: 1.55;
            color: var(--muted);
        }

        .receipt {
            text-align: left;
            border-radius: 14px;
            border: 1px solid var(--brand-border);
            background: linear-gradient(180deg, var(--brand-light) 0%, #FFFFFF 100%);
            overflow: hidden;
            margin-bottom: 18px;
        }

        .receipt-head {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-bottom: 1px solid var(--brand-border);
            background: rgba(255, 255, 255, 0.65);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--brand);
        }

        .receipt-head svg {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
        }

        .receipt-body { padding: 14px; }

        .row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            font-size: 0.88rem;
            padding: 7px 0;
        }

        .row + .row {
            border-top: 1px dashed rgba(184, 221, 235, 0.9);
        }

        .label { color: var(--muted); flex-shrink: 0; }
        .value {
            color: var(--text);
            font-weight: 600;
            text-align: right;
            word-break: break-word;
        }

        .value.mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.78rem;
            font-weight: 500;
        }

        .amount {
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: var(--brand-dark);
            margin: 4px 0 0;
        }

        .hint {
            font-size: 0.75rem;
            line-height: 1.5;
            color: var(--muted);
            margin: 0 0 18px;
        }

        .trust {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 14px 18px;
            padding: 14px 16px;
            border-top: 1px solid var(--brand-border);
            background: rgba(230, 244, 249, 0.45);
        }

        .trust span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 10px;
            font-weight: 600;
            color: var(--brand);
            letter-spacing: 0.01em;
        }

        .trust svg {
            width: 13px;
            height: 13px;
            flex-shrink: 0;
        }

        .app-compact .page {
            padding: max(12px, env(safe-area-inset-top)) 12px max(12px, env(safe-area-inset-bottom));
            justify-content: center;
        }

        .app-compact .card {
            max-width: 100%;
            border-radius: 16px;
            box-shadow: 0 10px 28px -8px rgba(0, 77, 112, 0.1);
        }

        .app-compact .card-body { padding: 22px 16px 18px; }

        .app-compact .status-icon-wrap,
        .app-compact .status-icon {
            width: 72px;
            height: 72px;
        }

        .app-compact .status-icon-wrap { margin-bottom: 14px; }

        .app-compact .brand-logo--hero {
            width: 42px;
            height: 42px;
        }

        .app-compact h1 {
            font-size: 1.125rem;
            margin-bottom: 6px;
        }

        .app-compact .subtitle {
            font-size: 0.8125rem;
            line-height: 1.45;
            margin-bottom: 10px;
        }

        .app-compact .badge {
            font-size: 10px;
            padding: 4px 10px;
            margin-bottom: 8px;
        }

        .app-compact .receipt { display: none; }

        .app-compact .hint {
            font-size: 0.6875rem;
            margin: 0;
        }

        .app-compact .trust {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 4px;
            padding: 10px 8px;
        }

        .app-compact .trust span {
            justify-content: center;
            font-size: 9px;
            gap: 3px;
            white-space: nowrap;
            min-width: 0;
        }

        .app-compact .trust svg {
            width: 11px;
            height: 11px;
            flex-shrink: 0;
        }

        .app-compact .footer-note {
            font-size: 0.625rem;
            margin-top: 10px;
            padding: 0 2px;
            line-height: 1.4;
        }

        @media (max-width: 480px) {
            .page {
                padding: max(12px, env(safe-area-inset-top)) 12px max(12px, env(safe-area-inset-bottom));
            }

            .card {
                max-width: 100%;
                border-radius: 16px;
            }

            .card-body { padding: 22px 16px 18px; }

            .trust {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 4px;
                padding: 10px 8px;
            }

            .trust span {
                justify-content: center;
                font-size: 9px;
                gap: 3px;
                white-space: nowrap;
            }

            .trust svg {
                width: 11px;
                height: 11px;
            }

            .footer-note {
                font-size: 0.625rem;
                margin-top: 10px;
            }
        }

        .footer-note {
            max-width: 420px;
            width: 100%;
            margin-top: 18px;
            text-align: center;
            font-size: 0.72rem;
            color: var(--muted);
            line-height: 1.45;
        }

        .footer-note p {
            margin: 0;
        }

        .footer-copy {
            margin-top: 6px !important;
            font-size: 0.65rem;
            opacity: 0.85;
        }

        .app-compact .footer-copy {
            margin-top: 4px !important;
            font-size: 0.6rem;
        }
    </style>
</head>
<body class="@yield('body_class')">
<div class="page">
    @yield('content')

    <div class="footer-note">
        <p>Never share your wallet PIN or OTP with anyone.</p>
        <p class="footer-copy">&copy; {{ date('Y') }} Durpalla. All rights reserved.</p>
    </div>
</div>
</body>
</html>
