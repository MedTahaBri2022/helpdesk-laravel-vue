import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', name: 'login', component: () => import('@/views/LoginView.vue'), meta: { guest: true } },
    { path: '/', name: 'tickets', component: () => import('@/views/TicketsView.vue') },
    { path: '/tickets/new', name: 'new-ticket', component: () => import('@/views/NewTicketView.vue') },
    { path: '/tickets/:id(\\d+)', name: 'ticket', component: () => import('@/views/TicketDetailView.vue'), props: true },
    { path: '/dashboard', name: 'dashboard', component: () => import('@/views/DashboardView.vue'), meta: { staff: true } },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

// Navigation guard: a convenience for the user. The API enforces the same
// rules again on every request.
router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (!auth.ready) await auth.restore()

  if (to.meta.guest) return auth.isAuthenticated ? { name: 'tickets' } : true
  if (!auth.isAuthenticated) return { name: 'login', query: { redirect: to.fullPath } }
  if (to.meta.staff && !auth.isStaff) return { name: 'tickets' }
  return true
})
