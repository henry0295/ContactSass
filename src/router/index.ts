import { createRouter, createWebHistory, RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

// Pages
import LoginPage from '@/pages/Login.vue'
import DashboardPage from '@/pages/Dashboard.vue'
import CampaignsPage from '@/pages/Campaigns.vue'
import CampaignFormPage from '@/pages/CampaignForm.vue'
import ContactsPage from '@/pages/Contacts.vue'
import ReportsPage from '@/pages/Reports.vue'
import SettingsPage from '@/pages/Settings.vue'
import NotFoundPage from '@/pages/NotFound.vue'

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    redirect: '/dashboard',
  },
  {
    path: '/login',
    name: 'Login',
    component: LoginPage,
    meta: { requiresAuth: false, layout: 'blank' },
  },
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: DashboardPage,
    meta: { requiresAuth: true },
  },
  {
    path: '/campaigns',
    name: 'Campaigns',
    component: CampaignsPage,
    meta: { requiresAuth: true },
  },
  {
    path: '/campaigns/new',
    name: 'CampaignCreate',
    component: CampaignFormPage,
    meta: { requiresAuth: true },
  },
  {
    path: '/campaigns/:id/edit',
    name: 'CampaignEdit',
    component: CampaignFormPage,
    meta: { requiresAuth: true },
  },
  {
    path: '/contacts',
    name: 'Contacts',
    component: ContactsPage,
    meta: { requiresAuth: true },
  },
  {
    path: '/reports',
    name: 'Reports',
    component: ReportsPage,
    meta: { requiresAuth: true },
  },
  {
    path: '/settings',
    name: 'Settings',
    component: SettingsPage,
    meta: { requiresAuth: true },
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'NotFound',
    component: NotFoundPage,
    meta: { layout: 'blank' },
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

// Guard routes
router.beforeEach((to, _from, next) => {
  const authStore = useAuthStore()
  const isAuthenticated = authStore.isAuthenticated

  if (to.meta.requiresAuth && !isAuthenticated) {
    next('/login')
  } else if (to.path === '/login' && isAuthenticated) {
    next('/dashboard')
  } else {
    next()
  }
})

export default router
