import { useState, type FormEvent } from 'react'
import { createUser } from '../../../api/install'
import { useAsyncAction } from '../../../hooks/useAsyncAction'
import { t } from '../../../i18n/i18n'

const MIN_PASSWORD_LENGTH = 8

export default function UserStep({ onCreated }: { onCreated: () => void }) {
  const { pending, error, setError, run } = useAsyncAction()
  const [firstName, setFirstName] = useState('')
  const [lastName, setLastName] = useState('')
  const [displayName, setDisplayName] = useState('')
  // Le nom d'affichage suit "prénom nom" tant que l'utilisateur ne l'a pas modifié lui-même
  const [displayNameEdited, setDisplayNameEdited] = useState(false)
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')

  function updateName(first: string, last: string) {
    setFirstName(first)
    setLastName(last)
    if (!displayNameEdited) {
      setDisplayName(`${first} ${last}`.trim())
    }
  }

  function handleSubmit(event: FormEvent) {
    event.preventDefault()

    if (password.length < MIN_PASSWORD_LENGTH) {
      setError(() => t('install.user.passwordTooShort', { min: MIN_PASSWORD_LENGTH }))
      return
    }
    if (password !== confirmation) {
      setError(() => t('install.user.passwordMismatch'))
      return
    }

    void run(() =>
      createUser({
        first_name: firstName,
        last_name: lastName,
        display_name: displayName,
        email,
        password,
      }),
    ).then((ok) => ok && onCreated())
  }

  return (
    <>
      <h1>{t('install.user.title')}</h1>
      <p>{t('install.user.intro')}</p>

      <form onSubmit={handleSubmit}>
        <fieldset disabled={pending}>
          <div className="field-row">
            <label>
              {t('install.user.firstName')}
              <input
                required
                autoComplete="given-name"
                value={firstName}
                onChange={(e) => updateName(e.target.value, lastName)}
              />
            </label>
            <label>
              {t('install.user.lastName')}
              <input
                required
                autoComplete="family-name"
                value={lastName}
                onChange={(e) => updateName(firstName, e.target.value)}
              />
            </label>
          </div>
          <label>
            {t('install.user.displayName')}
            <input
              required
              value={displayName}
              onChange={(e) => {
                setDisplayName(e.target.value)
                setDisplayNameEdited(true)
              }}
            />
          </label>
          <label>
            {t('install.user.email')}
            <input
              type="email"
              required
              autoComplete="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
            />
          </label>
          <div className="field-row">
            <label>
              {t('install.user.password')}
              <input
                type="password"
                required
                autoComplete="new-password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
              />
            </label>
            <label>
              {t('install.user.confirmation')}
              <input
                type="password"
                required
                autoComplete="new-password"
                value={confirmation}
                onChange={(e) => setConfirmation(e.target.value)}
              />
            </label>
          </div>
        </fieldset>

        {error && <p role="alert">{error}</p>}

        <button type="submit" disabled={pending}>
          {pending ? t('install.user.submitting') : t('install.user.submit')}
        </button>
      </form>
    </>
  )
}
