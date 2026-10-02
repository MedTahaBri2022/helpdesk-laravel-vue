<script setup lang="ts">
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

async function signOut() {
  await auth.logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <div class="min-h-dvh">
    <header v-if="auth.isAuthenticated" class="border-b border-line bg-white">
      <div class="mx-auto flex h-14 max-w-5xl flex-wrap items-center gap-x-5 gap-y-1 px-4">
        <RouterLink :to="{ name: 'tickets' }" class="font-semibold text-brand-dark">HelpDesk</RouterLink>
        <nav class="flex flex-1 items-center gap-4 text-sm">
          <RouterLink :to="{ name: 'tickets' }" class="text-muted hover:text-ink" exact-active-class="!text-ink font-medium">
            Tickets
          </RouterLink>
          <RouterLink
            v-if="auth.isStaff"
            :to="{ name: 'dashboard' }"
            class="text-muted hover:text-ink"
            active-class="!text-ink font-medium"
          >
            Dashboard
          </RouterLink>
        </nav>
        <span class="text-sm text-muted">
          {{ auth.user?.name }}
          <span class="ml-1 rounded-full bg-surface px-2 py-0.5 text-xs capitalize">{{ auth.user?.role }}</span>
        </span>
        <button type="button" class="cursor-pointer text-sm text-muted hover:text-ink" @click="signOut">
          Sign out
        </button>
      </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-8">
      <RouterView />
    </main>
  </div>
</template>
