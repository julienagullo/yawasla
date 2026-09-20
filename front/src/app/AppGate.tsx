import { useCallback, useEffect, useState, type ReactNode } from 'react'
import SetupLayout from '../layouts/SetupLayout'
import { fetchStatus, type StatusResponse } from '../api/status'
import InstallWizard from '../pages/install/InstallWizard'

type State =
  | { kind: 'loading' }
  | { kind: 'error'; message: string }
  | { kind: 'ready'; status: StatusResponse }

// Interroge le back au démarrage et n'affiche l'application que si elle est
// installée et à jour. Sinon, affiche l'écran correspondant à l'état détecté.
export default function AppGate({ children }: { children: ReactNode }) {
  const [state, setState] = useState<State>({ kind: 'loading' })

  const load = useCallback(() => {
    fetchStatus()
      .then((status) => setState({ kind: 'ready', status }))
      .catch((error: Error) => setState({ kind: 'error', message: error.message }))
  }, [])

  // Premier chargement : l'état initial est déjà 'loading'
  useEffect(load, [load])

  const reload = () => {
    setState({ kind: 'loading' })
    load()
  }

  if (state.kind === 'loading') {
    return (
      <SetupLayout>
        <p>Chargement…</p>
      </SetupLayout>
    )
  }

  if (state.kind === 'error') {
    return (
      <SetupLayout>
        <h1>Service indisponible</h1>
        <p>{state.message}</p>
        <button type="button" onClick={reload}>
          Réessayer
        </button>
      </SetupLayout>
    )
  }

  switch (state.status.status) {
    case 'config_required':
    case 'install_required':
      return <InstallWizard status={state.status} onProgress={load} />
    case 'update_required':
      // TODO : écran de mise à jour (POST /api/update)
      return (
        <SetupLayout>
          <h1>Mise à jour requise</h1>
        </SetupLayout>
      )
    default:
      return <>{children}</>
  }
}
