{{--
    Where this install's real-time server is (resources/js/echo.js reads
    it): from this install's own .env at run time, not baked into the
    JavaScript build, so one build serves every Express POS install. The
    app key is public by design (Reverb's key, never its secret).
--}}
@php($reverb = config('broadcasting.connections.reverb'))
<meta name="reverb-config" content="{{ json_encode(['key' => $reverb['key'] ?? null, 'host' => $reverb['options']['host'] ?? null, 'port' => (int) ($reverb['options']['port'] ?? 443), 'scheme' => $reverb['options']['scheme'] ?? 'https']) }}">
