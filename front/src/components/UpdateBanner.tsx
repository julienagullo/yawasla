import { installRelease, type Release } from '../api/update'
import { useAsyncAction } from '../hooks/useAsyncAction'
import { t } from '../i18n/i18n'
import styles from './UpdateBanner.module.css'

// Nouvelle version disponible : installée en un clic. La page est ensuite rechargée, l'écran de
// mise à jour de la base (UpdateRequiredPage) prend le relais s'il y a des migrations.
export default function UpdateBanner({ release }: { release: Release | null }) {
  const { pending, error, run } = useAsyncAction()

  if (release === null) {
    return null
  }

  function handleInstall() {
    void run(installRelease).then((ok) => ok && window.location.reload())
  }

  return (
    <section className={styles.banner} aria-live="polite">
      <p>{t('admin.release.available', { version: release.version })}</p>

      {release.compatible ? (
        <button type="button" disabled={pending} onClick={handleInstall}>
          {pending ? t('admin.release.installing') : t('admin.release.install')}
        </button>
      ) : (
        <p>{t('admin.release.incompatible')}</p>
      )}

      {error && <p role="alert">{error}</p>}
    </section>
  )
}
