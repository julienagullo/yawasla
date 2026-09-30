import { migrate } from '../../api/update'
import type { StatusResponse } from '../../api/status'
import { useAsyncAction } from '../../hooks/useAsyncAction'
import { t } from '../../i18n/i18n'
import SetupLayout from '../../layouts/SetupLayout'

// Migrations en attente (nouvelle version installée) : lancées par l'administrateur connecté.
// La page est rechargée ensuite pour relire l'état de l'application.
export default function UpdateRequiredPage({ status }: { status: StatusResponse }) {
  const { pending, error, run } = useAsyncAction()

  function handleMigrate() {
    void run(migrate).then((ok) => ok && window.location.reload())
  }

  return (
    <SetupLayout subtitle={t('admin.subtitle')}>
      <h1>{t('admin.update.title')}</h1>
      <p>
        {t('admin.update.text', {
          current: status.current_version ?? '?',
          target: status.target_version ?? '?',
        })}
      </p>

      {error && <p role="alert">{error}</p>}

      <button type="button" disabled={pending} onClick={handleMigrate}>
        {pending ? t('admin.update.submitting') : t('admin.update.submit')}
      </button>
    </SetupLayout>
  )
}
