import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import plugin from 'tailwindcss/plugin';

// Calm neutral palette inspired by Wealthfolio: cool off-white page, white cards, sage/brick accents.
// Existing slate/gray/emerald/... classes are remapped onto it, so views need no edits.
const neutral = {
    50: '#F7F7F6', 100: '#EFEFEE', 150: '#E7E7E6', 200: '#E2E2E0', 300: '#D0D0CE',
    400: '#A3A3A1', 500: '#6B6B6A', 600: '#585857', 700: '#454544', 800: '#3A3A39',
    850: '#2F2F2E', 900: '#262626', 950: '#171717',
};

const green = {
    50: '#EEF5F0', 100: '#DCEBE0', 150: '#C9E0D0', 200: '#B3D4BC', 300: '#8DBD9A',
    400: '#6BA77A', 500: '#4E8E5E', 600: '#3B7A4A', 700: '#2F6140', 800: '#254C33',
    850: '#1F3F2B', 900: '#1A3324', 950: '#10241A',
};

const red = {
    50: '#FBEEEC', 100: '#F6DCD7', 150: '#F0C8C1', 200: '#EAB3AA', 300: '#E8988D',
    400: '#E07A6C', 500: '#C9554A', 600: '#B03A2E', 700: '#8E2F25', 800: '#6B241D',
    850: '#571E18', 900: '#431813', 950: '#2A100D',
};

const yellow = {
    50: '#FAF5E8', 100: '#F3E9CF', 150: '#EDDFB9', 200: '#E7D4A3', 300: '#D6BA74',
    400: '#C4A157', 500: '#A88944', 600: '#8A6F35', 700: '#6C5729', 800: '#51411F',
    850: '#45371A', 900: '#382D16', 950: '#221B0D',
};

const blue = {
    50: '#EEF2F8', 100: '#DCE4F0', 150: '#CBD7E8', 200: '#BACAE1', 300: '#93AACD',
    400: '#6F8CB8', 500: '#56729F', 600: '#465D84', 700: '#384A69', 800: '#2B3850',
    850: '#253045', 900: '#1F2839', 950: '#131924',
};

// Muted categorical colors for charts (slate blue, sand, lavender, terracotta, sage, rose, teal, grey).
const chart = {
    1: '#5B7AA8', 2: '#B99F64', 3: '#9A8BC0', 4: '#B3705A',
    5: '#6E9A7E', 6: '#C07B9B', 7: '#659892', 8: '#A3A3A1',
};

// Dark mode mirrors a scale: tints become shades, so bg-x-50 stays "subtle" and text-x-700 stays "strong".
const mirror = (scale) => Object.fromEntries(Object.keys(scale).map((k) => [k, scale[1000 - k]]));

const palettes = {
    neutral: {
        light: neutral,
        dark: {
            50: '#0C0C0C', 100: '#222222', 150: '#2A2A2A', 200: '#2E2E2E', 300: '#3D3D3D',
            400: '#5C5C5C', 500: '#A3A3A3', 600: '#B8B8B8', 700: '#D4D4D4', 800: '#E0E0E0',
            850: '#E8E8E8', 900: '#E8E8E8', 950: '#F5F5F5',
        },
    },
    brand: { light: green, dark: mirror(green) },
    red: { light: red, dark: mirror(red) },
    yellow: { light: yellow, dark: mirror(yellow) },
    blue: { light: blue, dark: mirror(blue) },
    chart: { light: chart, dark: chart },
    ink: { light: { DEFAULT: '#171717' }, dark: { DEFAULT: '#DADADA' } },
    paper: { light: { DEFAULT: '#FFFFFF' }, dark: { DEFAULT: '#171717' } },
};

const channels = (hex) => [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16)).join(' ');

const cssVars = (mode) => Object.fromEntries(
    Object.entries(palettes).flatMap(([name, p]) =>
        Object.entries(p[mode]).map(([k, hex]) => [`--c-${name}-${k}`, channels(hex)])),
);

const color = (name) => Object.fromEntries(
    Object.keys(palettes[name].light).map((k) => [k, `rgb(var(--c-${name}-${k}) / <alpha-value>)`]),
);

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                white: color('paper').DEFAULT,
                black: color('ink').DEFAULT,
                slate: color('neutral'),
                gray: color('neutral'),
                emerald: color('brand'),
                green: color('brand'),
                red: color('red'),
                amber: color('yellow'),
                yellow: color('yellow'),
                sky: color('blue'),
                blue: color('blue'),
            },
            boxShadow: {
                sm: '0 1px 2px 0 rgb(0 0 0 / 0.04), 0 2px 8px -2px rgb(0 0 0 / 0.06)',
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [
        forms,
        plugin(({ addBase }) => addBase({
            // data-theme (set from Settings) overrides the system setting; without it the system decides.
            ':root': { ...cssVars('light'), colorScheme: 'light dark' },
            '@media (prefers-color-scheme: dark)': { ':root:not([data-theme="light"])': cssVars('dark') },
            ':root[data-theme="dark"]': { ...cssVars('dark'), colorScheme: 'dark' },
            ':root[data-theme="light"]': { colorScheme: 'light' },
            // @tailwindcss/forms hardcodes #fff for field backgrounds and focus ring offsets.
            // :root + :not(:checked) outranks the plugin's [type=...] rules but not its :checked fill.
            ':root :is(input, select, textarea):not(:checked)': {
                backgroundColor: 'rgb(var(--c-paper-DEFAULT))',
            },
            '*': { '--tw-ring-offset-color': 'rgb(var(--c-paper-DEFAULT))' },
        })),
    ],
};
