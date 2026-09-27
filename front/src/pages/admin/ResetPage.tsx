import { useState, type FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { resetPassword } from '../../api/auth'
import { useAsyncAction } from '../../hooks/useAsyncAction'
import { t } from '../../i18n/i18n'
import SetupLayout from '../../layouts/SetupLayout'

const MIN_PASSWORD_LENGTH = 8

export default function ResetPage() {
  const [params] = useSearchParams()
  const token = params.get('token') ?? ''
  const { pending, error, setError, run } = useAsyncAction()
  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const [done, setDone] = useState(false)

  function handleSubmit(event: FormEvent) {
    event.preventDefault()

    if (password.length < MIN_PASSWORD_LENGTH) {
      setError(() => t('admin.reset.passwordTooShort', { min: MIN_PASSWORD_LENGTH }))
      return
    }
    if (password !== confirmation) {
      setError(() => t('admin.reset.passwordMismatch'))
      return
    }

    void run(() => resetPassword(token, password)).then((ok) => ok && setDone(true))
  }

  return (
    <SetupLayout
      subtitle={t('admin.subtitle')}
      footer={t('common.copyright', { year: new Date().getFullYear() })}
    >
      <h1>{t('admin.reset.title')}</h1>

      {done ? (
        <>
          <p>{t('admin.reset.done')}</p>
          <p className="form-link">
            <Link to="/admin/login">{t('admin.reset.backToLogin')}</Link>
          </p>
        </>
      ) : (
        <>
          <p>{t('admin.reset.intro')}</p>

          <form onSubmit={handleSubmit}>
            <fieldset disabled={pending}>
              <label>
                {t('admin.reset.password')}
                <input
                  type="password"
                  required
                  autoComplete="new-password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                />
              </label>
              <label>
                {t('admin.reset.confirmation')}
                <input
                  type="password"
                  required
                  autoComplete="new-password"
                  value={confirmation}
                  onChange={(e) => setConfirmation(e.target.value)}
                />
              </label>
            </fieldset>

            {error && <p role="alert">{error}</p>}

            <button type="submit" disabled={pending}>
              {pending ? t('admin.reset.submitting') : t('admin.reset.submit')}
            </button>
          </form>
        </>
      )}
    </SetupLayout>
  )
}
