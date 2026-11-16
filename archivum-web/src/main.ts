import { createApp } from 'vue'
import { createPinia } from 'pinia'

import './styles/base.css'

import App from './App.vue'
import { installSession } from './features/session/install'
import router from './router'
import { installPageChanges } from './shared/pageChanges'

const app = createApp(App)

app.use(createPinia())
// After Pinia and before the router, so the guard and the title of the page also apply to the first route.
installSession(router)
installPageChanges(router)
app.use(router)

app.mount('#app')
