import react from '@vitejs/plugin-react'
import { defineConfig, loadEnv } from 'vite'

// https://vite.dev/config/
export default defineConfig(({ command, mode }) => {
  const env = loadEnv(mode, process.cwd(), '')

  return {
    plugins: [react()],
    // Build : URL relatives, résolues par le <base href> qu'injecte le back (AppShell) → l'app
    // fonctionne à la racine d'un domaine comme dans un sous-dossier
    base: command === 'build' ? './' : '/',
    server: {
      proxy: {
        // En dev, /api est redirigé vers le back PHP (évite les soucis de CORS)
        '/api': {
          target: env.VITE_API_PROXY_TARGET || 'http://localhost:8000',
          changeOrigin: true,
        },
      },
    },
  }
})
