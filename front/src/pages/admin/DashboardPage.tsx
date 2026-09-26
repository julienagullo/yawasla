import { t } from '../../i18n/i18n'

export default function DashboardPage() {
  return (
    <>
      <h1>{t('admin.dashboard.title')}</h1>
      <p>{t('admin.dashboard.intro')}</p>
    </>
  )
}
