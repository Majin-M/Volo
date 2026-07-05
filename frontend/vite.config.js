import { defineConfig } from 'vite'
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
    },
  },
})
