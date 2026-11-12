import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // pwa.js is its own entry rather than an import of app.js: it
            // registers the service worker and runs the install cards, none of
            // which the storefront's AJAX layer knows or needs to know about.
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/pwa.js'],
            refresh: true,
            fonts: [
                bunny('Cairo', {
                    weights: [400, 500, 600, 700, 800],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: Number(process.env.VITE_PORT ?? 5175),
        strictPort: true,
        // The browser reaches the dev server through the published host port.
        hmr: { host: 'localhost', port: Number(process.env.VITE_PORT ?? 5175) },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
