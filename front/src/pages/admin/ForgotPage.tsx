import { useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { requestReset } from '../../api/auth'
import { useAsyncAction } from '../../hooks/useAsyncAction'
import { t } from '../../i18n/i18n'
import SetupLayout from '../../layouts/SetupLayout'

export default function ForgotPage() {
  const { pending, error, run } = useAsyncAction()
  const [email, setEmail] = useState('')
  const [sent, setSent] = useState(false)

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    void run(() => requestReset(email)).then((ok) => ok && setSent(true))
  }

  return (
    <SetupLayout
      subtitle={t('admin.subtitle')}
      footer={t('common.copyright', { year: new Date().getFullYear() })}
    >
      <h1>{t('admin.forgot.title')}</h1>

      {sent ? (
        <>
          <p>{t('admin.forgot.done')}</p>
          <p className="form-link">
            <Link to="/admin/login">{t('admin.forgot.backToLogin')}</Link>
          </p>
        </>
      ) : (
        <>
          <p>{t('admin.forgot.intro')}</p>

          <form onSubmit={handleSubmit}>
            <fieldset disabled={pending}>
              <label>
                {t('admin.forgot.email')}
                <input
                  type="email"
                  required
                  autoComplete="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                />
              </label>
            </fieldset>

            {error && <p role="alert">{error}</p>}

            <button type="submit" disabled={pending}>
              {pending ? t('admin.forgot.submitting') : t('admin.forgot.submit')}
            </button>
          </form>

          <p className="form-link">
            <Link to="/admin/login">{t('admin.forgot.backToLogin')}</Link>
          </p>
        </>
      )}
    </SetupLayout>
  )
}
