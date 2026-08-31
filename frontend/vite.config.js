import { defineConfig } from 'vitest/config'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      // Toute requete vers /api est transferee vers Symfony, mais le
      // navigateur ne voit qu'une seule origine (localhost:5173) : les
      // cookies HttpOnly ne posent alors plus aucun probleme cross-site,
      // ni en dev ni en prod (ou c'est Nginx qui joue ce role).
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
      // Seuls les chemins VichUploader (produits/marques) sont proxifies
      // vers Symfony. Les images statiques (hero, logo, auth) restent
      // servies depuis frontend/public/images/.
      '/images/products': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
      '/images/brands': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
    },
  },
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.js'],
    pool: 'threads',
  },
})
