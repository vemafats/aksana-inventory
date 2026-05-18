<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;700&display=swap"
    rel="stylesheet"
>
@if (file_exists(public_path('build/manifest.json')))
    @vite('resources/css/filament/admin/theme.css')
@else
    <link rel="stylesheet" href="{{ asset('css/filament-aksana.css') }}">
@endif
