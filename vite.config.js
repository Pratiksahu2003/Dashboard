import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

const isProd = process.env.NODE_ENV === 'production';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: !isProd,
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
        // Target modern browsers — smaller, faster output.
        target: 'es2020',

        // No source maps in production — prevents code exposure.
        sourcemap: false,

        // Minify with oxc (built-in to this Vite version — faster than esbuild/terser).
        minify: true,

        // Raise chunk warning threshold (large vendor chunks are expected).
        chunkSizeWarningLimit: 600,

        cssCodeSplit: true,

        rollupOptions: {
            output: {
                // Deterministic content-hashed filenames for long-term browser caching.
                entryFileNames:  'assets/[name]-[hash].js',
                chunkFileNames:  'assets/[name]-[hash].js',
                assetFileNames:  'assets/[name]-[hash][extname]',

                manualChunks(id) {
                    if (!id.includes('node_modules')) return;

                    // Heavy, rarely-changing vendor chunks — cached independently.
                    if (id.includes('quill') || id.includes('@vueup/vue-quill'))
                        return 'vendor-quill';
                    if (id.includes('firebase'))
                        return 'vendor-firebase';
                    if (id.includes('@inertiajs'))
                        return 'vendor-inertia';
                    if (id.includes('sweetalert2'))
                        return 'vendor-sweetalert';
                    if (id.includes('pusher-js') || id.includes('laravel-echo'))
                        return 'vendor-echo';

                    // Vue core — tiny, always needed.
                    if (id.includes('/vue/') || id.includes('/vue-demi/') || id.includes('/pinia/'))
                        return 'vendor-vue';
                },
            },
        },
    },

    // Pre-bundle critical deps for faster dev server startup.
    optimizeDeps: {
        include: ['vue', '@inertiajs/vue3', 'axios', 'pinia'],
    },
});
