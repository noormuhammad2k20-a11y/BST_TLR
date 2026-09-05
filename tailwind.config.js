/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/**/*.php',
    ],

    /**
     * Class names that are assembled at runtime and therefore invisible to the
     * scanner. Keep this list in step with any new dynamic class strings.
     *
     * Source: the Quick Action tiles in dashboard.blade.php build their colour
     * as `bg-${o.c}-50 text-${o.c}-600`.
     */
    safelist: [
        {
            pattern: /^(bg|text|border)-(indigo|sky|red|purple|amber|emerald|slate|rose|pink|blue|green|orange)-(50|100|200|400|500|600|700)$/,
        },
        {
            pattern: /^(from|to)-(indigo|emerald|rose|sky|purple|teal|pink|blue)-(500|600)$/,
        },
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },
        },
    },

    plugins: [],
};
