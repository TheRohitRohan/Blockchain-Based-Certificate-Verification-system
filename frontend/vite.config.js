import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');

  // If VITE_API_URL is set, Axios talks to it directly — no proxy needed.
  // If not set, the proxy below forwards /api → the dummy backend (or cloud).
  const proxyTarget = env.VITE_PROXY_TARGET || 'http://localhost:8001';

  return {
    plugins: [
      react({
        babel: {
          plugins: [['babel-plugin-react-compiler']],
        },
      }),
    ],
    server: {
      proxy: {
        // Only active when VITE_API_URL is NOT set (i.e. using /api path)
        '/api': {
          target: proxyTarget,
          changeOrigin: true,
          // Do NOT rewrite /api prefix — backend expects /api/...
        },
      },
    },
  };
})
