<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Payment Not Found</title>

    <style>
        :root {
            --danger: #dc2626;
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
            background: rgba(220, 38, 38, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }

        .icon svg {
            width: 36px;
            height: 36px;
            color: var(--danger);
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
            background: #fef2f2;
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
            background: var(--danger);
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            border: none;
            cursor: pointer;
        }

        .btn.secondary {
            margin-top: 12px;
            background: #e5e7eb;
            color: var(--text);
        }
    </style>
</head>
<body>

<div class="card">
    <div class="icon">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M6 18L18 6M6 6l12 12" />
        </svg>
    </div>

    <h1>Payment Not Found</h1>
    <p>We could not find any payment information for this request.</p>

    <div class="details">
        <div class="row">
            <span class="label">Reference</span>
            <span class="value">N/A</span>
        </div>
        <div class="row">
            <span class="label">Status</span>
            <span class="value">No Record</span>
        </div>
        <div class="row">
            <span class="label">Date</span>
            <span class="value">—</span>
        </div>
    </div>
</div>

</body>
</html>
