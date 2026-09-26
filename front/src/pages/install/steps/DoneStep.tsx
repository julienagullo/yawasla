import { boot } from '../../../app/boot'
import { t } from '../../../i18n/i18n'

export default function DoneStep() {
  return (
    <>
      <h1>{t('install.done.title')}</h1>
      <p>{t('install.done.intro')}</p>
      <a className="button" href={`${boot.basePath}/admin/login`}>
        {t('install.done.link')}
      </a>
    </>
  )
}
