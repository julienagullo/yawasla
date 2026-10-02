import { useEffect, useState } from 'react'
import { Navigate, NavLink, Outlet, useNavigate } from 'react-router-dom'
import { logout } from '../api/auth'
import { fetchRelease, type ReleaseResponse } from '../api/update'
import { useAppStatus } from '../app/appStatus'
import LanguageSelect from '../components/LanguageSelect'
import Logo from '../components/Logo'
import UpdateBanner from '../components/UpdateBanner'
import { useAsyncAction } from '../hooks/useAsyncAction'
import { useCurrentUser } from '../hooks/useCurrentUser'
import { t } from '../i18n/i18n'
import SetupLayout from './SetupLayout'
import styles from './AdminLayout.module.css'
import UpdateRequiredPage from '../pages/admin/UpdateRequiredPage'

const navClass = ({ isActive }: { isActive: boolean }) =>
  isActive ? `${styles.navLink} ${styles.active}` : styles.navLink

// Back-office : barre latérale (barre du haut sur petit écran) et contenu.
// Les entrées de menu s'ajouteront avec les modules (organisme, annonces, médias).
export default function AdminLayout() {
  const status = useAppStatus()
  const user = useCurrentUser()
  const navigate = useNavigate()
  const { pending, run } = useAsyncAction()
  const [release, setRelease] = useState<ReleaseResponse | null>(null)
  const ready = Boolean(user) && status?.status !== 'update_required'

  // Version installée et nouvelle version éventuelle, une fois l'admin connecté (route réservée aux admins).
  // Échec silencieux : l'administration ne dépend pas du serveur de distribution
  useEffect(() => {
    if (!ready) {
      return
    }

    let active = true
    fetchRelease()
      .then((r) => active && setRelease(r))
      .catch(() => {})

    return () => {
      active = false
    }
  }, [ready])

  if (user === undefined) {
    return (
      <SetupLayout subtitle={t('admin.subtitle')}>
        <p>{t('common.loading')}</p>
      </SetupLayout>
    )
  }

  // Non connecté (ou rôle sans accès) : vers la page de connexion
  if (user === null) {
    return <Navigate to="/admin/login" replace />
  }

  function handleLogout() {
    void run(logout).then((ok) => ok && navigate('/admin/login', { replace: true }))
  }

  // Migrations en attente : l'administration est remplacée par l'écran de mise à jour
  if (status?.status === 'update_required') {
    return <UpdateRequiredPage status={status} />
  }

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
          <div className={styles.account}>
            <span className={styles.userName}>{user.display_name}</span>
            <button type="button" className={styles.logout} disabled={pending} onClick={handleLogout}>
              {t('admin.logout')}
            </button>
          </div>
          <LanguageSelect />
          {release && (
            <span className={styles.version}>{t('admin.version', { version: release.current_version })}</span>
          )}
        </div>
      </aside>

      <main className={styles.main}>
        <UpdateBanner release={release?.release ?? null} />
        <Outlet />
      </main>
    </div>
  )
}
