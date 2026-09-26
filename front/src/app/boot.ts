import type { StatusResponse } from '../api/status'

// Données de démarrage injectées par le back dans la page (AppShell.php).
// Toutes optionnelles : en dev, index.html est servi par Vite, sans injection.
export interface Boot {
  // Chemin de base de l'app : "" à la racine d'un domaine, "/yawasla" en sous-dossier
  basePath: string
  apiBase: string
  // Réponse de GET /api/ : évite l'aller-retour au démarrage (AppGate)
  status?: StatusResponse
}

function readBoot(): Boot {
  const fallback: Boot = { basePath: '', apiBase: '/api' }
  const element = document.getElementById('yawasla-boot')

  if (!element?.textContent) {
    return fallback
  }

  try {
    return { ...fallback, ...(JSON.parse(element.textContent) as Partial<Boot>) }
  } catch {
    return fallback
  }
}

export const boot = readBoot()
