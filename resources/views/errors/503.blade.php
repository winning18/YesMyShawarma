{{--
    Shown while a deploy updates the site (`php artisan down --render="errors::503"`
    in .github/workflows). Pre-rendered into a static file before the code
    changes, so it must stand alone: no layout, no database, no assets, no
    business-specific text (any install can show it). Neutral greys only.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('Back in a minute') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
               background: #f9fafb; color: #111827; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        main { max-width: 28rem; padding: 2rem 1.5rem; text-align: center; }
        h1 { font-size: 1.5rem; margin: 0 0 .75rem; }
        p { margin: 0; color: #4b5563; line-height: 1.5; }
    </style>
</head>
<body>
    <main>
        <h1>{{ __('Back in a minute') }}</h1>
        <p>{{ __('We\'re updating the site. This page will reload by itself.') }}</p>
    </main>
</body>
</html>
