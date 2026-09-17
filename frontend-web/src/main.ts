import { createPinia } from 'pinia'
import { createApp } from 'vue'

import App from '@/App.vue'
import router from '@/router'

import './style.css'

const app = createApp(App)

// L'ordre compte : le store Pinia (auth) doit être installé avant le
// router, puisque les gardes de navigation (router/index.ts) appellent
// `useAuthStore()` dès la toute première résolution de route.
app.use(createPinia())
app.use(router)

app.mount('#app')
