import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import { resolve } from 'path';

export default defineConfig({
    plugins: [
        tailwindcss(),
    ],
    build: {
        // Output directly into assets/build
        outDir: 'assets/build',
        emptyOutDir: true,
        manifest: true,
        rollupOptions: {
            input: resolve(__dirname, 'assets/css/tailwind.css'),
        },
    },
    server: {
        port: 5173,
        strictPort: true,
        // Enable CORS so WordPress can load the assets from the Vite server
        cors: true,
        hmr: {
            protocol: 'ws',
            host: 'localhost'
        }
    }
});
