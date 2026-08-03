<template>
    <div class="mkt-drawer" role="dialog" aria-modal="true" @click="$emit('close')">
        <div class="mkt-drawer__panel" @click.stop>
            <header class="mkt-drawer__head">
                <div class="mkt-drawer__title">
                    <h2>Mi pedido</h2>
                    <span class="mkt-mono">{{ count }}</span>
                </div>
                <div class="mkt-drawer__head-actions">
                    <button v-if="!isEmpty" type="button" class="mkt-drawer__clear" @click="clear">Vaciar</button>
                    <button type="button" class="mkt-drawer__close" aria-label="Cerrar" @click="$emit('close')">
                        <mkt-icon name="x" :size="18"/>
                    </button>
                </div>
            </header>

            <!-- Aviso cuando el pedido cruza varias tiendas: cada una recibe su
                 propio WhatsApp, no se mezclan. -->
            <div v-if="groups.length > 1" class="mkt-drawer__multi">
                <mkt-icon name="info-circle" :size="18"/>
                <span>
                    Tu pedido tiene productos de <span class="mkt-mono">{{ groups.length }}</span> tiendas.
                    Cada tienda recibe su propio WhatsApp solo con sus productos.
                </span>
            </div>

            <div class="mkt-drawer__body">
                <div v-if="isEmpty" class="mkt-drawer__empty">
                    <mkt-icon name="shopping-bag" :size="36"/>
                    <p>
                        Tu pedido está vacío. Agrega productos con el botón
                        <mkt-icon name="plus" :size="13"/> de cada tarjeta.
                    </p>
                </div>

                <div v-for="g in groups" :key="g.store.slug" class="mkt-order">
                    <div class="mkt-order__head">
                        <span class="avatar avatar--sm avatar--navy">{{ g.store.initials }}</span>
                        <span class="mkt-order__store">{{ g.store.name }}</span>
                    </div>

                    <div v-for="it in g.items" :key="it.id" class="mkt-order__line">
                        <span class="mkt-order__info">
                            <span class="mkt-order__name">{{ it.name }}</span>
                            <span v-if="it.code" class="mkt-order__code mkt-mono">{{ it.code }}</span>
                        </span>

                        <span class="mkt-stepper">
                            <button type="button" aria-label="Menos" @click="dec(it)">
                                <mkt-icon name="minus" :size="14"/>
                            </button>
                            <span class="mkt-stepper__qty mkt-mono">{{ it.qty }}</span>
                            <button type="button" aria-label="Más" @click="inc(it)">
                                <mkt-icon name="plus" :size="14"/>
                            </button>
                        </span>

                        <button type="button" class="mkt-order__remove" aria-label="Quitar" @click="remove(it)">
                            <mkt-icon name="trash" :size="16"/>
                        </button>
                    </div>

                    <div class="mkt-order__foot">
                        <!-- El mensaje se arma en el SERVIDOR: la puerta de
                             contacto recibe ítems y cantidades y devuelve el
                             enlace. El número nunca está en el navegador. -->
                        <button type="button" class="mkt-order__send" @click="$emit('contact', g)">
                            <mkt-icon name="whatsapp" :size="20"/>
                            Enviar pedido · <span class="mkt-mono">{{ g.units }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <p v-if="!isEmpty" class="mkt-drawer__note">
                Sin pagos en línea: coordinas precio y entrega con cada tienda por WhatsApp.
            </p>
        </div>
    </div>
</template>

<script>
import MktIcon from './MktIcon.vue'
import cart from '../cart'

// El pedido, agrupado por tienda. Cada tienda envía su propio mensaje de
// WhatsApp con sus ítems y cantidades. Toda la data sale del store `cart`
// (persistido); aquí solo se pinta y se manejan las cantidades.
//
// El envío ya no arma ningún enlace: emite `contact` con el grupo y la puerta
// de contacto (MktContactGate) hace el resto en el servidor. Al abrir el
// WhatsApp de una tienda, Marketplace vacía sus líneas del carrito: un pedido
// enviado no se queda en la lista invitando a reenviarse.
export default {
    name: 'MktCartDrawer',

    components: { MktIcon },

    computed: {
        groups() {
            return cart.groups()
        },
        count() {
            return cart.count()
        },
        isEmpty() {
            return this.groups.length === 0
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
        inc(it) {
            cart.setQty(it.id, it.qty + 1)
        },
        dec(it) {
            // A 0 el store lo quita solo.
            cart.setQty(it.id, it.qty - 1)
        },
        remove(it) {
            cart.remove(it.id)
        },
        clear() {
            cart.clear()
        },
    },
}
</script>

<style scoped>
.mkt-drawer {
    position: fixed;
    inset: 0;
    z-index: 65;
    background: rgba(2, 15, 60, 0.6);
    display: flex;
    justify-content: flex-end;
    animation: mkt-fade 200ms var(--ease-out);
}

.mkt-drawer__panel {
    width: min(440px, 100%);
    height: 100%;
    background: var(--white);
    display: flex;
    flex-direction: column;
    box-shadow: var(--shadow-xl);
}

.mkt-drawer__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 20px 24px;
    border-bottom: 1px solid var(--color-border);
}

.mkt-drawer__title { display: flex; align-items: baseline; gap: 10px; }
.mkt-drawer__title h2 { margin: 0; font-size: var(--fs-h5); font-weight: 800; color: var(--buho-navy-950); }
.mkt-drawer__title span { font-size: var(--fs-caption); color: var(--color-text-muted); }

.mkt-drawer__head-actions { display: flex; align-items: center; gap: 8px; }

.mkt-drawer__clear {
    background: none;
    border: none;
    cursor: pointer;
    font-family: var(--font-sans);
    font-size: var(--fs-caption);
    color: var(--color-text-muted);
    text-decoration: underline;
    padding: 6px;
}

.mkt-drawer__close {
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
}

.mkt-drawer__close:hover { background: var(--gray-200); }

.mkt-drawer__multi {
    margin: 16px 24px 0;
    background: var(--buho-cream);
    border-radius: var(--radius-md);
    padding: 12px 14px;
    display: flex;
    gap: 10px;
    align-items: flex-start;
    font-size: var(--fs-caption);
    color: var(--buho-navy-950);
    line-height: 1.5;
}

.mkt-drawer__multi svg { flex: none; margin-top: 1px; color: var(--buho-blue-600); }

.mkt-drawer__body {
    flex: 1;
    overflow: auto;
    padding: 16px 24px 24px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.mkt-drawer__empty { text-align: center; padding: 48px 16px; color: var(--color-text-muted); }
.mkt-drawer__empty p { margin: 12px 0 0; font-size: var(--fs-body-sm); line-height: 1.55; }

.mkt-order {
    flex: none;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    overflow: hidden;
}

.mkt-order__head {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    background: var(--buho-cream);
}

.mkt-order__head .avatar { flex: none; }

.mkt-order__store {
    flex: 1;
    font-weight: 700;
    font-size: var(--fs-body-sm);
    color: var(--buho-navy-950);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.mkt-order__line {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border-top: 1px solid var(--color-border);
}

.mkt-order__info { flex: 1; min-width: 0; }

.mkt-order__name {
    display: block;
    font-size: var(--fs-caption);
    font-weight: 600;
    color: var(--buho-navy-950);
    line-height: 1.35;
}

.mkt-order__code {
    display: block;
    font-size: var(--fs-micro);
    color: var(--color-text-muted);
    margin-top: 2px;
}

.mkt-stepper {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-full);
    flex: none;
}

.mkt-stepper button {
    width: 34px;
    height: 34px;
    border: none;
    background: none;
    cursor: pointer;
    color: var(--buho-navy-950);
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.mkt-stepper button:hover { background: var(--buho-cream); }

.mkt-stepper__qty {
    font-size: var(--fs-caption);
    font-weight: 700;
    min-width: 18px;
    text-align: center;
    color: var(--buho-navy-950);
}

.mkt-order__remove {
    width: 34px;
    height: 34px;
    border: none;
    background: none;
    cursor: pointer;
    color: var(--color-text-muted);
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: none;
}

.mkt-order__remove:hover { color: var(--color-danger, #d0021b); background: var(--color-danger-bg, #fef0f0); }

.mkt-order__foot { padding: 12px 14px; border-top: 1px solid var(--color-border); }

.mkt-order__send {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    border: none;
    cursor: pointer;
    font-family: var(--font-sans);
    height: 48px;
    border-radius: var(--radius-full);
    background: #25d366;
    color: var(--buho-navy-950);
    font-weight: 700;
    font-size: var(--fs-body-sm);
    transition: all 200ms var(--ease-out);
}

.mkt-order__send:hover { box-shadow: 0 4px 14px rgba(37, 211, 102, 0.4); }

.mkt-drawer__note {
    margin: 0;
    padding: 14px 24px;
    border-top: 1px solid var(--color-border);
    font-size: var(--fs-micro);
    color: var(--color-text-muted);
    text-align: center;
}
</style>
