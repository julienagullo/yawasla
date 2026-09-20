export type Theme = 'light' | 'dark'

// Même clé que le site vitrine : le choix est partagé entre les deux quand ils sont sur le même domaine
export const THEME_STORAGE_KEY = 'yawasla-theme'

export function currentTheme(): Theme {
  return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light'
}

export function applyTheme(theme: Theme) {
  document.documentElement.setAttribute('data-theme', theme)

  try {
    localStorage.setItem(THEME_STORAGE_KEY, theme)
  } catch {
    // stockage indisponible (navigation privée, etc.) : le choix vaut pour la session en cours
  }
}
