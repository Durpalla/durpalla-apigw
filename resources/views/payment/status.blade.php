<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Payment Status</title>

    <style>
        :root {
            --primary: #16a34a;
            --bg: #f8fafc;
            --text: #0f172a;
            --muted: #64748b;
        }

        * {
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI",
            Roboto, Oxygen, Ubuntu, Cantarell, "Helvetica Neue",
            sans-serif;
        }

        body {
            margin: 0;
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            text-align: center;
        }

        .icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: rgba(22, 163, 74, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }

        .icon svg {
            width: 36px;
            height: 36px;
            color: var(--primary);
        }

        h1 {
            font-size: 22px;
            color: var(--text);
            margin: 0 0 8px;
        }

        p {
            font-size: 14px;
            color: var(--muted);
            margin: 0 0 16px;
        }

        .details {
            background: #f1f5f9;
            border-radius: 12px;
            padding: 16px;
            text-align: left;
            margin-bottom: 20px;
        }

        .row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .row:last-child {
            margin-bottom: 0;
        }

        .label {
            color: var(--muted);
        }

        .value {
            color: var(--text);
            font-weight: 600;
        }

        .btn {
            display: block;
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            background: var(--primary);
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            border: none;
            cursor: pointer;
        }

        .btn.secondary {
            margin-top: 12px;
            background: #e2e8f0;
            color: var(--text);
        }
    </style>
</head>
<body>

<div class="card">
    <div class="icon">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M5 13l4 4L19 7" />
        </svg>
    </div>

    <h1>Payment {{ $payment->nice_status }}</h1>

    <div class="details">
        <div class="row">
            <span class="label">Amount</span>
            <span class="value">৳ {{ $payment->amount }}</span>
        </div>
        <div class="row">
            <span class="label">Transaction ID</span>
            <span class="value">{{ $payment->transaction_id }}</span>
        </div>
        <div class="row">
            <span class="label">Date</span>
            <span class="value">{{ $payment->created_at }}</span>
        </div>
        <div class="row">
            <span class="label">Status</span>
            <span class="value">{{ $payment->nice_status }}</span>
        </div>
    </div>
</div>

</body>
</html>
