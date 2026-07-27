<template>
    <div class="mkt-gate" role="dialog" aria-modal="true" @click="$emit('close')">
        <div class="mkt-gate__box" @click.stop>
            <div class="mkt-gate__head">
                <div>
                    <h3>{{ title }}</h3>
                    <p>El enlace se genera en el servidor — el número de la tienda nunca se publica en la página.</p>
                </div>
                <button type="button" class="mkt-gate__close" aria-label="Cerrar" @click="$emit('close')">
                    <mkt-icon name="x" :size="18"/>
                </button>
            </div>

            <!-- Paso 1: el formulario -->
            <template v-if="step === 'form'">
                <div class="field">
                    <label>Tu nombre</label>
                    <input class="input" v-model="name" placeholder="¿Cómo te llamas?" maxlength="80">
                </div>

                <div class="field">
                    <label>Tu WhatsApp</label>
                    <input class="input mkt-mono" type="tel" inputmode="numeric" v-model="whatsapp"
                           placeholder="51987654321" maxlength="15" @input="whatsapp = whatsapp.replace(/\D/g, '')">
                </div>

                <!-- El checkbox PoW: idle → solving → done -->
                <div class="mkt-gate__captcha" role="button" tabindex="0" @click="solve" @keydown.enter="solve">
                    <span v-if="captcha === 'idle'" class="mkt-gate__cap-idle"></span>
                    <span v-else-if="captcha === 'solving'" class="mkt-gate__cap-spin"></span>
                    <span v-else class="mkt-gate__cap-done"><mkt-icon name="check" :size="14"/></span>
                    <span class="mkt-gate__cap-text">
                        <strong>{{ captchaLabel }}</strong>
                        <small>Verificación local, sin servicios externos</small>
                    </span>
                </div>

                <label class="mkt-gate__remember">
                    <input type="checkbox" v-model="remember">
                    Recordar mis datos en este navegador
                </label>

                <div class="mkt-gate__notice">
                    <mkt-icon name="shield-lock" :size="16"/>
                    <span>Por motivos de seguridad, tu nombre, WhatsApp, cookie e IP quedan registrados al generar el enlace.</span>
                </div>

                <p v-if="error" class="mkt-gate__error">{{ error }}</p>

                <button class="btn btn--primary btn--block" :disabled="formDisabled" @click="submit">
                    {{ sending ? 'Generando…' : 'Generar enlace de WhatsApp' }} <span class="mkt-mono">→</span>
                </button>
            </template>

            <!-- Paso alterno: comprador recordado -->
            <template v-else-if="step === 'saved'">
                <div class="mkt-gate__buyer">
                    <span class="avatar avatar--navy">{{ buyerInitial }}</span>
                    <span class="mkt-gate__buyer-body">
                        <strong>{{ name }}</strong>
                        <small class="mkt-mono">{{ whatsapp }}</small>
                    </span>
                    <button type="button" @click="editBuyer">Editar</button>
                </div>

                <div class="mkt-gate__notice">
                    <mkt-icon name="shield-lock" :size="16"/>
                    <span>Esta solicitud también queda registrada con tu cookie e IP.</span>
                </div>

                <p v-if="error" class="mkt-gate__error">{{ error }}</p>

                <button class="btn btn--primary btn--block" :disabled="sending" @click="submit">
                    {{ sending ? 'Verificando…' : 'Continuar' }} <span class="mkt-mono">→</span>
                </button>
            </template>

            <!-- Paso final: el enlace, con su número de solicitud -->
            <template v-else>
                <div class="mkt-gate__ready">
                    <span class="mkt-gate__ready-check"><mkt-icon name="check" :size="26"/></span>
                    <strong>Enlace generado</strong>
                    <p>
                        Solicitud registrada como <span class="mkt-mono mkt-gate__code">{{ code }}</span>.
                        La tienda sabrá que vienes del marketplace.
                    </p>
                    <a :href="waLink" target="_blank" rel="noopener nofollow" class="mkt-wa mkt-gate__open"
                       @click="$emit('opened')">
                        <mkt-icon name="whatsapp" :size="22"/> Abrir WhatsApp
                    </a>
                </div>
            </template>
        </div>
    </div>
</template>

<script>
import MktIcon from './MktIcon.vue'
import usage from '../contact-usage'
import { fetchChallenge, solveChallenge } from '../pow'

