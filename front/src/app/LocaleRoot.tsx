import { Outlet } from 'react-router-dom'
import { useLocale } from '../i18n/useLocale'

// Route racine du routeur (router.tsx). Les éléments de route y sont créés une seule fois : quand
// App se re-rend au changement de langue, React les voit inchangés et ne les re-rend pas. La clé
// force le remontage des pages dans la nouvelle langue (les champs en cours de saisie sont
// réinitialisés, changement rare).
export default function LocaleRoot() {
  const locale = useLocale()

  return <Outlet key={locale} />
}
