import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    }
  },
  base: '/app/',
  build: {
    outDir: '../public_html/app',
    emptyOutDir: true,
    rollupOptions: {
      output: {
        manualChunks: {
          'primevue': ['primevue'],
          'vendor': ['vue', 'vue-router', 'pinia', 'axios']
        }
      }
    }
  },
  server: {
    proxy: {
      '/api': {
        target: 'http://localhost:8080',
        changeOrigin: true
      }
    }
  }
})
