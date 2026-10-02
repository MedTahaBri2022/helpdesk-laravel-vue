import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api, setUnauthorizedHandler, token } from '@/lib/api'
import type { User } from '@/lib/types'

interface AuthResponse {
  token: string
  user: User
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  // True until the first "who am I" check has finished: the router waits for it.
  const ready = ref(false)

  const isAuthenticated = computed(() => user.value !== null)
  const isStaff = computed(() => user.value?.role === 'agent' || user.value?.role === 'admin')

  function accept({ token: value, user: account }: AuthResponse) {
    token.set(value)
    user.value = account
  }

  function clear() {
    token.clear()
    user.value = null
  }

  async function login(email: string, password: string) {
    accept(await api<AuthResponse>('/login', { method: 'POST', body: { email, password } }))
  }

  async function register(name: string, email: string, password: string) {
    accept(
      await api<AuthResponse>('/register', {
        method: 'POST',
        body: { name, email, password, password_confirmation: password },
      }),
    )
  }

  async function logout() {
    // The token is revoked server-side; the local session ends either way.
    await api('/logout', { method: 'POST' }).catch(() => undefined)
    clear()
  }

  /** Restores the session from a stored token when the application starts. */
  async function restore() {
    if (token.get()) {
      try {
        user.value = (await api<{ data: User }>('/me')).data
      } catch {
        clear()
      }
    }
    ready.value = true
  }

  // An expired or revoked token ends the local session too.
  setUnauthorizedHandler(clear)

  return { user, ready, isAuthenticated, isStaff, login, register, logout, restore }
})
