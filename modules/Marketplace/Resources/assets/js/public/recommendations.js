// =========================================================
// Qué ha recomendado este visitante
// =========================================================
// La fuente de verdad es el servidor, no el navegador. El feed devuelve, en
// cada respuesta, qué productos de esa página ya recomendó este visitante
// (identificado por la cookie `mkt_visitor`), y el deep-link siembra el suyo.
// Aquí solo se guarda ese conjunto para que el pulgar pinte sin volver a
// preguntar y para reflejar al instante lo que el usuario acaba de tocar.
//
// Es membresía, no conteo: en el producto el pulgar solo está encendido o
// apagado. El número que ve el público es el de la TIENDA, y ese viaja aparte.
//
// Observable y compartido porque un mismo producto puede estar dos veces en
// pantalla (la tarjeta y el modal abierto encima): tocar uno enciende el otro.

import Vue from 'vue'

const state = Vue.observable({ ids: {} })

export default {
    /** Añade los ids que el servidor confirma como ya recomendados. */
    hydrate(ids) {
        (ids || []).forEach((id) => Vue.set(state.ids, id, true))
    },

    has(itemId) {
        return state.ids[itemId] === true
    },

    // Optimistas: mueven el estado antes de que conteste el servidor, para que
    // el pulgar responda al instante. Si la petición falla, la pantalla revierte
    // llamando al opuesto.
    mark(itemId) {
        Vue.set(state.ids, itemId, true)
    },

    unmark(itemId) {
        Vue.delete(state.ids, itemId)
    },
}
