import type { FormEvent } from 'react'
import { t } from '../../i18n/i18n'
import SetupLayout from '../../layouts/SetupLayout'

// Mise en page seule : l'authentification n'est pas encore branchée
export default function LoginPage() {
  function handleSubmit(event: FormEvent) {
    event.preventDefault()
  }

  return (
    <SetupLayout
      subtitle={t('admin.subtitle')}
      footer={t('common.copyright', { year: new Date().getFullYear() })}
    >
      <h1>{t('admin.login.title')}</h1>

      <form onSubmit={handleSubmit}>
        <fieldset>
          <label>
            {t('admin.login.email')}
            <input type="email" required autoComplete="email" />
          </label>
          <label>
            {t('admin.login.password')}
            <input type="password" required autoComplete="current-password" />
          </label>
        </fieldset>

        <button type="submit">{t('admin.login.submit')}</button>
      </form>
    </SetupLayout>
  )
}
