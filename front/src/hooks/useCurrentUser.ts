import { useEffect, useState } from 'react'
import { fetchCurrentUser, type CurrentUser } from '../api/auth'

type State =
  | { kind: 'loading' }
  | { kind: 'error'; error: unknown }
  | { kind: 'ready'; user: CurrentUser | null }

// Utilisateur connecté : undefined pendant le chargement, null si visiteur.
// Une erreur (back injoignable…) est levée au rendu → errorElement du routeur.
export function useCurrentUser(): CurrentUser | null | undefined {
  const [state, setState] = useState<State>({ kind: 'loading' })

  useEffect(() => {
    let active = true
    fetchCurrentUser()
      .then((user) => active && setState({ kind: 'ready', user }))
      .catch((error: unknown) => active && setState({ kind: 'error', error }))

    return () => {
      active = false
    }
  }, [])

  if (state.kind === 'error') {
    throw state.error
  }

  return state.kind === 'ready' ? state.user : undefined
}
