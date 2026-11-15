{{--
    Solusys Digital's own social pages and website (the maker of Express
    POS) — on the sign-in pages only. Not a business's links; those are
    x-social-links, from its Business profile.
--}}
@props(['onLight' => false])
@php
    $links = [
        'TikTok' => ['https://www.tiktok.com/@solusys.digital.gh?lang=en', '<rect x="3" y="3" width="18" height="18" rx="5" stroke="currentColor" stroke-width="1.5"/><path d="M13.5 6.75v7.35a2.4 2.4 0 1 1-1.9-2.35M13.5 6.75c.3 1.65 1.5 2.9 3 3.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'],
        'Instagram' => ['https://www.instagram.com/solusysdigital/', '<rect x="3" y="3" width="18" height="18" rx="5" stroke="currentColor" stroke-width="1.5"/><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.5"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/>'],
        'Facebook' => ['https://web.facebook.com/user.illuminate/', '<rect x="3" y="3" width="18" height="18" rx="5" stroke="currentColor" stroke-width="1.5"/><path d="M14 8.5h-1.3c-.9 0-1.7.7-1.7 1.6v1.65h3l-.4 2.1h-2.6V19h-2.2v-5.15H7.3v-2.1h1.5V9.9c0-1.87 1.5-3.4 3.4-3.4H14v2Z" fill="currentColor"/>'],
        'Solusys Digital website' => ['https://www.solusysdigital.com', '<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>'],
    ];
@endphp
<div {{ $attributes }}>
    <p class="text-xs text-xpos-muted">{{ __('Follow us on social media') }}</p>
    <div class="mt-2 flex items-center gap-4">
        @foreach ($links as $label => [$url, $icon])
            <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ $label }}" title="{{ $label }}" class="{{ $onLight ? 'text-gray-500 hover:text-xpos-midnight' : 'text-xpos-muted hover:text-xpos-gold' }} transition">
                <svg viewBox="0 0 24 24" fill="none" class="w-6 h-6" aria-hidden="true">{!! $icon !!}</svg>
            </a>
        @endforeach
    </div>
</div>
