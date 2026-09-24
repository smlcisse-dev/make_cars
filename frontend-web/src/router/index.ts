import { createRouter, createWebHistory } from 'vue-router'

import { homePathForRole, profilePathForRole, useAuthStore } from '@/stores/auth'
import { useSignupStore } from '@/stores/signup'
import type { AccountType } from '@/types/user'

// Étend le type `RouteMeta` de vue-router (module augmentation TypeScript) :
// chaque route peut désormais porter `role`, et l'éditeur/le compilateur
// vérifient qu'on n'y met que ces deux propriétés-là, avec les bons types.
declare module 'vue-router' {
  interface RouteMeta {
    // Rôle requis pour accéder à la route (CLAUDE.md §3) ; absent = route
    // publique (login) ou déjà filtrée par une route parente qui en porte un.
    role?: Exclude<AccountType, 'automobiliste'>
    // Page réservée aux visiteurs non connectés (login, inscription) : un
    // utilisateur déjà connecté est renvoyé vers son espace.
    guestOnly?: boolean
    // Étape du parcours d'inscription professionnelle (CLAUDE.md §5, ajout
    // v0.26) : quitter ces pages vide l'inscription en cours (stores/signup.ts).
    signupFlow?: boolean
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
      meta: { guestOnly: true },
    },
    // Mot de passe oublié par code email (CLAUDE.md §5, ajout v0.29), page
    // publique en deux étapes.
    {
      path: '/mot-de-passe-oublie',
      name: 'password.forgot',
      component: () => import('@/views/auth/ForgotPasswordView.vue'),
      meta: { guestOnly: true },
    },
    // Inscription professionnelle (CLAUDE.md §5, ajout v0.26), pages publiques.
    {
      path: '/inscription',
      name: 'signup',
      component: () => import('@/views/auth/SignupChoiceView.vue'),
      meta: { guestOnly: true, signupFlow: true },
    },
    {
      path: '/inscription/garagiste',
      name: 'signup.garagiste',
      component: () => import('@/views/auth/SignupFormView.vue'),
      props: { accountType: 'garagiste' },
      meta: { guestOnly: true, signupFlow: true },
    },
    {
      path: '/inscription/boutique',
      name: 'signup.market-space',
      component: () => import('@/views/auth/SignupFormView.vue'),
      props: { accountType: 'market_space' },
      meta: { guestOnly: true, signupFlow: true },
    },
    {
      // `:id` = `verification_id` renvoyé par le backend, dans l'URL pour
      // qu'un rechargement permette encore de saisir le code. `props: true`
      // transmet les paramètres de l'URL comme props du composant.
      path: '/inscription/verification/:id',
      name: 'signup.verify',
      component: () => import('@/views/auth/SignupVerifyView.vue'),
      props: true,
      meta: { guestOnly: true, signupFlow: true },
    },
    {
      path: '/inscription/confirmation',
      name: 'signup.done',
      component: () => import('@/views/auth/SignupDoneView.vue'),
      meta: { guestOnly: true, signupFlow: true },
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
          component: () => import('@/views/shared/ProductsView.vue'),
          props: { space: 'garage' },
        },
        {
          path: 'orders',
          name: 'garage.orders',
          component: () => import('@/views/shared/OrdersView.vue'),
          props: { space: 'garage' },
        },
        {
          path: 'orders/:id',
          name: 'garage.orders.show',
          component: () => import('@/views/shared/OrderDetailView.vue'),
          props: { space: 'garage' },
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
        {
          path: 'quotes',
          name: 'garage.quotes',
          component: () => import('@/views/garage/QuotesView.vue'),
        },
        // `new` avant `:id` : sinon « new » serait lu comme un identifiant de devis.
        {
          path: 'quotes/new',
          name: 'garage.quotes.new',
          component: () => import('@/views/garage/QuoteCreateView.vue'),
        },
        {
          path: 'quotes/:id',
          name: 'garage.quotes.show',
          component: () => import('@/views/garage/QuoteDetailView.vue'),
        },
        {
          path: 'conversations',
          name: 'garage.conversations',
          component: () => import('@/views/shared/ConversationsView.vue'),
          props: { space: 'garage' },
        },
        {
          path: 'reviews',
          name: 'garage.reviews',
          component: () => import('@/views/shared/ReviewsView.vue'),
          props: { space: 'garage' },
        },
        {
          path: 'disputes',
          name: 'garage.disputes',
          component: () => import('@/views/shared/DisputesView.vue'),
          props: { space: 'garage' },
        },
        {
          path: 'disputes/:id',
          name: 'garage.disputes.show',
          component: () => import('@/views/shared/DisputeDetailView.vue'),
          props: { space: 'garage' },
        },
        {
          path: 'notifications',
          name: 'garage.notifications',
          component: () => import('@/views/shared/NotificationsView.vue'),
          props: { space: 'garage' },
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
        {
          path: 'conversations',
          name: 'market-space.conversations',
          component: () => import('@/views/shared/ConversationsView.vue'),
          props: { space: 'market-space' },
        },
        {
          path: 'products',
          name: 'market-space.products',
          component: () => import('@/views/shared/ProductsView.vue'),
          props: { space: 'market-space' },
        },
        {
          path: 'orders',
          name: 'market-space.orders',
          component: () => import('@/views/shared/OrdersView.vue'),
          props: { space: 'market-space' },
        },
        {
          path: 'orders/:id',
          name: 'market-space.orders.show',
          component: () => import('@/views/shared/OrderDetailView.vue'),
          props: { space: 'market-space' },
        },
        {
          path: 'reviews',
          name: 'market-space.reviews',
          component: () => import('@/views/shared/ReviewsView.vue'),
          props: { space: 'market-space' },
        },
        {
          path: 'disputes',
          name: 'market-space.disputes',
          component: () => import('@/views/shared/DisputesView.vue'),
          props: { space: 'market-space' },
        },
        {
          path: 'disputes/:id',
          name: 'market-space.disputes.show',
          component: () => import('@/views/shared/DisputeDetailView.vue'),
          props: { space: 'market-space' },
        },
        {
          path: 'notifications',
          name: 'market-space.notifications',
          component: () => import('@/views/shared/NotificationsView.vue'),
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

  // Sortie du parcours d'inscription : le mot de passe saisi ne doit pas
  // rester en mémoire plus longtemps que nécessaire.
  if (!to.meta.signupFlow) {
    useSignupStore().reset()
  }

  if (to.meta.guestOnly) {
    // Déjà connecté : inutile de repasser par le login ou l'inscription,
    // direction son espace.
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

  // Dossier non approuvé ou profil incomplet : aucune autre page que celle
  // du profil (CLAUDE.md §5, ajouts v0.20 et v0.26). La déconnexion n'est pas
  // une route, elle reste toujours possible depuis la barre latérale.
  const profilePath = profilePathForRole(auth.user.role)
  if (auth.mustStayOnProfile && profilePath && to.path !== profilePath) {
    return { path: profilePath }
  }

  return true
})

export default router
