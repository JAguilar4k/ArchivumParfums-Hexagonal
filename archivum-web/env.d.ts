/// <reference types="vite/client" />

// Variables that Vite exposes to the client. Each one is declared in .env.example.
interface ImportMetaEnv {
  readonly VITE_API_URL: string
  readonly VITE_DEFAULT_BUSINESS_SLUG: string
  readonly VITE_STORE_WHATSAPP?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
