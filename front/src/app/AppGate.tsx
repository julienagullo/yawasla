import { useCallback, useEffect, useState, type ReactNode } from 'react'
import SetupLayout from '../layouts/SetupLayout'
import { fetchStatus, type StatusResponse } from '../api/status'
import InstallWizard from '../pages/install/InstallWizard'
import { errorMessage } from '../api/client'
import { t } from '../i18n/i18n'
import { AppStatusContext } from './appStatus'
import { boot } from './boot'

type State =
  | { kind: 'loading' }
  | { kind: 'error'; error: unknown }
  | { kind: 'ready'; status: StatusResponse }

// Interroge le back au démarrage et n'affiche l'application que si elle est
// installée et à jour. Sinon, affiche l'écran correspondant à l'état détecté.
export default function AppGate({ children }: { children: ReactNode }) {
  // État déjà injecté dans la page par le back (absent en dev) : pas de requête au démarrage
  const [state, setState] = useState<State>(() =>
    boot.status ? { kind: 'ready', status: boot.status } : { kind: 'loading' },
  )

  const load = useCallback(() => {
    fetchStatus()
      .then((status) => setState({ kind: 'ready', status }))
      .catch((error: unknown) => setState({ kind: 'error', error }))
  }, [])

  // Premier chargement, sauf si l'état a été injecté (l'état initial est alors déjà 'ready')
  useEffect(() => {
    if (!boot.status) {
      load()
    }
  }, [load])

  const reload = () => {
    setState({ kind: 'loading' })
    load()
  }

  if (state.kind === 'loading') {
    return (
      <SetupLayout>
        <p>{t('common.loading')}</p>
      </SetupLayout>
    )
  }

  if (state.kind === 'error') {
    return (
      <SetupLayout>
        <h1>{t('app.unavailable')}</h1>
        <p>{errorMessage(state.error)}</p>
        <button type="button" onClick={reload}>
          {t('common.retry')}
        </button>
      </SetupLayout>
    )
  }

  switch (state.status.status) {
    case 'config_required':
    case 'install_required':
      return <InstallWizard status={state.status} onProgress={load} />
    default:
      // Y compris "update_required" : le site public reste en ligne, l'administration affiche la
      // mise à jour (voir AdminLayout)
      return <AppStatusContext.Provider value={state.status}>{children}</AppStatusContext.Provider>
  }
}
