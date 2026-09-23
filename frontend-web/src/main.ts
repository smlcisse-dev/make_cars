import { createPinia } from 'pinia'
import { createApp } from 'vue'

import App from '@/App.vue'
import router from '@/router'
import { useAuthStore } from '@/stores/auth'

import './style.css'

const app = createApp(App)

// L'ordre compte : le store Pinia (auth) doit être installé avant le
// router, puisque les gardes de navigation (router/index.ts) appellent
// `useAuthStore()` dès la toute première résolution de route.
app.use(createPinia())
app.use(router)

// Rafraîchit la session au démarrage (statut du dossier, suspension,
// complétude du profil ont pu changer depuis la connexion), SANS `await` avant
// `mount` : la latence Supabase peut atteindre ~10 s, et l'écran ne doit pas
// rester blanc pendant ce temps. L'app se monte donc avec l'utilisateur
// enregistré localement ; quand la réponse arrive, `refreshUser()` remplace
// `user` dans le store, et comme menus, bandeaux et `mustStayOnProfile` sont
// des `computed` qui lisent ce store, Vue les recalcule et met l'écran à jour
// tout seul (réactivité). En cas d'échec (réseau, délai), on garde la session
// locale : un 401 (token invalide) est déjà traité par l'intercepteur Axios.
const auth = useAuthStore()
if (auth.isAuthenticated) {
  auth.refreshUser().catch(() => {
    // Session locale conservée, voir ci-dessus.
  })
}

app.mount('#app')
