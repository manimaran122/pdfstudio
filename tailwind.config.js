import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                // Stellar PDF v2 (stellar-pdf-demo-v2.html) uses one family throughout.
                body: ['"Schibsted Grotesk"', ...defaultTheme.fontFamily.sans],
                display: ['"Schibsted Grotesk"', ...defaultTheme.fontFamily.sans],
                mono: ['"Schibsted Grotesk"', ...defaultTheme.fontFamily.sans],
            },
            // Stellar PDF v2 palette (see stellar-pdf-demo-v2.html :root)
            colors: {
                ink: { DEFAULT: '#1B1D22', soft: '#3F4249' },
                muted: { DEFAULT: '#5C6068', light: '#8A8E96', inverse: '#B9BCC3' },
                paper: '#F7F7F5',
                sand: { DEFAULT: '#F0F0EC', dark: '#E9E9E4' },
                line: { DEFAULT: '#E6E6E1', strong: '#D5D5CE', dashed: '#D5D5CE' },
                accent: { DEFAULT: '#C8322B', dark: '#A3261F', soft: '#FBE9E7', faded: '#DDA7A3' },
                focus: '#2563EB',
                success: { DEFAULT: '#15803D', soft: '#DDF3E4' },
            },
        },
    },

    plugins: [forms, typography],
};
