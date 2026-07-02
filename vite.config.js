import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/mapa.js',
                'resources/js/vistoria-form.js',
                'resources/js/vistoria-show.js',
                'resources/js/vistoria-edit.js',
                'resources/js/dashboard.js',
                'resources/js/stack-projecao.js',
                'resources/js/admin-parametros.js',
                'resources/js/vistoria-index.js',
                'resources/js/ponto-edit.js',
                'resources/js/morador-form.js',
            ],
            refresh: true,
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    chartjs: ['chart.js'],
                },
            },
        },
    },
});
