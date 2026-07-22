<template>
    <div class="mkt-modal" role="dialog" aria-modal="true" @click="$emit('close')">
        <div class="mkt-modal__box" @click.stop>
            <div class="mkt-modal__media">
                <img v-if="product.image_url" :src="product.image_url" :alt="product.name">
                <span v-else class="mkt-modal__initial">{{ product.initial }}</span>
            </div>

            <div class="mkt-modal__body">
                <div class="mkt-modal__top">
                    <span v-if="product.category" class="badge">{{ product.category }}</span>
                    <span v-else></span>
                    <button type="button" class="mkt-modal__close" aria-label="Cerrar" @click="$emit('close')">
                        <mkt-icon name="x" :size="18"/>
                    </button>
                </div>

                <h2 class="mkt-modal__title">{{ product.name }}</h2>

                <p v-if="priceLabel" class="mkt-modal__price">{{ priceLabel }}</p>

                <p v-if="product.internal_code" class="mkt-modal__code">
                    Código <span class="mkt-mono">{{ product.internal_code }}</span>
                </p>

                <p v-if="product.description" class="mkt-modal__desc">{{ product.description }}</p>

                <button type="button" class="mkt-modal__store" @click="$emit('open-store', product.store)">
                    <span class="avatar avatar--navy">{{ product.store.initials }}</span>
                    <span class="mkt-modal__store-body">
                        <span class="mkt-modal__store-name">{{ product.store.name }}</span>
                        <span class="mkt-modal__store-hint">Ver todos sus productos</span>
                    </span>
                    <mkt-icon name="chevron-right" :size="18"/>
                </button>

                <!-- Sin wa_link: abre la puerta de contacto, que genera el
                     enlace en el servidor y registra la solicitud. -->
                <button type="button" class="mkt-wa mkt-modal__cta" @click="$emit('contact', product)">
                    <mkt-icon name="whatsapp" :size="24"/> Pedir por WhatsApp
                </button>

                <mkt-cart-button :product="product" variant="full" @added="$emit('added')"/>

                <mkt-recommend :product="product" :prefix="prefix" variant="full" class="mkt-modal__reco"/>

                <p class="mkt-modal__note">Coordinas precio y entrega directamente con la tienda.</p>

                <button type="button" class="mkt-modal__report" @click="$emit('report', product)">
                    Denunciar este producto
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import MktIcon from './MktIcon.vue'
import MktRecommend from './MktRecommend.vue'
import MktCartButton from './MktCartButton.vue'
import { formatPrice } from '../format'

export default {
    name: 'MktProductModal',
    components: { MktIcon, MktRecommend, MktCartButton },
    props: {
        product: { type: Object, required: true },
        prefix: { type: String, default: 'marketplace' },
        currency: { type: String, default: 'S/' },
    },

    computed: {
        priceLabel() {
            return this.product.price != null ? formatPrice(this.product.price, this.currency) : ''
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
    },
}
</script>

<style scoped>
.mkt-modal {
    position: fixed;
    inset: 0;
    z-index: 60;
    background: rgba(2, 15, 60, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    animation: mkt-fade 200ms var(--ease-out);
}

.mkt-modal__box {
    background: var(--white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-xl);
    width: min(860px, 100%);
    max-height: 90vh;
    overflow: auto;
    display: grid;
    grid-template-columns: minmax(280px, 1fr) minmax(300px, 1fr);
}

@media (max-width: 720px) {
    .mkt-modal { padding: 0; align-items: flex-end; }
    .mkt-modal__box { grid-template-columns: 1fr; max-height: 94vh; border-radius: var(--radius-lg) var(--radius-lg) 0 0; }
}

.mkt-modal__media {
    background: var(--buho-cream);
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 380px;
}

@media (max-width: 720px) {
    .mkt-modal__media { min-height: 220px; }
}

.mkt-modal__media img { width: 100%; height: 100%; object-fit: cover; }

.mkt-modal__initial {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 96px;
    height: 96px;
    border-radius: 50%;
    background: var(--buho-navy-950);
    color: var(--white);
    font-weight: 800;
    font-size: 36px;
}

.mkt-modal__initial::after {
    content: '';
    position: absolute;
    right: -4px;
    top: -4px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--buho-pink-600);
    border: 4px solid var(--buho-cream);
}

.mkt-modal__body {
    padding: 32px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.mkt-modal__top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
}

.mkt-modal__close {
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

.mkt-modal__close:hover { background: var(--gray-200); }

.mkt-modal__title {
    margin: 0;
    font-size: var(--fs-h4);
    font-weight: 700;
    line-height: 1.35;
    color: var(--buho-navy-950);
    text-wrap: pretty;
}

.mkt-modal__code {
    margin: 0;
    font-size: var(--fs-caption);
    color: var(--color-text-muted);
}

.mkt-modal__code span { color: var(--buho-navy-950); font-weight: 600; }

.mkt-modal__price {
    margin: 0;
    font-size: var(--fs-h4);
    font-weight: 800;
    letter-spacing: -0.01em;
    color: var(--buho-navy-950);
}

/* pre-line: respeta los saltos de línea que la tienda escribió en la app. */
.mkt-modal__desc {
    margin: 0;
    font-size: var(--fs-body-sm);
    line-height: 1.6;
    color: var(--color-text-muted);
    white-space: pre-line;
}

.mkt-modal__store {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border: none;
    border-radius: var(--radius-md);
    background: var(--buho-cream);
    cursor: pointer;
    transition: background 160ms var(--ease-out);
    font-family: var(--font-sans);
    text-align: left;
    color: var(--color-text-muted);
}

.mkt-modal__store:hover { background: var(--gray-200); }
.mkt-modal__store-body { flex: 1; min-width: 0; }

.mkt-modal__store-name {
    display: block;
    font-weight: 700;
    font-size: var(--fs-body-sm);
    color: var(--buho-navy-950);
}

.mkt-modal__store-hint {
    display: block;
    font-size: var(--fs-micro);
    color: var(--color-text-muted);
}

.mkt-modal__cta {
    height: 56px;
    border-radius: var(--radius-full);
    font-size: var(--fs-body);
    margin-top: 4px;
}

/* Ancho completo pero secundario: no debe competir con el CTA de WhatsApp, que
   es la acción que de verdad importa aquí. */
.mkt-modal__reco { width: 100%; }

.mkt-modal__note {
    margin: 0;
    text-align: center;
    font-size: var(--fs-micro);
    color: var(--color-text-muted);
}

.mkt-modal__report {
    align-self: center;
    background: none;
    border: none;
    cursor: pointer;
    font-family: var(--font-sans);
    font-size: var(--fs-micro);
    color: var(--color-text-muted);
    text-decoration: underline;
    padding: 6px;
}
</style>
