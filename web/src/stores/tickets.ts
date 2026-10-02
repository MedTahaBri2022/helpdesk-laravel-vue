import { defineStore } from 'pinia'
import { reactive, ref } from 'vue'
import { api } from '@/lib/api'
import type { Paginated, Ticket, TicketFilters } from '@/lib/types'

export const defaultFilters = (): TicketFilters => ({
  q: '',
  status: '',
  priority: '',
  category_id: '',
  assigned: '',
  page: 1,
})

export const useTicketsStore = defineStore('tickets', () => {
  const tickets = ref<Ticket[]>([])
  const meta = ref({ current_page: 1, last_page: 1, per_page: 10, total: 0 })
  const filters = reactive<TicketFilters>(defaultFilters())
  const loading = ref(false)
  const error = ref<string | null>(null)

  // Each call gets a number; only the latest one is allowed to write the
  // state, so a slow response cannot overwrite a newer one.
  let latestRequest = 0

  async function fetch() {
    const request = ++latestRequest
    loading.value = true
    error.value = null
    try {
      const result = await api<Paginated<Ticket>>('/tickets', { query: { ...filters } })
      if (request !== latestRequest) return
      tickets.value = result.data
      meta.value = result.meta
    } catch (caught) {
      if (request !== latestRequest) return
      error.value = (caught as Error).message
    } finally {
      if (request === latestRequest) loading.value = false
    }
  }

  /** Changing any filter goes back to the first page. */
  function setFilter<K extends keyof TicketFilters>(key: K, value: TicketFilters[K]) {
    filters[key] = value
    if (key !== 'page') filters.page = 1
    return fetch()
  }

  function reset() {
    Object.assign(filters, defaultFilters())
    return fetch()
  }

  return { tickets, meta, filters, loading, error, fetch, setFilter, reset }
})
