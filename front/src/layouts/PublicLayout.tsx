import { NavLink, Outlet } from 'react-router-dom'
import LanguageSelect from '../components/LanguageSelect'
import Logo from '../components/Logo'
import { t } from '../i18n/i18n'
import Trans from '../i18n/Trans'
import styles from './PublicLayout.module.css'

const navClass = ({ isActive }: { isActive: boolean }) =>
  isActive ? `${styles.navLink} ${styles.active}` : styles.navLink

// Front-office : en-tête (logo, navigation, langue), contenu, pied de page
export default function PublicLayout() {
  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div className={styles.inner}>
          <NavLink to="/" className={styles.brand}>
            <Logo height={36} />
          </NavLink>
          <nav className={styles.nav} aria-label={t('public.navLabel')}>
            <NavLink to="/" end className={navClass}>
              {t('public.nav.news')}
            </NavLink>
            <NavLink to="/medias" className={navClass}>
              {t('public.nav.media')}
            </NavLink>
          </nav>
          <LanguageSelect className={styles.language} />
        </div>
      </header>

      <main className={styles.main}>
        <Outlet />
      </main>

      <footer className={styles.footer}>
        <Trans k="public.poweredBy" components={{ link: <a href="https://yawasla.org" /> }} />
      </footer>
    </div>
  )
}
