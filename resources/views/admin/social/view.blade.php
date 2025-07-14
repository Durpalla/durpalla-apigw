<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta property="og:url"           content="{{config('paths.frontend_site_url')}}/schedule/{{$social->vehicle_schedule_id}}" />
    <meta property="og:type"          content="website" />
    <meta property="og:title"         content="{{$social->name}}" />
    <meta property="og:description"   content="{{$social->description}}" />
    <meta property="og:image"         content="{{asset($social->poster)}}" />

    <title>{{ $social->title ?? 'Jolzan.' }}</title>
</head>
<body>
<!-- Load Facebook SDK for JavaScript -->
<div id="fb-root"></div>
<script>(function(d, s, id) {
        var js, fjs = d.getElementsByTagName(s)[0];
        if (d.getElementById(id)) return;
        js = d.createElement(s); js.id = id;
        js.src = "https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v3.0";
        fjs.parentNode.insertBefore(js, fjs);
    }(document, 'script', 'facebook-jssdk'));</script>
<div class="fb-share-button"
     data-href="http://app.jolzatra.com/search?trip_id={{$social->vehicle_schedule_id}}"
     data-layout="button_count">
</div>
</body>
</html>
