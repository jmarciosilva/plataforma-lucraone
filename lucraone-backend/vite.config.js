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
    /*
    | Rodando no container, o Vite precisa escutar em todas as interfaces para
    | ser alcançável de fora dele — mas o endereço gravado em public/hot, que o
    | navegador lê, tem que continuar sendo localhost. Daí host e hmr.host
    | apontarem para lugares diferentes.
    |
    | Fora do Docker nenhuma das variáveis existe e tudo cai no padrão de
    | sempre: localhost, sem polling.
    */
    server: {
        host: process.env.VITE_DEV_HOST || 'localhost',
        port: 5173,
        strictPort: true,
        hmr: {
            host: process.env.VITE_HMR_HOST || 'localhost',
        },
        watch: {
            // Bind mount do Windows não entrega eventos de inotify ao
            // container: sem polling o HMR fica mudo. No host, polling seria
            // só CPU gasta à toa.
            usePolling: process.env.VITE_USE_POLLING === 'true',
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
