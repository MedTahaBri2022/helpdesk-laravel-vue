<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const mode = ref<'login' | 'register'>('login')
const form = reactive({ name: '', email: '', password: '' })
const pending = ref(false)
const message = ref('')
const fields = ref<Record<string, string[]>>({})

async function submit() {
  pending.value = true
  message.value = ''
  fields.value = {}
  try {
    if (mode.value === 'login') await auth.login(form.email, form.password)
    else await auth.register(form.name, form.email, form.password)
    await router.push((route.query.redirect as string) || { name: 'tickets' })
  } catch (error) {
    message.value = (error as Error).message
    if (error instanceof ApiError) fields.value = error.fields
  } finally {
    pending.value = false
  }
}
</script>

<template>
  <div class="mx-auto mt-10 max-w-sm">
    <h1 class="text-center text-2xl font-bold text-brand-dark">HelpDesk</h1>
    <p class="mt-1 text-center text-sm text-muted">
      {{ mode === 'login' ? 'Sign in to follow your tickets' : 'Create a customer account' }}
    </p>

    <form class="card mt-6 space-y-4 p-6" @submit.prevent="submit">
      <div v-if="mode === 'register'">
        <label class="label" for="name">Name</label>
        <input id="name" v-model="form.name" class="field" required autocomplete="name" />
        <p v-if="fields.name" class="mt-1 text-xs text-danger">{{ fields.name[0] }}</p>
      </div>
      <div>
        <label class="label" for="email">Email</label>
        <input id="email" v-model="form.email" class="field" type="email" required autocomplete="email" />
        <p v-if="fields.email" class="mt-1 text-xs text-danger">{{ fields.email[0] }}</p>
      </div>
      <div>
        <label class="label" for="password">Password</label>
        <input
          id="password"
          v-model="form.password"
          class="field"
          type="password"
          required
          :minlength="mode === 'register' ? 8 : 1"
          :autocomplete="mode === 'register' ? 'new-password' : 'current-password'"
        />
        <p v-if="fields.password" class="mt-1 text-xs text-danger">{{ fields.password[0] }}</p>
      </div>

      <p v-if="message && !Object.keys(fields).length" role="alert" class="text-sm text-danger">{{ message }}</p>

      <button type="submit" class="btn w-full" :disabled="pending">
        {{ pending ? 'Please wait...' : mode === 'login' ? 'Sign in' : 'Create account' }}
      </button>
    </form>

    <p class="mt-4 text-center text-sm text-muted">
      {{ mode === 'login' ? 'No account yet?' : 'Already registered?' }}
      <button
        type="button"
        class="cursor-pointer font-medium text-brand-dark hover:underline"
        @click="mode = mode === 'login' ? 'register' : 'login'"
      >
        {{ mode === 'login' ? 'Create one' : 'Sign in' }}
      </button>
    </p>
  </div>
</template>
