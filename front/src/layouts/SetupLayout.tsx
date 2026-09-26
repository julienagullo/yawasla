import type { ReactNode } from 'react'
import LanguageSelect from '../components/LanguageSelect'
import Logo from '../components/Logo'
import styles from './SetupLayout.module.css'
import { t } from '../i18n/i18n'

interface Props {
  // Sous-titre affiché sous le nom (ex. « Assistant d'installation »)
  subtitle?: string
  children: ReactNode
  // Affiché sous la carte (ex. copyright)
  footer?: ReactNode
}

// Section centrée dans la page, utilisée par l'assistant d'installation et les écrans d'état
export default function SetupLayout({ subtitle, children, footer }: Props) {
  return (
    <main className={styles.setup}>
      <section className={styles.card}>
        <header className={styles.brand}>
          <Logo />
          <div>
            <p className={styles.brandName}>Yawasla</p>
            <p className={styles.brandTagline}>
              {subtitle ?? t('app.tagline')}
            </p>
          </div>
          <LanguageSelect className={styles.language} />
        </header>
        {children}
      </section>
      {footer && <footer className={styles.footer}>{footer}</footer>}
    </main>
  )
}