const BUYER_KEY = 'mkt:buyer'

/**
 * La puerta de contacto (plan de seguridad, B1): TODO camino hacia WhatsApp
 * pasa por aquí. Tres pasos — form (nombre + WhatsApp + PoW), saved (comprador
 * recordado) y ready (enlace generado con nº de solicitud).
 *
 * target: { type: 'store'|'product'|'cart', store: slug, item_id?, items? }
 */
export default {
    name: 'MktContactGate',

    components: { MktIcon },

    props: {
        target: { type: Object, required: true },
        prefix: { type: String, default: 'marketplace' },
    },

    data() {
        const buyer = this.loadBuyer()

        return {
            step: buyer ? 'saved' : 'form',
            name: buyer ? buyer.name : '',
            whatsapp: buyer ? buyer.whatsapp : '',
            remember: true,
            captcha: 'idle', // idle | solving | done
            solution: null,
            sending: false,
            error: '',
            code: '',
            waLink: '',
        }
    },

    computed: {
        title() {
            return this.target.type === 'cart' ? 'Antes de enviar tu pedido' : 'Antes de abrir WhatsApp'
        },
        captchaLabel() {
            if (this.captcha === 'done') return 'Verificado'
            if (this.captcha === 'solving') return 'Resolviendo desafío…'
            return 'No soy un robot'
        },
        buyerInitial() {
            return (this.name || '?').charAt(0).toUpperCase()
        },
        formDisabled() {
            return this.sending
                || this.captcha !== 'done'
                || !this.name.trim()
                || !/^\d{9,15}$/.test(this.whatsapp)
        },
    },

    mounted() {
        document.addEventListener('keydown', this.onKey)
        document.body.style.overflow = 'hidden'
    },

    beforeDestroy() {
        document.removeEventListener('keydown', this.onKey)
        document.body.style.overflow = ''
    },

    methods: {
        onKey(event) {
            if (event.key === 'Escape') this.$emit('close')
        },

        loadBuyer() {
            try {
                const raw = JSON.parse(window.localStorage.getItem(BUYER_KEY))
                if (raw && typeof raw.name === 'string' && /^\d{9,15}$/.test(raw.whatsapp || '')) return raw
            } catch (e) { /* sin datos guardados */ }
            return null
        },

        editBuyer() {
            this.step = 'form'
            this.error = ''
        },

        /** Clic en el checkbox: pide el desafío y lo resuelve en el navegador. */
        async solve() {
            if (this.captcha !== 'idle') return
            this.captcha = 'solving'
            this.error = ''

            try {
                const challenge = await fetchChallenge(this.$http, this.prefix)
                this.solution = await solveChallenge(challenge)
                this.captcha = 'done'
            } catch (e) {
                this.captcha = 'idle'
                this.error = 'No se pudo completar la verificación. Inténtalo de nuevo.'
            }
        },

        async submit() {
            if (this.sending) return
            this.sending = true
            this.error = ''

            try {
                // En el paso «saved» (o si el desafío ya se gastó) se resuelve
                // uno silenciosamente: el servidor exige un PoW por solicitud.
                if (!this.solution) {
                    this.solution = await solveChallenge(await fetchChallenge(this.$http, this.prefix))
                }

                const { data } = await this.$http.post(`/${this.prefix}/contacto`, {
                    type: this.target.type,
                    store: this.target.store,
                    item_id: this.target.item_id || null,
                    items: this.target.items || null,
                    name: this.name.trim(),
                    whatsapp: this.whatsapp,
                    pow: this.solution,
                })

                usage.record(data.throttle)
                this.persistBuyer()

                this.code = data.code
                this.waLink = data.wa_link
                this.step = 'ready'
            } catch (e) {
                const status = e.response ? e.response.status : 0
                const message = e.response && e.response.data && e.response.data.message

                if (status === 429 && e.response.data.throttle) usage.record(e.response.data.throttle)

                this.error = message || 'No se pudo generar el enlace. Inténtalo de nuevo.'
                // El PoW es de un solo uso: gastado o rechazado, toca resolver otro.
                this.solution = null
                if (this.step === 'form') this.captcha = 'idle'
            } finally {
                this.sending = false
            }
        },

        persistBuyer() {
            try {
                if (this.remember) {
                    window.localStorage.setItem(BUYER_KEY, JSON.stringify({ name: this.name.trim(), whatsapp: this.whatsapp }))
                } else {
                    window.localStorage.removeItem(BUYER_KEY)
                }
            } catch (e) { /* sin persistencia: pedirá los datos otra vez */ }
        },
    },
}
</script>

