import { boot } from '../app/boot'
import { hasMessage, t, type Values } from '../i18n/i18n'

const BASE_URL = boot.apiBase

export class ApiError extends Error {
  status: number
  // Code stable (ex. "database.create_failed") traduit par le front, voir errorMessage()
  code?: string
  params?: Values

  constructor(status: number, message: string, code?: string, params?: Values) {
    super(message)
    this.status = status
    this.code = code
    this.params = params
  }
}

// Message affichable : traduction du code d'erreur si le front la connaît,
// sinon le message (en français) renvoyé par le back
export function errorMessage(error: unknown): string {
  if (error instanceof ApiError && error.code) {
    const key = `api.${error.code}`
    if (hasMessage(key)) {
      const params = { ...error.params }
      // Nom de champ API ("postal_code") remplacé par son libellé traduit
      const field = `api.fields.${params.field}`
      if (params.field !== undefined && hasMessage(field)) {
        params.field = t(field)
      }

      return t(key, params)
    }
  }

  return error instanceof Error ? error.message : t('errors.unknown')
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const response = await fetch(`${BASE_URL}${path}`, {
    credentials: 'include',
    ...init,
    headers: {
      Accept: 'application/json',
      ...(init.body ? { 'Content-Type': 'application/json' } : {}),
      ...init.headers,
    },
  })

  if (!response.ok) {
    // Le back renvoie ses erreurs sous la forme { error: { code?, params?, message } }
    const body = await response.json().catch(() => null)
    throw new ApiError(
      response.status,
      body?.error?.message ?? response.statusText,
      body?.error?.code,
      body?.error?.params,
    )
  }

  return (await response.json()) as T
}

export const api = {
  get: <T>(path: string) => request<T>(path),
  post: <T>(path: string, body?: unknown) =>
    request<T>(path, { method: 'POST', body: JSON.stringify(body) }),
  put: <T>(path: string, body?: unknown) =>
    request<T>(path, { method: 'PUT', body: JSON.stringify(body) }),
  delete: <T>(path: string) => request<T>(path, { method: 'DELETE' }),
}
