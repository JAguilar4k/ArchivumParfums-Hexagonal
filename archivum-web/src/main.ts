import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import { installSession } from './features/session/install'
import router from './router'

const app = createApp(App)

app.use(createPinia())
// After Pinia and before the router, so the guard also applies to the first route.
installSession(router)
app.use(router)

app.mount('#app')
