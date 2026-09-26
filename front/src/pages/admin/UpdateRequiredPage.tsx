import type { StatusResponse } from '../../api/status'
import { t } from '../../i18n/i18n'
import SetupLayout from '../../layouts/SetupLayout'

// Migrations en attente. Le lancement de la mise à jour (réservé aux utilisateurs connectés)
// arrivera avec l'authentification.
export default function UpdateRequiredPage({ status }: { status: StatusResponse }) {
  return (
    <SetupLayout subtitle={t('admin.subtitle')}>
      <h1>{t('admin.update.title')}</h1>
      <p>
        {t('admin.update.text', {
          current: status.current_version ?? '?',
          target: status.target_version ?? '?',
        })}
      </p>
    </SetupLayout>
  )
}
