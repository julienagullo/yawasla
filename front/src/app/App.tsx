import { RouterProvider } from 'react-router-dom'
import ThemeToggle from '../components/ThemeToggle'
import AppGate from './AppGate'
import { router } from './router'
import { useLocale } from '../i18n/useLocale'

export default function App() {
  // Changement de langue : re-rend l'arbre hors routeur (AppGate, assistant d'installation) sans le
  // remonter, l'état des formulaires est conservé. Les pages du routeur : voir LocaleRoot (router.tsx)
  useLocale()

  return (
    <>
      <AppGate>
        <RouterProvider router={router} />
      </AppGate>
      <ThemeToggle />
    </>
  )
}
