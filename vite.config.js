import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
    publicDir: false,
    build: {
        outDir: 'public/build',
        manifest: 'manifest.json',
        emptyOutDir: true,
        rollupOptions: {
            input: 'resources/js/app.js',
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: 'content-distribution-system.ddev.site',
            protocol: 'ws',
        },
        proxy: {
            '/api': {
                target: 'https://content-distribution-system.ddev.site',
                changeOrigin: true,
                secure: false,
                ws: true,
                configure: (proxy, options) => {
                    proxy.on('error', (err, req, res) => {
                        console.log('Proxy error:', err);
                    });
                    proxy.on('proxyReq', (proxyReq, req, res) => {
                        console.log('Proxying request:', req.method, req.url);
                    });
                },
            },
        },
    },
});
