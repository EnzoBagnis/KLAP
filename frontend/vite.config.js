import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';
import { htaccessPlugin } from './build/htaccess.js';

export default defineConfig(({ mode }) => ({
  // .htaccess pour Apache (Alwaysdata) : routes React, en-têtes de sécurité, cache (#203).
  plugins: [react(), htaccessPlugin(loadEnv(mode, process.cwd(), 'VITE_').VITE_API_URL)],
  server: {
    // Écoute sur toutes les interfaces pour être joignable depuis un conteneur.
    host: true,
    port: 5173,
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: './src/setupTests.js',
    css: false,
  },
}));
