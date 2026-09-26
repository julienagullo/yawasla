import { useSyncExternalStore } from 'react'
import { currentLocale, subscribeLocale, type Locale } from './i18n'

// Langue courante ; le composant est re-rendu quand elle change
export function useLocale(): Locale {
  return useSyncExternalStore(subscribeLocale, currentLocale)
}
