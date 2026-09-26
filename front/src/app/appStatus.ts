import { createContext, useContext } from 'react'
import type { StatusResponse } from '../api/status'

// État de l'application (GET /api/) une fois l'installation terminée, fourni par AppGate.
// Un contexte et non une prop : les éléments de route sont créés une seule fois (router.tsx),
// seuls leurs consommateurs de contexte sont re-rendus quand il change.
export const AppStatusContext = createContext<StatusResponse | null>(null)

export function useAppStatus(): StatusResponse | null {
  return useContext(AppStatusContext)
}
