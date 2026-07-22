// =========================================================
// El pedido: carrito compartido, agrupado por tienda
// =========================================================
// Reúne productos de una o varias tiendas y, al enviar, cada tienda recibe UN
// solo mensaje de WhatsApp con sus ítems y cantidades. No es un ecommerce: no
// hay precios, stock ni pago; es una lista de la compra que se coordina por
// WhatsApp, igual que el resto del marketplace.
//
// Persiste en localStorage porque el front público navega con recargas de
// página completas (ir a una tienda es un location.href): un carrito en memoria
// se perdería al cambiar de vista. Por eso cada ítem guarda un SNAPSHOT de lo
// que necesita para agruparse y armar el mensaje (nombre, código, y los datos
// de su tienda), sin depender de que el producto siga en la página actual.
//
// Observable y compartido: el botón de la tarjeta, el del modal, el FAB y el
// drawer leen y escriben el mismo estado, así que agregar en un sitio se refleja
// en todos al instante.

import Vue from 'vue'

const KEY = 'mkt:pedido'
const MAX_QTY = 99

// Un ítem del carrito debe traer todo lo que el drawer y el mensaje necesitan.
// Un snapshot de una versión anterior (o un localStorage manipulado) al que le
// falte algo rompería `groups()` o generaría `wa.me/undefined`, así que se
// descarta en la carga. El carrito se auto-sana: lo válido sobrevive, lo demás
// se ignora y el vecino lo vuelve a agregar.
function isValidEntry(it) {
    // Sin whatsapp ni url: desde la capa de seguridad el mensaje se arma en el
    // servidor (POST /contacto) y el snapshot solo necesita identificar el
    // producto y su tienda. Un snapshot viejo con más campos sigue siendo
    // válido; simplemente ya no se usan.
    return !!it
        && typeof it === 'object'
        && typeof it.name === 'string'
        && typeof it.qty === 'number'
        && Number.isFinite(it.qty)
        && it.qty > 0
        && !!it.store
        && typeof it.store.slug === 'string'
        && typeof it.store.name === 'string'
}

function load() {
    try {
        const raw = JSON.parse(window.localStorage.getItem(KEY))
        if (!raw || typeof raw !== 'object') return {}

        const clean = {}
        Object.keys(raw).forEach((k) => {
            if (isValidEntry(raw[k])) clean[k] = raw[k]
        })
        return clean
    } catch (e) {
        return {}
    }
}

// { [itemId]: { id, name, code, qty, store: { slug, name, initials, whatsapp } } }
const state = Vue.observable({ items: load() })

function persist() {
    try {
        window.localStorage.setItem(KEY, JSON.stringify(state.items))
    } catch (e) {
        // Sin persistencia (modo privado, cuota llena): la sesión sigue igual.
    }
}

function clamp(qty) {
    return Math.max(0, Math.min(MAX_QTY, qty))
}

export default {
    state,

    has(itemId) {
        return state.items[itemId] !== undefined
    },

    /** Snapshot mínimo de un producto del feed, listo para el carrito. */
    snapshot(product) {
        return {
            id: product.id,
            name: product.name,
            code: product.internal_code || '',
            store: {
                slug: product.store.slug,
                name: product.store.name,
                initials: product.store.initials,
            },
        }
    },

    add(product) {
        const id = product.id
        const current = state.items[id]
        Vue.set(state.items, id, {
            ...this.snapshot(product),
            qty: current ? clamp(current.qty + 1) : 1,
        })
        persist()
    },

    setQty(itemId, qty) {
        const entry = state.items[itemId]
        if (!entry) return

        const next = clamp(qty)
        if (next <= 0) {
            Vue.delete(state.items, itemId)
        } else {
            Vue.set(state.items, itemId, { ...entry, qty: next })
        }
        persist()
    },

    remove(itemId) {
        Vue.delete(state.items, itemId)
        persist()
    },

    /**
     * Vacía el pedido de UNA tienda. Se llama cuando su mensaje de WhatsApp ya
     * se abrió: ese pedido está enviado y dejarlo en la lista solo invita a
     * reenviarlo por error. Las demás tiendas del carrito no se tocan.
     */
    removeStore(storeSlug) {
        Object.keys(state.items).forEach((id) => {
            if (state.items[id].store.slug === storeSlug) Vue.delete(state.items, id)
        })
        persist()
    },

    clear() {
        state.items = {}
        persist()
    },

    /** Total de unidades (suma de cantidades), no de líneas. */
    count() {
        return Object.values(state.items).reduce((sum, it) => sum + it.qty, 0)
    },

    /**
     * Agrupado por tienda, que es como se envía: una tienda, un mensaje. El
     * orden es estable (por nombre de tienda) para que el drawer no baile al
     * cambiar cantidades.
     */
    groups() {
        const byStore = {}
        Object.values(state.items).forEach((it) => {
            const slug = it.store.slug
            if (!byStore[slug]) byStore[slug] = { store: it.store, items: [] }
            byStore[slug].items.push(it)
        })

        return Object.values(byStore)
            .sort((a, b) => a.store.name.localeCompare(b.store.name))
            .map((g) => ({
                store: g.store,
                items: g.items,
                units: g.items.reduce((sum, it) => sum + it.qty, 0),
            }))
    },
}
