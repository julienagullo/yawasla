import { useState, type FormEvent } from 'react'
import { createUser } from '../../../api/install'
import { useAsyncAction } from '../../../hooks/useAsyncAction'

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
      setError(`Le mot de passe doit contenir au moins ${MIN_PASSWORD_LENGTH} caractères.`)
      return
    }
    if (password !== confirmation) {
      setError('Les mots de passe ne correspondent pas.')
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
      <h1>Compte principal</h1>
      <p>Ce compte sera le propriétaire de l’organisme et donnera accès au back-office et à tous les modules.</p>

      <form onSubmit={handleSubmit}>
        <fieldset disabled={pending}>
          <div className="field-row">
            <label>
              Prénom *
              <input
                required
                autoComplete="given-name"
                value={firstName}
                onChange={(e) => updateName(e.target.value, lastName)}
              />
            </label>
            <label>
              Nom *
              <input
                required
                autoComplete="family-name"
                value={lastName}
                onChange={(e) => updateName(firstName, e.target.value)}
              />
            </label>
          </div>
          <label>
            Nom d’affichage
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
            Adresse e-mail *
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
              Mot de passe
              <input
                type="password"
                required
                autoComplete="new-password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
              />
            </label>
            <label>
              Confirmation
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
          {pending ? 'Création…' : 'Terminer l’installation'}
        </button>
      </form>
    </>
  )
}
