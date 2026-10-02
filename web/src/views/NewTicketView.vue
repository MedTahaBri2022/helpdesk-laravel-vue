<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { api, ApiError } from '@/lib/api'
import type { Category, Ticket } from '@/lib/types'

const router = useRouter()
const categories = ref<Category[]>([])
const form = reactive({ subject: '', description: '', category_id: '' as number | '' })
const pending = ref(false)
const message = ref('')
const fields = ref<Record<string, string[]>>({})

onMounted(async () => {
  categories.value = (await api<{ data: Category[] }>('/categories')).data
})

async function submit() {
  pending.value = true
  message.value = ''
  fields.value = {}
  try {
    const { data } = await api<{ data: Ticket }>('/tickets', { method: 'POST', body: form })
    await router.push({ name: 'ticket', params: { id: data.id } })
  } catch (error) {
    message.value = (error as Error).message
    // Laravel returns one list of messages per field: show them where they belong.
    if (error instanceof ApiError) fields.value = error.fields
  } finally {
    pending.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-2xl space-y-5">
    <RouterLink :to="{ name: 'tickets' }" class="text-sm text-brand-dark hover:underline">← Back to tickets</RouterLink>
    <h1 class="text-2xl font-bold">New ticket</h1>

    <form class="card space-y-4 p-6" @submit.prevent="submit">
      <div>
        <label class="label" for="subject">Subject</label>
        <input id="subject" v-model="form.subject" class="field" required minlength="5" maxlength="150" />
        <p v-if="fields.subject" class="mt-1 text-xs text-danger">{{ fields.subject[0] }}</p>
      </div>
      <div>
        <label class="label" for="category">Category</label>
        <select id="category" v-model="form.category_id" class="field" required>
          <option value="" disabled>Choose a category</option>
          <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
        </select>
        <p v-if="fields.category_id" class="mt-1 text-xs text-danger">{{ fields.category_id[0] }}</p>
      </div>
      <div>
        <label class="label" for="description">Describe the problem</label>
        <textarea
          id="description"
          v-model="form.description"
          class="field h-40 py-2"
          required
          minlength="10"
          maxlength="5000"
        />
        <p v-if="fields.description" class="mt-1 text-xs text-danger">{{ fields.description[0] }}</p>
      </div>

      <p v-if="message && !Object.keys(fields).length" role="alert" class="text-sm text-danger">{{ message }}</p>

      <button type="submit" class="btn" :disabled="pending">{{ pending ? 'Sending...' : 'Open ticket' }}</button>
    </form>
  </div>
</template>
