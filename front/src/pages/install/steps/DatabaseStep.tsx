import { useState, type FormEvent } from 'react'
import { configureDatabase } from '../../../api/install'
import type { StatusResponse } from '../../../api/status'
import { useAsyncAction } from '../../../hooks/useAsyncAction'
import { t } from '../../../i18n/i18n'
import Trans from '../../../i18n/Trans'

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
        <h1>{t('install.database.detectedTitle')}</h1>
        <p>{t('install.database.detectedText', { name: status.database ?? '' })}</p>
        <button type="button" onClick={onContinue}>
          {t('common.continue')}
        </button>
      </>
    )
  }

  if (status.reason === 'unknown_database') {
    return (
      <>
        <h1>{t('install.database.unknownTitle')}</h1>
        <p>
          <Trans
            k="install.database.unknownText"
            values={{ name: status.database ?? '' }}
            components={{ code: <code /> }}
          />
        </p>
        {error && <p role="alert">{error}</p>}
        <button
          type="button"
          disabled={pending}
          onClick={() => void run(configureDatabase).then((ok) => ok && onProgress())}
        >
          {pending ? t('install.database.creating') : t('install.database.create')}
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
      <h1>{t('install.database.title')}</h1>
      <p>{t('install.database.intro')}</p>

      <form onSubmit={handleSubmit}>
        <fieldset disabled={pending}>
          <label>
            {t('install.database.host')}
            <input required value={host} onChange={(e) => setHost(e.target.value)} />
          </label>
          <label>
            {t('install.database.port')}
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
            {t('install.database.name')}
            <input required value={name} onChange={(e) => setName(e.target.value)} />
          </label>
          <label>
            {t('install.database.username')}
            <input
              required
              autoComplete="username"
              value={username}
              onChange={(e) => setUsername(e.target.value)}
            />
          </label>
          <label>
            {t('install.database.password')}
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
          {pending ? t('install.database.connecting') : t('common.continue')}
        </button>
      </form>
    </>
  )
}
