import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import '@fontsource-variable/fraunces'
import '@fontsource-variable/inter'
import './styles/index.css'
import { detectLocale, setLocale } from './i18n/i18n'
import App from './app/App'

// Langue du navigateur pour l'instant ; à terme User.locale (admin) et Organization.locale (public)
setLocale(detectLocale())

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
