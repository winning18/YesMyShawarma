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
                // Brand palette as given, with light/dark shades added where
                // hover states and subtle backgrounds need them. brand.red.dark
                // is literally the given "Accent 3" maroon (#9a0200) doing
                // double duty as red's dark/hover shade.
                brand: {
                    yellow: {
                        light: '#fff9b3',
                        DEFAULT: '#fffa00',
                        dark: '#ccc800',
                    },
                    black: '#000000',
                    white: '#ffffff',
                    red: {
                        light: '#ffe6e6',
                        DEFAULT: '#ff0000',
                        dark: '#9a0200',
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
