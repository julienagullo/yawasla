import { useState, type FormEvent } from 'react'
import { configureDatabase } from '../../../api/install'
import type { StatusResponse } from '../../../api/status'
import { useAsyncAction } from '../../../hooks/useAsyncAction'

interface Props {
  status: StatusResponse
  // Base déjà utilisable : on demande seulement de confirmer
  detected: boolean
  onContinue: () => void
  onProgress: () => void
}

export default function DatabaseStep({ status, detected, onContinue, onProgress }: Props) {
  const { pending, error, run } = useAsyncAction()
  const [host, setHost] = useState('127.0.0.1')
  const [port, setPort] = useState('3306')
  const [name, setName] = useState('')
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')

  if (detected) {
    return (
      <>
        <h1>Base de données détectée ✓</h1>
        <p>
          La base « {status.database} » est accessible. Yawasla va maintenant y créer ses tables.
        </p>
        <button type="button" onClick={onContinue}>
          Continuer
        </button>
      </>
    )
  }

  if (status.reason === 'unknown_database') {
    return (
      <>
        <h1>Base de données introuvable</h1>
        <p>
          La base « {status.database} » n’existe pas sur le serveur. Yawasla peut la créer avec les
          identifiants déjà configurés.
        </p>
        {error && <p role="alert">{error}</p>}
        <button
          type="button"
          disabled={pending}
          onClick={() => void run(configureDatabase).then((ok) => ok && onProgress())}
        >
          {pending ? 'Création…' : 'Créer la base'}
        </button>
      </>
    )
  }

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    void run(() =>
      configureDatabase({
        host: host.trim(),
        port: Number(port),
        database: name.trim(),
        username: username.trim(),
        password,
      }),
    ).then((ok) => ok && onProgress())
  }

  return (
    <>
      <h1>Connexion à la base de données</h1>
      <p>
        Renseignez les identifiants de votre base MySQL / MariaDB. Si la base n’existe pas, Yawasla
        essaiera de la créer.
      </p>

      <form onSubmit={handleSubmit}>
        <fieldset disabled={pending}>
          <label>
            Hôte
            <input required value={host} onChange={(e) => setHost(e.target.value)} />
          </label>
          <label>
            Port
            <input
              type="number"
              required
              min={1}
              max={65535}
              value={port}
              onChange={(e) => setPort(e.target.value)}
            />
          </label>
          <label>
            Nom de la base
            <input required value={name} onChange={(e) => setName(e.target.value)} />
          </label>
          <label>
            Utilisateur
            <input
              required
              autoComplete="username"
              value={username}
              onChange={(e) => setUsername(e.target.value)}
            />
          </label>
          <label>
            Mot de passe
            <input
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
          </label>
        </fieldset>

        {error && <p role="alert">{error}</p>}

        <button type="submit" disabled={pending}>
          {pending ? 'Connexion…' : 'Continuer'}
        </button>
      </form>
    </>
  )
}
