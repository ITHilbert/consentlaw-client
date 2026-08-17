{{--
    Minimal-Layout als Notausgang, wenn im Kundenprojekt kein Layout über
    consentlaw.routes.layout gesetzt wurde. Absichtlich ohne externe Assets
    (DSGVO: keine CDN-Fonts, kein Tracking).
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    <style>
        body { font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
               line-height: 1.6; color: #374151; max-width: 48rem;
               margin: 0 auto; padding: 2rem 1rem; }
        h1, h2, h3 { color: #1e3a8a; line-height: 1.25; }
        a { color: #2563eb; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #e5e7eb; padding: .5rem; text-align: left; }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
