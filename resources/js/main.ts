import '@fontsource/alexandria/arabic-400.css'
import '@fontsource/alexandria/arabic-500.css'
import '@fontsource/alexandria/arabic-600.css'
import '@fontsource/alexandria/arabic-700.css'
import '@fontsource/alexandria/latin-400.css'
import '@fontsource/alexandria/latin-500.css'
import '@fontsource/alexandria/latin-600.css'
import '@fontsource/alexandria/latin-700.css'
import '@fontsource/cinzel/latin-600.css'
import '@fontsource/cinzel/latin-900.css'
import '@fontsource/amiri/arabic-700.css'
import '../css/tokens.css'
import '../css/app.css'
import { createPinia } from 'pinia'
import { createApp } from 'vue'
import App from './App.vue'
import './i18n'
import { installSiteCopy } from './lib/content'
import { router } from './router'

createApp(App).use(createPinia()).use(router).mount('#app')
// Phrases rewritten by the administrators, in the visitor's language.
installSiteCopy()
