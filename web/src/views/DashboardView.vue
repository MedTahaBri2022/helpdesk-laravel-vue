<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api } from '@/lib/api'
import { PRIORITY_LABELS, STATUS_LABELS, type Stats, type TicketPriority, type TicketStatus } from '@/lib/types'

const stats = ref<Stats | null>(null)
const error = ref('')

onMounted(async () => {
  try {
    stats.value = await api<Stats>('/stats')
  } catch (caught) {
    error.value = (caught as Error).message
  }
})

const statusRows = computed(() =>
  stats.value
    ? (Object.keys(STATUS_LABELS) as TicketStatus[]).map((key) => ({
        label: STATUS_LABELS[key],
        value: stats.value!.by_status[key],
      }))
    : [],
)
const priorityRows = computed(() =>
  stats.value
    ? (Object.keys(PRIORITY_LABELS) as TicketPriority[]).map((key) => ({
        label: PRIORITY_LABELS[key],
        value: stats.value!.by_priority[key],
      }))
    : [],
)
const largest = (rows: { value: number }[]) => Math.max(1, ...rows.map((row) => row.value))
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold">Dashboard</h1>
    <p v-if="error" role="alert" class="text-sm text-danger">{{ error }}</p>

    <template v-if="stats">
      <div class="grid gap-4 sm:grid-cols-3">
        <div class="card p-5">
          <p class="text-sm text-muted">Waiting for an agent</p>
          <p class="mt-1 text-3xl font-bold" :class="stats.unassigned ? 'text-danger' : ''">{{ stats.unassigned }}</p>
        </div>
        <div class="card p-5">
          <p class="text-sm text-muted">Assigned to me, still open</p>
          <p class="mt-1 text-3xl font-bold">{{ stats.assigned_to_me }}</p>
        </div>
        <div class="card p-5">
          <p class="text-sm text-muted">Average time to resolve</p>
          <p class="mt-1 text-3xl font-bold">
            {{ stats.average_resolution_hours === null ? '–' : `${stats.average_resolution_hours} h` }}
          </p>
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-2">
        <section
          v-for="[title, rows] in [['By status', statusRows], ['By priority', priorityRows]] as const"
          :key="title"
          class="card p-5"
        >
          <h2 class="mb-4 font-semibold">{{ title }}</h2>
          <dl class="space-y-3">
            <div v-for="row in rows" :key="row.label" class="grid grid-cols-[90px_1fr_32px] items-center gap-3 text-sm">
              <dt class="text-muted">{{ row.label }}</dt>
              <dd class="h-2 rounded-full bg-surface">
                <div class="h-2 rounded-full bg-brand" :style="{ width: `${(row.value / largest(rows)) * 100}%` }" />
              </dd>
              <dd class="text-right font-medium tabular-nums">{{ row.value }}</dd>
            </div>
          </dl>
        </section>
      </div>
    </template>
  </div>
</template>
