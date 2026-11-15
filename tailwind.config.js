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
            },
            colors: {
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
