<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import TicketBadges from '@/components/TicketBadges.vue'
import { api, ApiError } from '@/lib/api'
import {
  PRIORITY_LABELS,
  STATUS_LABELS,
  type Comment,
  type Person,
  type Ticket,
  type TicketPriority,
  type TicketStatus,
} from '@/lib/types'
import { useAuthStore } from '@/stores/auth'

const props = defineProps<{ id: string }>()
const auth = useAuthStore()

const ticket = ref<Ticket | null>(null)
const agents = ref<Person[]>([])
const error = ref('')
const reply = ref('')
const internal = ref(false)
const sending = ref(false)

async function load() {
  error.value = ''
  try {
    ticket.value = (await api<{ data: Ticket }>(`/tickets/${props.id}`)).data
  } catch (caught) {
    ticket.value = null
    error.value =
      caught instanceof ApiError && caught.status === 403
        ? 'You do not have access to this ticket.'
        : (caught as Error).message
  }
}

/** Sends a partial update; the API answers with the ticket as it now is. */
async function patch(changes: Record<string, unknown>) {
  error.value = ''
  try {
    ticket.value = (await api<{ data: Ticket }>(`/tickets/${props.id}`, { method: 'PATCH', body: changes })).data
  } catch (caught) {
    error.value =
      caught instanceof ApiError ? (Object.values(caught.fields)[0]?.[0] ?? caught.message) : (caught as Error).message
  }
}

async function sendReply() {
  if (!ticket.value) return
  sending.value = true
  error.value = ''
  try {
    const { data } = await api<{ data: Comment }>(`/tickets/${props.id}/comments`, {
      method: 'POST',
      body: { body: reply.value, is_internal: internal.value },
    })
    ticket.value.comments = [...(ticket.value.comments ?? []), data]
    reply.value = ''
    internal.value = false
  } catch (caught) {
    error.value = (caught as Error).message
  } finally {
    sending.value = false
  }
}

const formatDateTime = (iso: string) =>
  new Date(iso).toLocaleString('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })

const actionLabel = (status: TicketStatus) =>
  ({ open: 'Reopen', in_progress: 'Start work', resolved: 'Mark as resolved', closed: 'Close' })[status]

onMounted(async () => {
  void load()
  if (auth.isStaff) agents.value = (await api<{ data: Person[] }>('/agents')).data
})
watch(() => props.id, load)
</script>

<template>
  <div class="space-y-5">
    <RouterLink :to="{ name: 'tickets' }" class="text-sm text-brand-dark hover:underline">← Back to tickets</RouterLink>

    <p v-if="error" role="alert" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-danger">{{ error }}</p>

    <div v-if="ticket" class="grid gap-6 lg:grid-cols-[1fr_280px]">
      <div class="min-w-0 space-y-5">
        <div>
          <p class="font-mono text-xs text-muted">{{ ticket.reference }}</p>
          <h1 class="text-2xl font-bold">{{ ticket.subject }}</h1>
          <p class="mt-1 text-sm text-muted">
            Opened by {{ ticket.requester.name }} on {{ formatDateTime(ticket.created_at) }} · {{ ticket.category.name }}
          </p>
        </div>

        <div class="card p-5 text-sm leading-relaxed whitespace-pre-line">{{ ticket.description }}</div>

        <section aria-labelledby="conversation" class="space-y-3">
          <h2 id="conversation" class="text-lg font-semibold">Conversation</h2>
          <p v-if="!ticket.comments?.length" class="text-sm text-muted">No reply yet.</p>
          <article
            v-for="comment in ticket.comments"
            :key="comment.id"
            class="card p-4"
            :class="{ 'border-amber-300 bg-amber-50': comment.is_internal }"
          >
            <p class="mb-1 text-xs text-muted">
              <span class="font-medium text-ink">{{ comment.author.name }}</span>
              <span class="capitalize"> · {{ comment.author.role }}</span> · {{ formatDateTime(comment.created_at) }}
              <span v-if="comment.is_internal" class="ml-1 font-medium text-amber-700">Internal note</span>
            </p>
            <p class="text-sm whitespace-pre-line">{{ comment.body }}</p>
          </article>

          <form class="card space-y-3 p-4" @submit.prevent="sendReply">
            <label class="label" for="reply">Reply</label>
            <textarea id="reply" v-model="reply" class="field h-28 py-2" required minlength="2" maxlength="5000" />
            <div class="flex flex-wrap items-center gap-4">
              <button type="submit" class="btn" :disabled="sending">{{ sending ? 'Sending...' : 'Send' }}</button>
              <label v-if="auth.isStaff" class="flex cursor-pointer items-center gap-2 text-sm">
                <input v-model="internal" type="checkbox" class="size-4 accent-amber-600" />
                Internal note (hidden from the customer)
              </label>
            </div>
          </form>
        </section>
      </div>

      <aside class="space-y-4">
        <div class="card space-y-4 p-4">
          <TicketBadges :status="ticket.status" :priority="ticket.priority" />

          <!-- The buttons are whatever the API says this user may do next. -->
          <div class="flex flex-col gap-2">
            <button
              v-for="status in ticket.allowed_statuses"
              :key="status"
              type="button"
              class="btn-outline w-full"
              @click="patch({ status })"
            >
              {{ actionLabel(status) }}
            </button>
            <p v-if="!ticket.allowed_statuses.length" class="text-xs text-muted">
              This ticket is {{ STATUS_LABELS[ticket.status].toLowerCase() }}; no further action is available.
            </p>
          </div>
        </div>

        <div v-if="auth.isStaff" class="card space-y-3 p-4">
          <div>
            <label class="label" for="assignee">Assigned to</label>
            <select
              id="assignee"
              class="field"
              :value="ticket.assignee?.id ?? ''"
              @change="patch({ assigned_to: Number(($event.target as HTMLSelectElement).value) || null })"
            >
              <option value="">Nobody</option>
              <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
            </select>
          </div>
          <div>
            <label class="label" for="priority">Priority</label>
            <select
              id="priority"
              class="field"
              :value="ticket.priority"
              @change="patch({ priority: ($event.target as HTMLSelectElement).value as TicketPriority })"
            >
              <option v-for="(label, value) in PRIORITY_LABELS" :key="value" :value="value">{{ label }}</option>
            </select>
          </div>
        </div>
        <p v-else class="text-xs text-muted">
          Assigned to {{ ticket.assignee?.name ?? 'nobody yet' }}.
        </p>
      </aside>
    </div>
  </div>
</template>
