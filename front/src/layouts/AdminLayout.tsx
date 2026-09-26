import { NavLink, Outlet } from 'react-router-dom'
import LanguageSelect from '../components/LanguageSelect'
import Logo from '../components/Logo'
import { t } from '../i18n/i18n'
import styles from './AdminLayout.module.css'

const navClass = ({ isActive }: { isActive: boolean }) =>
  isActive ? `${styles.navLink} ${styles.active}` : styles.navLink

// Back-office : barre latérale (barre du haut sur petit écran) et contenu.
// Les entrées de menu s'ajouteront avec les modules (organisme, annonces, médias).
export default function AdminLayout() {
  return (
    <div className={styles.shell}>
      <aside className={styles.sidebar}>
        <div className={styles.brand}>
          <Logo height={36} />
          <span className={styles.brandLabel}>{t('admin.subtitle')}</span>
        </div>

        <nav className={styles.nav} aria-label={t('admin.navLabel')}>
          <NavLink to="/admin" end className={navClass}>
            {t('admin.nav.dashboard')}
          </NavLink>
        </nav>

        <div className={styles.footer}>
          <LanguageSelect />
          <NavLink to="/" className={styles.viewSite}>
            {t('admin.viewSite')}
          </NavLink>
        </div>
      </aside>

      <main className={styles.main}>
        <Outlet />
      </main>
    </div>
  )
}
