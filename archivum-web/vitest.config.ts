import { fileURLToPath } from 'node:url'
import { mergeConfig, defineConfig, configDefaults } from 'vitest/config'
import viteConfig from './vite.config.ts'

export default mergeConfig(
  viteConfig,
  defineConfig({
    test: {
      environment: 'jsdom',
      // Node 25 and later have their own localStorage, which hides the one of jsdom and fails without a file.
      execArgv: ['--no-experimental-webstorage'],
      exclude: [...configDefaults.exclude, 'e2e/**'],
      root: fileURLToPath(new URL('./', import.meta.url)),
      // The tests never depend on a local .env file.
      env: { VITE_API_URL: 'https://api.example.test/api/v1', VITE_DEFAULT_BUSINESS_SLUG: 'archivumparfums' },
    },
  }),
)
