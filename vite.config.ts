import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [
    laravel({ input: ['resources/js/main.ts'], refresh: true }),
    vue({ template: { transformAssetUrls: { base: null, includeAbsolute: false } } }),
  ],
  server: { watch: { ignored: ['**/storage/framework/views/**'] } },
  test: { environment: 'node', include: ['resources/js/**/*.test.ts'] },
})
