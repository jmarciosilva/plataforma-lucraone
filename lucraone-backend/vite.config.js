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
                // Corpo e interface
                bunny('Karla', {
                    weights: [400, 500, 600, 700],
                }),
                // Logo e títulos de display
                bunny('Archivo', {
                    weights: [600, 700],
                }),
                // Valores monetários e KPIs — mono alinha as casas decimais
                bunny('Martian Mono', {
                    weights: [400, 500],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
