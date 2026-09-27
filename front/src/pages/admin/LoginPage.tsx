import { useState, type FormEvent } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { login } from '../../api/auth'
import { useAsyncAction } from '../../hooks/useAsyncAction'
import { useCurrentUser } from '../../hooks/useCurrentUser'
import { t } from '../../i18n/i18n'
import SetupLayout from '../../layouts/SetupLayout'

export default function LoginPage() {
  const user = useCurrentUser()
  const navigate = useNavigate()
  const { pending, error, run } = useAsyncAction()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')

  // Déjà connecté : directement vers l'administration
  if (user) {
    return <Navigate to="/admin" replace />
  }

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    void run(() => login(email, password)).then((ok) => ok && navigate('/admin', { replace: true }))
  }

  return (
    <SetupLayout
      subtitle={t('admin.subtitle')}
      footer={t('common.copyright', { year: new Date().getFullYear() })}
    >
      {user === undefined ? (
        <p>{t('common.loading')}</p>
      ) : (
        <>
          <h1>{t('admin.login.title')}</h1>

          <form onSubmit={handleSubmit}>
            <fieldset disabled={pending}>
              <label>
                {t('admin.login.email')}
                <input
                  type="email"
                  required
                  autoComplete="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                />
              </label>
              <label>
                {t('admin.login.password')}
                <input
                  type="password"
                  required
                  autoComplete="current-password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                />
              </label>
            </fieldset>

            {error && <p role="alert">{error}</p>}

            <button type="submit" disabled={pending}>
              {pending ? t('admin.login.submitting') : t('admin.login.submit')}
            </button>
          </form>

          <p className="form-link">
            <Link to="/admin/forgot">{t('admin.login.forgot')}</Link>
          </p>
        </>
      )}
    </SetupLayout>
  )
}
