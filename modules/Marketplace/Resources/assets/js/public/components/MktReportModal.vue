<template>
    <div class="mkt-modal" role="dialog" aria-modal="true" @click="$emit('close')">
        <div class="mkt-report" @click.stop>
            <template v-if="!sent">
                <h3 class="mkt-report__title">Denunciar</h3>
                <p class="mkt-report__target">{{ targetLabel }}</p>

                <div class="field">
                    <label for="mkt-reason">Motivo</label>
                    <select id="mkt-reason" v-model="reason" class="input mkt-report__select">
                        <option value="">Elige un motivo…</option>
                        <option v-for="r in reasons" :key="r" :value="r">{{ r }}</option>
                    </select>
                </div>

                <p v-if="error" class="mkt-report__error">{{ error }}</p>

                <div class="mkt-report__actions">
                    <button type="button" class="btn btn--ghost btn--sm" @click="$emit('close')">Cancelar</button>
                    <button type="button" class="btn btn--primary btn--sm" :disabled="!reason || sending" @click="send">
                        {{ sending ? 'Enviando…' : 'Enviar denuncia' }}
                    </button>
                </div>
            </template>

            <div v-else class="mkt-report__done">
                <span class="mkt-report__check"><mkt-icon name="check" :size="24"/></span>
                <h3 class="mkt-report__title">Denuncia enviada</h3>
                <p>Gracias. El administrador la revisará pronto.</p>
                <button type="button" class="btn btn--secondary btn--sm" @click="$emit('close')">Cerrar</button>
            </div>
        </div>
    </div>
</template>

<script>
import MktIcon from './MktIcon.vue'

export default {
    name: 'MktReportModal',

    components: { MktIcon },

    props: {
        // Producto o tienda; uno de los dos.
        target: { type: Object, required: true },
        reasons: { type: Array, default: () => [] },
        prefix: { type: String, default: 'marketplace' },
    },

    data() {
        return { reason: '', sending: false, sent: false, error: null }
    },

    computed: {
        isProduct() {
            return this.target.id !== undefined
        },
        targetLabel() {
            return this.isProduct
                ? `Producto: ${this.target.name}`
                : `Tienda: ${this.target.name}`
        },
    },

    methods: {
        send() {
            this.sending = true
            this.error = null

            const payload = this.isProduct
                ? { item_id: this.target.id, reason: this.reason }
                : { store: this.target.slug, reason: this.reason }

            this.$http.post(`/${this.prefix}/denuncia`, payload)
                .then(() => { this.sent = true })
                .catch((err) => {
                    this.error = this.isThrottled(err)
                        ? 'Has enviado demasiadas denuncias. Inténtalo más tarde.'
                        : 'No se pudo enviar la denuncia. Inténtalo de nuevo.'
                })
                .finally(() => { this.sending = false })
        },

        /**
         * El throttle es de 5 denuncias por hora y por IP, pero NO se detecta
         * por el código 429: el handler global de la app
         * (app/Exceptions/Handler.php) devuelve 500 para cualquier excepción en
         * peticiones JSON. Lo mismo le pasa a otras rutas del proyecto, así que
         * en vez de tocar el handler se reconoce también por el mensaje.
         */
        isThrottled(err) {
            const res = err.response
            if (!res) return false
            if (res.status === 429) return true

            const message = res.data && res.data.message
            return typeof message === 'string' && /too many attempts/i.test(message)
        },
    },
}
</script>

<style scoped>
.mkt-modal {
    position: fixed;
    inset: 0;
    z-index: 70;
    background: rgba(2, 15, 60, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    animation: mkt-fade 200ms var(--ease-out);
}

.mkt-report {
    background: var(--white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-xl);
    width: min(420px, 100%);
    padding: 28px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.mkt-report__title {
    margin: 0;
    font-size: var(--fs-h5);
    font-weight: 700;
    color: var(--buho-navy-950);
}

.mkt-report__target {
    margin: 0;
    font-size: var(--fs-caption);
    color: var(--color-text-muted);
}

.mkt-report__select { width: 100%; box-sizing: border-box; appearance: auto; }

.mkt-report__error {
    margin: 0;
    font-size: var(--fs-caption);
    color: var(--color-danger, #d0021b);
}

.mkt-report__actions { display: flex; gap: 10px; justify-content: flex-end; }

.mkt-report__done { text-align: center; padding: 8px 0; }
.mkt-report__done p { margin: 6px 0 16px; font-size: var(--fs-caption); color: var(--color-text-muted); }

.mkt-report__check {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--color-success-bg);
    color: var(--color-success);
    margin-bottom: 12px;
}
</style>
