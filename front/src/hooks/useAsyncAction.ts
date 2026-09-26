import { useState } from 'react'
import { errorMessage } from '../api/client'

// Exécute une action asynchrone en suivant son état (en cours / erreur affichable).
// run() renvoie true si l'action a réussi.
export function useAsyncAction() {
  const [pending, setPending] = useState(false)
  // Fonction plutôt que texte : le message est recalculé à chaque rendu et suit un changement de langue
  const [failure, setFailure] = useState<(() => string) | null>(null)

  function setError(message: (() => string) | null) {
    // Enveloppée : passée telle quelle, React la prendrait pour une fonction de mise à jour
    setFailure(() => message)
  }

  async function run(action: () => Promise<unknown>): Promise<boolean> {
    setError(null)
    setPending(true)
    try {
      await action()
      return true
    } catch (e) {
      setError(() => errorMessage(e))
      return false
    } finally {
      setPending(false)
    }
  }

  return { pending, error: failure?.() ?? null, setError, run }
}
