import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import plugin from 'tailwindcss/plugin';

// Flexoki palette (https://stephango.com/flexoki, MIT), the base of Wealthfolio's look.
// Existing slate/gray/emerald/... classes are remapped onto it, so views need no edits.
const base = {
    50: '#F2F0E5', 100: '#E6E4D9', 150: '#DAD8CE', 200: '#CECDC3', 300: '#B7B5AC',
    400: '#9F9D96', 500: '#878580', 600: '#6F6E69', 700: '#575653', 800: '#403E3C',
    850: '#343331', 900: '#282726', 950: '#1C1B1A',
};

const green = {
    50: '#EDEECF', 100: '#DDE2B2', 150: '#CDD597', 200: '#BEC97E', 300: '#A0AF54',
    400: '#879A39', 500: '#768D21', 600: '#66800B', 700: '#536907', 800: '#3D4C07',
    850: '#313D07', 900: '#252D09', 950: '#1A1E0C',
};

const red = {
    50: '#FFE1D5', 100: '#FFCABB', 150: '#FDB2A2', 200: '#F89A8A', 300: '#E8705F',
    400: '#D14D41', 500: '#C03E35', 600: '#AF3029', 700: '#942822', 800: '#6C201C',
    850: '#551B18', 900: '#3E1715', 950: '#261312',
};

const yellow = {
    50: '#FAEEC6', 100: '#F6E2A0', 150: '#F1D67E', 200: '#ECCB60', 300: '#DFB431',
    400: '#D0A215', 500: '#BE9207', 600: '#AD8301', 700: '#8E6B01', 800: '#664D01',
    850: '#503D02', 900: '#3A2D04', 950: '#241E08',
};

const blue = {
    50: '#E1ECEB', 100: '#C6DDE8', 150: '#ABCFE2', 200: '#92BFDB', 300: '#66A0C8',
    400: '#4385BE', 500: '#3171B2', 600: '#205EA6', 700: '#1A4F8C', 800: '#163B66',
    850: '#133051', 900: '#12253B', 950: '#101A24',
};

// Dark mode mirrors a scale: tints become shades, so bg-x-50 stays "subtle" and text-x-700 stays "strong".
const mirror = (scale) => Object.fromEntries(Object.keys(scale).map((k) => [k, scale[1000 - k]]));

// Flexoki mid tones are lighter than Tailwind's, so some steps shift to keep WCAG AA (4.5:1) text.
const palettes = {
    neutral: {
        light: { ...base, 500: base[600], 600: base[700] },
        dark: {
            50: '#100F0F', 100: '#282726', 150: '#343331', 200: '#403E3C', 300: '#575653',
            400: '#6F6E69', 500: '#9F9D96', 600: '#B7B5AC', 700: '#CECDC3', 800: '#DAD8CE',
            850: '#E6E4D9', 900: '#E6E4D9', 950: '#F2F0E5',
        },
    },
    brand: {
        light: { ...green, 500: green[600], 600: green[700], 700: green[800], 800: green[850], 900: green[900] },
        dark: mirror(green),
    },
    red: { light: red, dark: { ...mirror(red), 600: red[300], 700: red[200] } },
    yellow: { light: yellow, dark: mirror(yellow) },
    blue: { light: blue, dark: mirror(blue) },
    ink: { light: { DEFAULT: '#100F0F' }, dark: { DEFAULT: '#CECDC3' } },
    paper: { light: { DEFAULT: '#FFFCF0' }, dark: { DEFAULT: '#1C1B1A' } },
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
