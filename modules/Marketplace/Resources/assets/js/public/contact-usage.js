// =========================================================
// Rastro local del throttle de contactos
// =========================================================
// El servidor es la autoridad (contact_limit_per_hour en el backend); esto es
// solo el espejo en el navegador que permite mostrar el aviso a pantalla
// completa al cruzar la MITAD del límite, antes de abrir la puerta de
// contacto. Cada respuesta de POST /contacto trae {used, limit} y se guarda
// aquí; a la hora, la ventana del servidor expiró y el contador local también.

const KEY = 'mkt:contact-usage'
const ACK_KEY = 'mkt:throttle-ack'
const WINDOW_MS = 60 * 60 * 1000

function read() {
    try {
        const raw = JSON.parse(window.localStorage.getItem(KEY))
        if (!raw || typeof raw !== 'object') return null
        if (typeof raw.used !== 'number' || typeof raw.limit !== 'number') return null
        if (Date.now() - (raw.ts || 0) > WINDOW_MS) return null
        return raw
    } catch (e) {
        return null
    }
}

export default {
    /** Guarda el meta {used, limit} que devolvió el servidor. */
    record(throttle) {
        if (!throttle || typeof throttle.used !== 'number') return
        try {
            window.localStorage.setItem(KEY, JSON.stringify({
                used: throttle.used,
                limit: throttle.limit,
                ts: Date.now(),
            }))
        } catch (e) { /* sin persistencia, sin aviso: el servidor igual corta */ }
    },

    /**
     * ¿Toca mostrar el aviso? A partir de la mitad del límite, una sola vez
     * por sesión (el «Entiendo, continuar» queda en sessionStorage).
     */
    shouldWarn() {
        const usage = read()
        if (!usage) return false
        if (usage.used * 2 < usage.limit) return false

        try {
            return window.sessionStorage.getItem(ACK_KEY) !== '1'
        } catch (e) {
            return true
        }
    },

    acknowledge() {
        try { window.sessionStorage.setItem(ACK_KEY, '1') } catch (e) { /* igual sigue */ }
    },
}
