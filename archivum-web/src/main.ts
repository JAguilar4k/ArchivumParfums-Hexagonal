import { createApp } from 'vue'
import { createPinia } from 'pinia'

import '@fontsource/inter/latin-400.css'
import '@fontsource/inter/latin-500.css'
import '@fontsource/inter/latin-600.css'
import '@fontsource/inter/latin-700.css'
import '@fontsource/playfair-display/latin-600.css'
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
