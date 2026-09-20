import { api } from './client'

export interface DatabasePayload {
  host: string
  port: number
  database: string
  username: string
  password: string
}

// Sans payload (base inconnue) : le back crée la base avec les identifiants déjà configurés.
export const configureDatabase = (payload?: DatabasePayload) =>
  api.post<{ status: 'configured'; created: boolean }>('/install/database', payload)

export const runMigrations = () => api.post<{ status: 'migrated' }>('/install/migrate')

export interface OrganizationPayload {
  name: string
  address?: string
  city?: string
  postal_code?: string
  phone?: string
  email?: string
  domain?: string
}

export const createOrganization = (payload: OrganizationPayload) =>
  api.post<{ status: 'organization_created' }>('/install/organization', payload)

export interface UserPayload {
  first_name: string
  last_name: string
  display_name: string
  email: string
  password: string
}

export const createUser = (payload: UserPayload) =>
  api.post<{ status: 'installed' }>('/install/user', payload)
