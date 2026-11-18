import { onBeforeUnmount, ref, watch } from 'vue'

export const THEME_STORAGE_KEY = 'archivum.theme'
type Theme = 'light' | 'dark'

function savedTheme(): Theme | null {
  try {
    const stored = localStorage.getItem(THEME_STORAGE_KEY)
    return stored === 'dark' || stored === 'light' ? stored : null
  } catch {
    return null
  }
}

export function useTheme() {
  const scheme = window.matchMedia?.('(prefers-color-scheme: dark)')
  let preference = savedTheme()
  const dark = ref(preference ? preference === 'dark' : (scheme?.matches ?? false))

  watch(dark, (active) => {
    document.documentElement.dataset.theme = active ? 'dark' : 'light'
  }, { immediate: true, flush: 'sync' })

  function updateSystemTheme(event: MediaQueryListEvent): void {
    if (!preference) dark.value = event.matches
  }

  scheme?.addEventListener('change', updateSystemTheme)
  onBeforeUnmount(() => scheme?.removeEventListener('change', updateSystemTheme))

  function toggleTheme(): void {
    dark.value = !dark.value
    preference = dark.value ? 'dark' : 'light'
    try {
      localStorage.setItem(THEME_STORAGE_KEY, preference)
    } catch {
      // The selection lasts for the current tab when storage is unavailable.
    }
  }

  return { dark, toggleTheme }
}
