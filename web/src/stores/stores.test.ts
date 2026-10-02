import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError, api, buildUrl, token } from '@/lib/api'
import { useAuthStore } from './auth'
import { useTicketsStore } from './tickets'

const json = (body: unknown, status = 200) => new Response(JSON.stringify(body), { status })
const user = { id: 1, name: 'Ada', email: 'ada@example.com', role: 'agent' }
const page = (ids: number[]) => ({
  data: ids.map((id) => ({ id })),
  meta: { current_page: 1, last_page: 1, per_page: 10, total: ids.length },
})

beforeEach(() => {
  setActivePinia(createPinia())
  token.clear()
})
afterEach(() => vi.unstubAllGlobals())

describe('api client', () => {
  it('builds query strings without empty values', () => {
    expect(buildUrl('/tickets', { q: '', status: 'open', page: 2, priority: undefined })).toBe(
      '/api/tickets?status=open&page=2',
    )
  })

  it('exposes Laravel validation errors per field', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        json({ message: 'The subject field is required.', errors: { subject: ['The subject field is required.'] } }, 422),
      ),
    )

    const error = await api('/tickets', { method: 'POST', body: {} }).catch((caught: unknown) => caught)

    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).status).toBe(422)
    expect((error as ApiError).fields.subject).toEqual(['The subject field is required.'])
  })
})

describe('auth store', () => {
  it('stores the token on login and sends it afterwards', async () => {
    const fetchMock = vi.fn().mockResolvedValueOnce(json({ token: 'abc', user })).mockResolvedValueOnce(json({ data: [] }))
    vi.stubGlobal('fetch', fetchMock)
    const auth = useAuthStore()

    await auth.login('ada@example.com', 'secret')
    await api('/categories')

    expect(auth.isAuthenticated).toBe(true)
    expect(auth.isStaff).toBe(true)
    const [, init] = fetchMock.mock.calls[1] as [string, RequestInit]
    expect(init.headers).toMatchObject({ Authorization: 'Bearer abc' })
  })

  it('restores a session from a stored token', async () => {
    token.set('stored')
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(json({ data: user })))
    const auth = useAuthStore()

    await auth.restore()

    expect(auth.ready).toBe(true)
    expect(auth.user?.name).toBe('Ada')
  })

  it('drops a token the API no longer accepts', async () => {
    token.set('revoked')
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(json({ message: 'Unauthenticated.' }, 401)))
    const auth = useAuthStore()

    await auth.restore()

    expect(auth.isAuthenticated).toBe(false)
    expect(token.get()).toBeNull()
  })
})

describe('tickets store', () => {
  it('goes back to page 1 when a filter changes', async () => {
    const fetchMock = vi.fn().mockImplementation(() => Promise.resolve(json(page([1]))))
    vi.stubGlobal('fetch', fetchMock)
    const store = useTicketsStore()

    await store.setFilter('page', 3)
    await store.setFilter('status', 'open')

    expect(store.filters.page).toBe(1)
    expect(fetchMock.mock.calls[1][0]).toBe('/api/tickets?status=open&page=1')
  })

  it('ignores a slow response that arrives after a newer one', async () => {
    let resolveSlow!: (response: Response) => void
    const slow = new Promise<Response>((resolve) => (resolveSlow = resolve))
    vi.stubGlobal('fetch', vi.fn().mockReturnValueOnce(slow).mockResolvedValueOnce(json(page([2, 3]))))
    const store = useTicketsStore()

    const first = store.setFilter('q', 'inv')
    await store.setFilter('q', 'invoice')
    resolveSlow(json(page([1])))
    await first

    expect(store.tickets.map((ticket) => ticket.id)).toEqual([2, 3])
    expect(store.loading).toBe(false)
  })

  it('keeps the error message when loading fails', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(json({ message: 'Server Error' }, 500)))
    const store = useTicketsStore()

    await store.fetch()

    expect(store.error).toBe('Server Error')
  })
})
