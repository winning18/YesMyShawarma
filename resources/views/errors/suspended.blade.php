{{--
    Shown on every page while the business's account is suspended (owner,
    2026-11-24; Express POS Admin → Suspend, `php artisan down --render=
    "errors::suspended"`). Rendered once, when suspending, so it needs no
    database while showing. Neutral on purpose: customers see the
    business's name, never "Express POS" or why.
--}}
@php
    $profile = app(\App\Services\Settings\BusinessProfile::class);
    $name = $profile->name();
    $logo = $profile->logoUrl();
    $colours = $profile->colours();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $name }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f9fafb; color: #111827; padding: 24px; box-sizing: border-box; }
        main { max-width: 420px; text-align: center; }
        img { max-height: 72px; max-width: 220px; margin-bottom: 24px; }
        .bar { height: 4px; width: 64px; margin: 0 auto 24px; border-radius: 2px; background: {{ $colours['brand_primary'] }}; }
        h1 { font-size: 1.5rem; margin: 0 0 8px; }
        p { color: #4b5563; line-height: 1.5; margin: 0; }
    </style>
</head>
<body>
    <main>
        @if ($logo)
            <img src="{{ $logo }}" alt="{{ $name }}">
        @endif
        <div class="bar"></div>
        <h1>{{ __(':name is temporarily unavailable', ['name' => $name]) }}</h1>
        <p>{{ __('Please check back soon.') }}</p>
    </main>
</body>
</html>
