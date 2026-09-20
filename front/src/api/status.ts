import { api } from './client'

// État de l'application, déterminé côté back selon la version de la base
// (voir bootstrap.php : config / install / update / fonctionnement normal).
export type AppStatus = 'ok' | 'config_required' | 'install_required' | 'update_required'

// config_required : "no_env" = connexion à la base à renseigner,
// "unknown_database" = la base configurée n'existe pas encore.
export type ConfigReason = 'no_env' | 'unknown_database'

// install_required : étape en cours, déduite de la base
export type InstallStep = 'migrations' | 'organization' | 'user'

export interface StatusResponse {
  status: AppStatus
  reason?: ConfigReason
  step?: InstallStep
  database?: string
  migrations?: string[]
  current_version?: string
  target_version?: string
}

export const fetchStatus = () => api.get<StatusResponse>('/')
