@php
    $logoUrl = config('payment.brand_icon_url');
    $logoTone = $tone ?? 'success';
    $logoSize = $size ?? 'hero';
@endphp
<img
    src="{{ $logoUrl }}"
    alt="Durpalla"
    class="brand-logo brand-logo--{{ $logoSize }} brand-logo--{{ $logoTone }}"
    width="56"
    height="56"
    loading="eager"
    decoding="async"
/>
