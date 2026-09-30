import { api } from './client'

// Nouvelle version publiée sur le serveur de distribution (voir Yawasla\Core\Updater)
export interface Release {
  version: string
  date: string
  php: string
  // false : la version de PHP du serveur est trop ancienne, l'installation sera refusée
  compatible: boolean
}

interface ReleaseResponse {
  current_version: string
  // null : à jour, vérification désactivée ou serveur de distribution injoignable
  release: Release | null
}

export const fetchRelease = () => api.get<ReleaseResponse>('/admin/release')

// Remplace les fichiers de l'application ; recharger la page ensuite (migrations éventuelles)
export const installRelease = () => api.post<{ version: string }>('/admin/release/install')

// Migrations en attente (état "update_required")
export const migrate = () => api.post<unknown>('/admin/update/migrate')
