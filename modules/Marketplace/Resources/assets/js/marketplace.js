// =========================================================
// Entrypoint público del Marketplace
// =========================================================
// Bundle standalone: NO se cuelga de system.js ni de app.js, y NO importa
// Element UI ni Bootstrap. Esa es la razón de existir de este archivo — si
// algún día acaba importándolos, el patrón se rompió.
//
// Referencia del patrón: modules/ClaimsBook/Resources/assets/js/app.js
// (entrypoint standalone del widget público del Libro de Reclamaciones).

import Vue from 'vue'
import axios from 'axios'

import '../sass/marketplace.scss'
import Marketplace from './public/Marketplace.vue'

// CSRF para POST /marketplace/denuncia (Fase 5).
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
const token = document.head.querySelector('meta[name="csrf-token"]')
if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content
}
Vue.prototype.$http = axios

Vue.config.productionTip = false

const el = document.getElementById('marketplace-app')
if (el) {
    new Vue({
        el,
        render: (h) => h(Marketplace),
    })
}
