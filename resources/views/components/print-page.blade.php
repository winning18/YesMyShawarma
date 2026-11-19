@props(['title', 'width' => 80])
{{--
    A thermal-printer page: $width mm paper (58 or 80), printable area a
    little narrower. Plain, high-contrast and small so it prints crisply on
    any receipt printer through the browser (step 8a).
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { size: {{ $width }}mm auto; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #fff; color: #000; }
        body { width: {{ $width === 58 ? 48 : 72 }}mm; margin: 0 auto; padding: 3mm 0 6mm; font: {{ $width === 58 ? 11 : 12 }}px/1.35 Arial, Helvetica, sans-serif; }
        .center { text-align: center; }
        .big { font-size: 1.6em; font-weight: 700; }
        .huge { font-size: 2.4em; font-weight: 800; letter-spacing: .02em; }
        .bold { font-weight: 700; }
        .muted { color: #000; opacity: .8; }
        .rule { border-top: 1px dashed #000; margin: 2mm 0; }
        .row { display: flex; justify-content: space-between; gap: 2mm; }
        .row > :last-child { white-space: nowrap; text-align: right; }
        .opt { padding-left: 4mm; }
        img.logo { display: block; max-width: 70%; max-height: 18mm; margin: 0 auto 1.5mm; filter: grayscale(1) contrast(1.4); }
        p { margin: 0; }
    </style>
</head>
<body>
    {{ $slot }}
</body>
</html>
