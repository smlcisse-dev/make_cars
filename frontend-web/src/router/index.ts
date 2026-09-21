import { createRouter, createWebHistory } from 'vue-router'

import { homePathForRole, profilePathForRole, useAuthStore } from '@/stores/auth'
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
        {
          path: 'registrations',
          name: 'admin.registrations',
          component: () => import('@/views/admin/RegistrationsView.vue'),
        },
        {
          path: 'registrations/:id',
          name: 'admin.registrations.show',
          component: () => import('@/views/admin/RegistrationDetailView.vue'),
        },
        {
          path: 'services',
          name: 'admin.services',
          component: () => import('@/views/admin/ServicesView.vue'),
        },
        {
          path: 'services/:id',
          name: 'admin.services.show',
          component: () => import('@/views/admin/ServiceDetailView.vue'),
        },
        {
          path: 'products',
          name: 'admin.products',
          component: () => import('@/views/admin/ProductsView.vue'),
        },
        {
          path: 'products/:id',
          name: 'admin.products.show',
          component: () => import('@/views/admin/ProductDetailView.vue'),
        },
        {
          path: 'avis',
          name: 'admin.avis',
          component: () => import('@/views/admin/AvisView.vue'),
        },
        {
          path: 'avis/:id',
          name: 'admin.avis.show',
          component: () => import('@/views/admin/AvisDetailView.vue'),
        },
        {
          path: 'litiges',
          name: 'admin.litiges',
          component: () => import('@/views/admin/LitigesView.vue'),
        },
        {
          path: 'litiges/:id',
          name: 'admin.litiges.show',
          component: () => import('@/views/admin/LitigeDetailView.vue'),
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
        {
          path: 'profile',
          name: 'garage.profile',
          component: () => import('@/views/shared/ProfessionalProfileView.vue'),
          props: { space: 'garage' },
        },
        {
          path: 'services',
          name: 'garage.services',
          component: () => import('@/views/garage/ServicesView.vue'),
        },
        {
          path: 'products',
          name: 'garage.products',
          component: () => import('@/views/garage/ProductsView.vue'),
        },
        {
          path: 'appointments',
          name: 'garage.appointments',
          component: () => import('@/views/garage/AppointmentsView.vue'),
        },
        {
          path: 'appointments/:id',
          name: 'garage.appointments.show',
          component: () => import('@/views/garage/AppointmentDetailView.vue'),
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
        {
          path: 'profile',
          name: 'market-space.profile',
          component: () => import('@/views/shared/ProfessionalProfileView.vue'),
          props: { space: 'market-space' },
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
//
// Entièrement synchrone, aucun appel réseau : `auth.isAuthenticated`/
// `auth.user` ne font que lire l'état déjà en mémoire (chargé une fois depuis
// localStorage à la création du store) — il n'y a donc pas de "vérification
// de session" à éviter ici, la session n'est jamais re-vérifiée auprès du
// backend à chaque navigation. La seule vérification serveur se produit
// naturellement au premier appel API de la page visitée (ex. le chargement
// de la liste dans RegistrationsView.vue) : un 401 y déclenche le nettoyage
// et la redirection (voir src/api/http.ts).
router.beforeEach((to) => {
  const auth = useAuthStore()
  const requiredRole = to.matched.find((record) => record.meta.role)?.meta.role

  if (to.name === 'login') {
    // Déjà connecté : inutile de repasser par le login, direction son espace.
    // On vérifie `isAuthenticated` (token ET utilisateur), pas `user` seul :
    // un `user` sans token valide ne doit jamais faire croire à une session
    // active (voir le commentaire de clearStoredSession() dans lib/token.ts).
    if (!auth.isAuthenticated || !auth.user) {
      return true
    }
    return { path: homePathForRole(auth.user.role) }
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

  // Profil professionnel incomplet : aucune autre page que celle du profil
  // (CLAUDE.md §5, ajout v0.20). La déconnexion n'est pas une route, elle
  // reste toujours possible depuis la barre latérale.
  const profilePath = profilePathForRole(auth.user.role)
  if (auth.mustCompleteProfile && profilePath && to.path !== profilePath) {
    return { path: profilePath }
  }

  return true
})

export default router
