import { createRouter, createWebHistory } from 'vue-router'

import { homePathForRole, useAuthStore } from '@/stores/auth'
import type { AccountType } from '@/types/user'

// Étend le type `RouteMeta` de vue-router (module augmentation TypeScript) :
// chaque route peut désormais porter `role`, et l'éditeur/le compilateur
// vérifient qu'on n'y met que ces deux propriétés-là, avec les bons types.
declare module 'vue-router' {
  interface RouteMeta {
    // Rôle requis pour accéder à la route (CLAUDE.md §3) ; absent = route
    // publique (login) ou déjà filtrée par une route parente qui en porte un.
    role?: Exclude<AccountType, 'automobiliste'>
  }
}

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      redirect: () => {
        const auth = useAuthStore()
        return auth.user ? homePathForRole(auth.user.role) : '/login'
      },
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/auth/LoginView.vue'),
    },
    {
      path: '/admin',
      component: () => import('@/layouts/AdminLayout.vue'),
      meta: { role: 'admin' },
      children: [
        {
          path: '',
          name: 'admin.dashboard',
          component: () => import('@/views/admin/DashboardView.vue'),
        },
      ],
    },
    {
      path: '/garage',
      component: () => import('@/layouts/GarageLayout.vue'),
      meta: { role: 'garagiste' },
      children: [
        {
          path: '',
          name: 'garage.dashboard',
          component: () => import('@/views/garage/DashboardView.vue'),
        },
      ],
    },
    {
      path: '/market-space',
      component: () => import('@/layouts/MarketSpaceLayout.vue'),
      meta: { role: 'market_space' },
      children: [
        {
          path: '',
          name: 'market-space.dashboard',
          component: () => import('@/views/market-space/DashboardView.vue'),
        },
      ],
    },
    {
      path: '/403',
      name: 'forbidden',
      component: () => import('@/views/errors/ForbiddenView.vue'),
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/errors/NotFoundView.vue'),
    },
  ],
})

// Garde de navigation globale : exécutée avant chaque changement de route.
// `meta.role` est hérité par les enfants sans le redéclarer (une route
// enfant sans meta propre "voit" quand même le meta du parent via
// `to.matched`, qui liste toute la chaîne parent -> enfant).
router.beforeEach((to) => {
  const auth = useAuthStore()
  const requiredRole = to.matched.find((record) => record.meta.role)?.meta.role

  if (to.name === 'login') {
    // Déjà connecté : inutile de repasser par le login, direction son espace.
    return auth.user ? { path: homePathForRole(auth.user.role) } : true
  }

  if (!requiredRole) {
    return true
  }

  if (!auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (auth.user?.role !== requiredRole) {
    return { name: 'forbidden' }
  }

  return true
})

export default router
