const BASE_URL: string = import.meta.env?.VITE_API_URL ?? '/api'
const TOKEN_KEY = 'helpdesk-token'

/** Error returned by the API; `fields` holds Laravel's per-field validation messages. */
export class ApiError extends Error {
  readonly status: number
  readonly fields: Record<string, string[]>

  constructor(status: number, message: string, fields: Record<string, string[]> = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.fields = fields
  }
}

// Node (unit tests) has no localStorage.
const memory = new Map<string, string>()
const storage = {
  get: (key: string) =>
    typeof localStorage === 'undefined' ? (memory.get(key) ?? null) : localStorage.getItem(key),
  set: (key: string, value: string) =>
    typeof localStorage === 'undefined' ? void memory.set(key, value) : localStorage.setItem(key, value),
  remove: (key: string) =>
    typeof localStorage === 'undefined' ? void memory.delete(key) : localStorage.removeItem(key),
}

export const token = {
  get: () => storage.get(TOKEN_KEY),
  set: (value: string) => storage.set(TOKEN_KEY, value),
  clear: () => storage.remove(TOKEN_KEY),
}

type Query = Record<string, string | number | undefined>

export function buildUrl(path: string, query: Query = {}): string {
  const params = new URLSearchParams()
  for (const [key, value] of Object.entries(query)) {
    if (value !== undefined && value !== '') params.set(key, String(value))
  }
  const search = params.toString()
  return `${BASE_URL}${path}${search ? `?${search}` : ''}`
}

/** Called when the API answers 401: lets the auth store sign the user out. */
let onUnauthorized: () => void = () => {}
export function setUnauthorizedHandler(handler: () => void) {
  onUnauthorized = handler
}

export async function api<T>(
  path: string,
  options: { method?: 'GET' | 'POST' | 'PATCH' | 'DELETE'; body?: unknown; query?: Query } = {},
): Promise<T> {
  const bearer = token.get()
  const response = await fetch(buildUrl(path, options.query), {
    method: options.method ?? 'GET',
    headers: {
      Accept: 'application/json',
      ...(options.body !== undefined && { 'Content-Type': 'application/json' }),
      ...(bearer && { Authorization: `Bearer ${bearer}` }),
    },
    body: options.body === undefined ? undefined : JSON.stringify(options.body),
  })

  if (response.status === 204) return undefined as T

  const payload = (await response.json().catch(() => null)) as
    | { message?: string; errors?: Record<string, string[]> }
    | null
  if (!response.ok) {
    if (response.status === 401 && bearer) onUnauthorized()
    throw new ApiError(response.status, payload?.message ?? response.statusText, payload?.errors)
  }
  return payload as T
}
