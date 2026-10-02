<script setup lang="ts">
import { PRIORITY_LABELS, STATUS_LABELS, type TicketPriority, type TicketStatus } from '@/lib/types'

defineProps<{ status: TicketStatus; priority: TicketPriority }>()

const STATUS_CLASS: Record<TicketStatus, string> = {
  open: 'bg-sky-100 text-sky-800',
  in_progress: 'bg-amber-100 text-amber-800',
  resolved: 'bg-emerald-100 text-emerald-800',
  closed: 'bg-slate-100 text-slate-600',
}
const PRIORITY_CLASS: Record<TicketPriority, string> = {
  low: 'text-slate-500',
  medium: 'text-sky-700',
  high: 'text-amber-700',
  urgent: 'text-red-700 font-semibold',
}
</script>

<template>
  <span class="inline-flex items-center gap-2 whitespace-nowrap">
    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="STATUS_CLASS[status]">
      {{ STATUS_LABELS[status] }}
    </span>
    <span class="text-xs" :class="PRIORITY_CLASS[priority]">{{ PRIORITY_LABELS[priority] }}</span>
  </span>
</template>
