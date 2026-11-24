import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                // Scoped to customer-facing views (see layouts/customer.blade.php)
                // rather than replacing the staff dashboard's existing Figtree.
                customer: ['Inter', ...defaultTheme.fontFamily.sans],
                // Express POS's own display face — the wordmark and the
                // sign-in headline only (layouts/guest.blade.php), served
                // from public/fonts.
                'xpos-display': ['"Archivo Express"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Express POS (Solusys Digital) — the sign-in pages only,
                // never a business's own screens. From the logo system:
                // Midnight, Kente Gold, Forest Green, Signal Red; paper
                // and line are the sign-in screen's background and borders,
                // green-dark is Forest Green darkened for readable link text.
                xpos: {
                    midnight: '#101010',
                    gold: '#FCD116',
                    green: { DEFAULT: '#12A150', dark: '#0B7A3A' },
                    red: '#E23A3A',
                    paper: '#F4F2EE',
                    line: '#D6D1C4',
                    muted: '#9A968E',
                },
                // Brand palette. yellow (the primary colour) and red (the
                // accent) come from CSS variables set per business from its
                // Business profile (partials/brand-head, BusinessProfile);
                // the class names stay brand-yellow/brand-red so nothing
                // else changes. Defaults live in app.css.
                brand: {
                    yellow: {
                        light: 'rgb(var(--brand-primary-light) / <alpha-value>)',
                        DEFAULT: 'rgb(var(--brand-primary) / <alpha-value>)',
                        dark: 'rgb(var(--brand-primary-dark) / <alpha-value>)',
                    },
                    black: '#000000',
                    white: '#ffffff',
                    // Black or white, whichever reads on the business's
                    // primary/accent colour (BusinessProfile::cssVariables).
                    'on-primary': 'rgb(var(--brand-primary-ink) / <alpha-value>)',
                    'on-accent': 'rgb(var(--brand-accent-ink) / <alpha-value>)',
                    // The brand colours as text on white, darkened until readable.
                    'primary-text': 'rgb(var(--brand-primary-text) / <alpha-value>)',
                    'accent-text': 'rgb(var(--brand-accent-text) / <alpha-value>)',
                    // The third colour (2026-11-24): the website's header,
                    // home page, footer and loading screen; on-surface is
                    // readable text on it, highlight the main colour where
                    // it stands out on it (else on-surface).
                    surface: 'rgb(var(--brand-background) / <alpha-value>)',
                    'on-surface': 'rgb(var(--brand-background-ink) / <alpha-value>)',
                    highlight: 'rgb(var(--brand-highlight) / <alpha-value>)',
                    red: {
                        light: 'rgb(var(--brand-accent-light) / <alpha-value>)',
                        DEFAULT: 'rgb(var(--brand-accent) / <alpha-value>)',
                        dark: 'rgb(var(--brand-accent-dark) / <alpha-value>)',
                    },
                    gray: {
                        50: '#fafafa',
                        100: '#f0f0f0',
                        300: '#b3b3b3',
                        500: '#4d4d4d',
                        700: '#1a1a1a',
                        900: '#000000',
                    },
                },
            },
        },
    },

    plugins: [forms],
};
