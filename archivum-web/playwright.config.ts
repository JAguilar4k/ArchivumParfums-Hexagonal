import { defineConfig, devices } from '@playwright/test'

const origin = 'http://127.0.0.1:4179'
const output = 'node_modules/.tmp/playwright-dist'

export default defineConfig({
  testDir: './e2e',
  outputDir: './test-results',
  workers: 1,
  use: {
    ...devices['Desktop Chrome'],
    baseURL: origin,
    trace: 'retain-on-failure',
  },
  webServer: {
    command: `vite build --outDir ${output} && vite preview --outDir ${output} --host 127.0.0.1 --port 4179 --strictPort`,
    url: origin,
    env: {
      VITE_API_URL: `${origin}/api/v1`,
      VITE_DEFAULT_BUSINESS_SLUG: 'archivumparfums',
      VITE_STORE_WHATSAPP: '50600000000',
    },
  },
})
