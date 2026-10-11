import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Cairo', {
                    weights: [400, 500, 600],
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
