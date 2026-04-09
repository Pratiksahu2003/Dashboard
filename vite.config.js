import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    build: {
        cssCodeSplit: true,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (!id.includes('node_modules')) return;
                    if (id.includes('quill') || id.includes('@vueup/vue-quill')) return 'vendor-quill';
                    if (id.includes('firebase')) return 'vendor-firebase';
                    if (id.includes('@inertiajs')) return 'vendor-inertia';
                    if (id.includes('sweetalert2')) return 'vendor-sweetalert';
                    if (id.includes('pusher-js') || id.includes('laravel-echo')) return 'vendor-echo';
                },
            },
        },
    },
    optimizeDeps: {
        include: ['vue', '@inertiajs/vue3', 'axios'],
    },
});
