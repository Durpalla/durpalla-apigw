<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('invoice.unavailable') }}</title>
    <style>
        body { font-family: sans-serif; background:#F5F7FA; color:#1F2937; padding:24px; }
        .box { max-width:480px; margin:40px auto; background:#fff; border-radius:12px; padding:24px; border:1px solid #E5E7EB; }
        h1 { font-size:20px; margin:0 0 8px; }
        p { color:#6B7280; line-height:1.5; }
    </style>
</head>
<body>
<div class="box">
    <h1>{{ __('invoice.unavailable') }}</h1>
    <p>{{ $message ?? __('invoice.load_failed') }}</p>
    @if (!empty($booking_id))
        <p>{{ __('invoice.booking_label', ['id' => $booking_id]) }}</p>
    @endif
</div>
</body>
</html>
