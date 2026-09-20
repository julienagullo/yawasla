import { useState } from 'react'

// Exécute une action asynchrone en suivant son état (en cours / erreur affichable).
// run() renvoie true si l'action a réussi.
export function useAsyncAction() {
  const [pending, setPending] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function run(action: () => Promise<unknown>): Promise<boolean> {
    setError(null)
    setPending(true)
    try {
      await action()
      return true
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Une erreur est survenue.')
      return false
    } finally {
      setPending(false)
    }
  }

  return { pending, error, setError, run }
}
