/// <reference types="vite/client" />

// Variables that Vite exposes to the client. Each one is declared in .env.example.
interface ImportMetaEnv {
  readonly VITE_API_URL: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
