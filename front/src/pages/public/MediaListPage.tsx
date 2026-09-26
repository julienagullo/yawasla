import EmptyState from '../../components/EmptyState'
import { t } from '../../i18n/i18n'

export default function MediaListPage() {
  return (
    <>
      <h1>{t('public.mediaTitle')}</h1>
      <EmptyState>{t('public.noMedia')}</EmptyState>
    </>
  )
}
