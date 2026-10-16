import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Mobi Boost build configuration.
// - Dev: vite serves the React app on :5173 and proxies /api to the PHP dev server (:8000).
// - Production: `npm run build` outputs static files into /dist, which are uploaded
//   to Hostinger's public_html alongside the /api folder (see README.md).
export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
      // Uploaded media (product images / ads) is written to ./uploads and served
      // by the PHP server; proxy it in dev so <img>/<video> resolve like prod.
      '/uploads': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
    // deploy.ps1 writes mobiboost-deploy.zip / mobiboost-frontend.zip into the
    // project root; watching those binaries while they are being written crashes
    // Vite on Windows (EBUSY). Ignore every zip in the tree.
    watch: {
      ignored: ['**/*.zip'],
    },
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
  },
});
