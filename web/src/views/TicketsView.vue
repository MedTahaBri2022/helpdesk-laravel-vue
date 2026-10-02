<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import TicketBadges from '@/components/TicketBadges.vue'
import { api } from '@/lib/api'
import {
  PRIORITY_LABELS,
  STATUS_LABELS,
  type Category,
  type TicketFilters,
  type TicketPriority,
  type TicketStatus,
} from '@/lib/types'
import { useAuthStore } from '@/stores/auth'
import { useTicketsStore } from '@/stores/tickets'

const auth = useAuthStore()
const store = useTicketsStore()
const categories = ref<Category[]>([])

// The search box is debounced: one request when typing stops, not one per key.
const search = ref(store.filters.q)
let timer: ReturnType<typeof setTimeout> | undefined
watch(search, (value) => {
  clearTimeout(timer)
  timer = setTimeout(() => store.setFilter('q', value.trim()), 300)
})

function select<K extends keyof TicketFilters>(key: K, event: Event) {
  const value = (event.target as HTMLSelectElement).value
  void store.setFilter(key, (key === 'category_id' && value ? Number(value) : value) as TicketFilters[K])
}

function clear() {
  search.value = ''
  void store.reset()
}

const formatDate = (iso: string) =>
  new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })

onMounted(async () => {
  void store.fetch()
  categories.value = (await api<{ data: Category[] }>('/categories')).data
})
</script>

<template>
  <div class="space-y-5">
    <div class="flex flex-wrap items-center gap-3">
      <h1 class="flex-1 text-2xl font-bold">{{ auth.isStaff ? 'All tickets' : 'My tickets' }}</h1>
      <RouterLink :to="{ name: 'new-ticket' }" class="btn">New ticket</RouterLink>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
      <input
        v-model="search"
        type="search"
        class="field sm:col-span-2 lg:col-span-2"
        placeholder="Search subject or description..."
        aria-label="Search tickets"
      />
      <select class="field" aria-label="Status" :value="store.filters.status" @change="select('status', $event)">
        <option value="">All statuses</option>
        <option v-for="(label, value) in STATUS_LABELS" :key="value" :value="value">{{ label }}</option>
      </select>
      <select class="field" aria-label="Priority" :value="store.filters.priority" @change="select('priority', $event)">
        <option value="">All priorities</option>
        <option v-for="(label, value) in PRIORITY_LABELS" :key="value" :value="value">{{ label }}</option>
      </select>
      <select class="field" aria-label="Category" :value="store.filters.category_id" @change="select('category_id', $event)">
        <option value="">All categories</option>
        <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
      </select>
    </div>

    <div v-if="auth.isStaff" class="flex flex-wrap gap-2 text-sm">
      <button
        v-for="[value, label] in [['', 'Everyone'], ['me', 'Assigned to me'], ['none', 'Unassigned']] as const"
        :key="value"
        type="button"
        class="cursor-pointer rounded-full border px-3 py-1"
        :class="store.filters.assigned === value ? 'border-brand bg-brand text-white' : 'border-line bg-white text-muted'"
        @click="store.setFilter('assigned', value)"
      >
        {{ label }}
      </button>
    </div>

    <p v-if="store.error" role="alert" class="text-sm text-danger">{{ store.error }}</p>

    <div class="card divide-y divide-line" :class="{ 'opacity-60': store.loading }">
      <RouterLink
        v-for="ticket in store.tickets"
        :key="ticket.id"
        :to="{ name: 'ticket', params: { id: ticket.id } }"
        class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 hover:bg-surface"
      >
        <span class="w-24 font-mono text-xs text-muted">{{ ticket.reference }}</span>
        <span class="min-w-0 flex-1 basis-60">
          <span class="block truncate font-medium">{{ ticket.subject }}</span>
          <span class="block truncate text-xs text-muted">
            {{ ticket.category.name }} · {{ ticket.requester.name }} · {{ formatDate(ticket.created_at) }}
            <template v-if="auth.isStaff"> · {{ ticket.assignee?.name ?? 'unassigned' }}</template>
          </span>
        </span>
        <span class="text-xs text-muted">{{ ticket.comments_count }} replies</span>
        <TicketBadges :status="ticket.status as TicketStatus" :priority="ticket.priority as TicketPriority" />
      </RouterLink>

      <div v-if="!store.loading && store.tickets.length === 0" class="px-4 py-12 text-center text-sm text-muted">
        No ticket matches.
        <button type="button" class="cursor-pointer font-medium text-brand-dark hover:underline" @click="clear">
          Clear the filters
        </button>
      </div>
    </div>

    <nav class="flex items-center justify-between text-sm text-muted" aria-label="Pagination">
      <span>{{ store.meta.total }} tickets · page {{ store.meta.current_page }} of {{ store.meta.last_page }}</span>
      <span class="flex gap-2">
        <button
          type="button"
          class="btn-outline"
          :disabled="store.meta.current_page <= 1"
          @click="store.setFilter('page', store.meta.current_page - 1)"
        >
          Previous
        </button>
        <button
          type="button"
          class="btn-outline"
          :disabled="store.meta.current_page >= store.meta.last_page"
          @click="store.setFilter('page', store.meta.current_page + 1)"
        >
          Next
        </button>
      </span>
    </nav>
  </div>
</template>
