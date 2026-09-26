import { isRouteErrorResponse, Link, useRouteError } from 'react-router-dom'
import { t } from '../i18n/i18n'
import SetupLayout from '../layouts/SetupLayout'

// Page affichée à la place de l'application en cas d'erreur de rendu, ou d'URL inconnue (404)
export default function ErrorPage() {
  const error = useRouteError()
  const notFound = isRouteErrorResponse(error) && error.status === 404

  if (!notFound) {
    console.error(error)
  }

  return (
    <SetupLayout>
      <h1>{notFound ? t('errors.notFoundTitle') : t('errors.pageTitle')}</h1>
      <p>{notFound ? t('errors.notFoundText') : t('errors.pageText')}</p>
      <Link className="button" to="/">
        {t('errors.backHome')}
      </Link>
    </SetupLayout>
  )
}