<style scoped>
.mkt-gate {
    position: fixed;
    inset: 0;
    z-index: 75;
    background: rgba(2, 15, 60, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    animation: mkt-fade 200ms var(--ease-out);
}

.mkt-gate__box {
    background: var(--white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-xl);
    width: min(460px, 100%);
    max-height: 90vh;
    overflow: auto;
    padding: 28px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.mkt-gate__head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
.mkt-gate__head h3 { margin: 0; font-size: var(--fs-h5); font-weight: 800; color: var(--buho-navy-950); }
.mkt-gate__head p { margin: 6px 0 0; font-size: var(--fs-caption); color: var(--color-text-muted); line-height: 1.5; }

.mkt-gate__close {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: none;
    background: var(--buho-cream);
    cursor: pointer;
    color: var(--buho-navy-950);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: none;
}

.mkt-gate__captcha {
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: 12px 14px;
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    background: var(--buho-cream);
}

.mkt-gate__cap-idle {
    width: 22px;
    height: 22px;
    border: 2px solid var(--color-border);
    border-radius: 6px;
    background: var(--white);
    flex: none;
    box-sizing: border-box;
}

.mkt-gate__cap-spin {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    border: 3px solid var(--color-border);
    border-top-color: var(--buho-blue-600);
    animation: mkt-gate-spin 0.8s linear infinite;
    flex: none;
    box-sizing: border-box;
}

.mkt-gate__cap-done {
    width: 22px;
    height: 22px;
    border-radius: 6px;
    background: var(--color-success);
    color: var(--white);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: none;
}

.mkt-gate__cap-text { display: flex; flex-direction: column; }
.mkt-gate__cap-text strong { font-size: var(--fs-caption); font-weight: 600; color: var(--buho-navy-950); }
.mkt-gate__cap-text small { font-size: var(--fs-micro); color: var(--color-text-muted); }

@keyframes mkt-gate-spin { to { transform: rotate(360deg); } }

.mkt-gate__remember {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: var(--fs-caption);
    color: var(--color-text-muted);
    cursor: pointer;
}

.mkt-gate__remember input { width: 18px; height: 18px; accent-color: var(--buho-navy-950); }

.mkt-gate__notice {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    background: var(--buho-cream);
    border-radius: var(--radius-md);
    padding: 12px 14px;
    font-size: var(--fs-micro);
    color: var(--buho-navy-950);
    line-height: 1.5;
}

.mkt-gate__notice svg { flex: none; margin-top: 1px; color: var(--buho-blue-600); }

.mkt-gate__error { margin: 0; font-size: var(--fs-caption); color: var(--color-danger); }

.mkt-gate__buyer {
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--buho-cream);
    border-radius: var(--radius-md);
    padding: 14px 16px;
}

.mkt-gate__buyer-body { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.mkt-gate__buyer-body strong { font-weight: 700; font-size: var(--fs-body-sm); color: var(--buho-navy-950); }
.mkt-gate__buyer-body small { font-size: var(--fs-micro); color: var(--color-text-muted); }

.mkt-gate__buyer button {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--buho-blue-600);
    font-size: var(--fs-caption);
    font-family: var(--font-sans);
    text-decoration: underline;
}

.mkt-gate__ready {
    text-align: center;
    padding: 8px 0 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
}

.mkt-gate__ready-check {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: var(--color-success-bg);
    color: var(--color-success);
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.mkt-gate__ready strong { font-weight: 800; font-size: var(--fs-body); color: var(--buho-navy-950); }
.mkt-gate__ready p { margin: 0; font-size: var(--fs-caption); color: var(--color-text-muted); line-height: 1.5; }
.mkt-gate__code { color: var(--buho-navy-950); }

.mkt-gate__open {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    box-sizing: border-box;
    height: 52px;
    border-radius: var(--radius-full);
    font-weight: 700;
    font-size: var(--fs-body-sm);
}
</style>
