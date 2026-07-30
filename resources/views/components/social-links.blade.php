@props(['iconClass' => 'text-brand-gray-500'])

<div {{ $attributes->merge(['class' => 'flex items-center gap-4']) }}>
    <a href="https://www.instagram.com/yesmygrill_shawarma" target="_blank" rel="noopener"
        aria-label="Instagram" class="{{ $iconClass }} hover:text-brand-yellow transition">
        <svg viewBox="0 0 24 24" fill="none" class="w-6 h-6">
            <rect x="3" y="3" width="18" height="18" rx="5" stroke="currentColor" stroke-width="1.5" />
            <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.5" />
            <circle cx="17.5" cy="6.5" r="1" fill="currentColor" />
        </svg>
    </a>

    <a href="https://www.facebook.com/share/18ving6kE3/" target="_blank" rel="noopener"
        aria-label="Facebook" class="{{ $iconClass }} hover:text-brand-yellow transition">
        <svg viewBox="0 0 24 24" fill="none" class="w-6 h-6">
            <rect x="3" y="3" width="18" height="18" rx="5" stroke="currentColor" stroke-width="1.5" />
            <path d="M14 8.5h-1.3c-.9 0-1.7.7-1.7 1.6v1.65h3l-.4 2.1h-2.6V19h-2.2v-5.15H7.3v-2.1h1.5V9.9c0-1.87 1.5-3.4 3.4-3.4H14v2Z" fill="currentColor" />
        </svg>
    </a>

    <a href="https://www.tiktok.com/@ymgrillnshawarma" target="_blank" rel="noopener"
        aria-label="TikTok" class="{{ $iconClass }} hover:text-brand-yellow transition">
        <svg viewBox="0 0 24 24" fill="none" class="w-6 h-6">
            <rect x="3" y="3" width="18" height="18" rx="5" stroke="currentColor" stroke-width="1.5" />
            <path
                d="M13.5 6.75v7.35a2.4 2.4 0 1 1-1.9-2.35M13.5 6.75c.3 1.65 1.5 2.9 3 3.1"
                stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
            />
        </svg>
    </a>
</div>
