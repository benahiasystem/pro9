<template>
    <article class="mkt-card">
        <div class="mkt-card__media" role="button" tabindex="0" :aria-label="product.name"
             @click="$emit('open', product)" @keydown.enter="$emit('open', product)">
            <img v-if="product.image_url" :src="product.image_url" :alt="product.name" loading="lazy">
            <span v-else class="mkt-card__placeholder">
                <span class="mkt-card__initial">{{ product.initial }}</span>
                <span class="mkt-card__store-name">{{ product.store.name }}</span>
            </span>
        </div>

        <div class="mkt-card__body">
            <h3 class="mkt-card__title" role="button" tabindex="0"
                @click="$emit('open', product)" @keydown.enter="$emit('open', product)">
                {{ product.name }}
            </h3>

            <div v-if="priceLabel" class="mkt-card__price">{{ priceLabel }}</div>

            <div class="mkt-card__foot">
                <button type="button" class="mkt-card__store" @click="$emit('open-store', product.store)">
                    <span class="avatar avatar--sm avatar--navy">{{ product.store.initials }}</span>
                    <span class="mkt-card__store-label">{{ product.store.name }}</span>
                </button>

                <div class="mkt-card__actions">
                    <mkt-recommend :product="product" :prefix="prefix"/>
                    <mkt-cart-button :product="product" @added="$emit('added')"/>

                    <!-- Sin wa_link: el enlace nace en la puerta de contacto,
                         que registra al comprador antes de entregarlo. -->
                    <button type="button" class="mkt-card__wa" aria-label="Escribir por WhatsApp"
                            @click="$emit('contact', product)">
                        <mkt-icon name="whatsapp" :size="22"/>
                    </button>
                </div>
            </div>
        </div>
    </article>
</template>

<script>
import MktIcon from './MktIcon.vue'
import MktRecommend from './MktRecommend.vue'
import MktCartButton from './MktCartButton.vue'
import { formatPrice } from '../format'

export default {
    name: 'MktProductCard',
    components: { MktIcon, MktRecommend, MktCartButton },
    props: {
        product: { type: Object, required: true },
        prefix: { type: String, default: 'marketplace' },
        currency: { type: String, default: 'S/' },
    },
    computed: {
        // null cuando la tienda no muestra precios; el backend ya lo omite.
        priceLabel() {
            return this.product.price != null ? formatPrice(this.product.price, this.currency) : ''
        },
    },
}
</script>

<style scoped>
.mkt-card {
    background: var(--white);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: border-color 200ms var(--ease-out), box-shadow 200ms var(--ease-out), transform 200ms var(--ease-out);
    animation: mkt-fade 240ms var(--ease-out);
}

.mkt-card:hover {
    border-color: var(--buho-blue-400);
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.mkt-card__media {
    cursor: pointer;
    aspect-ratio: 1 / 1;
    position: relative;
    overflow: hidden;
    background: var(--buho-cream);
    display: flex;
    align-items: center;
    justify-content: center;
}

.mkt-card__media img { width: 100%; height: 100%; object-fit: cover; }

.mkt-card__placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
}

/* Sin foto: inicial del producto y nombre de la tienda. Es lo que evita
   depender de que todas las tiendas suban imágenes. */
.mkt-card__initial {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: var(--buho-navy-950);
    color: var(--white);
    font-weight: 800;
    font-size: 24px;
}

.mkt-card__initial::after {
    content: '';
    position: absolute;
    right: -3px;
    top: -3px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: var(--buho-pink-600);
    border: 3px solid var(--buho-cream);
}

.mkt-card__store-name {
    font-size: var(--fs-micro);
    color: var(--color-text-muted);
    font-weight: 600;
}

.mkt-card__body {
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    flex: 1;
}

.mkt-card__title {
    margin: 0;
    cursor: pointer;
    font-size: var(--fs-body-sm);
    font-weight: 600;
    line-height: 1.4;
    color: var(--buho-navy-950);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 2.8em;
    text-wrap: pretty;
}

.mkt-card__title:hover { color: var(--buho-blue-600); }

.mkt-card__price {
    font-size: var(--fs-body);
    font-weight: 800;
    letter-spacing: -0.01em;
    color: var(--buho-navy-950);
    margin-top: -4px;
}

/* Dos filas: la tienda arriba, y debajo los tres botones de acción. Con tres
   acciones (recomendar, agregar, WhatsApp) ya no caben en la misma línea que el
   nombre de la tienda sin apretarse. */
.mkt-card__foot {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: auto;
}

.mkt-card__actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
}

.mkt-card__store {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    min-width: 0;
    font-family: var(--font-sans);
}

.mkt-card__store:hover { opacity: 0.75; }

.mkt-card__store .avatar { width: 24px; height: 24px; font-size: 9px; flex: none; }

.mkt-card__store-label {
    font-size: var(--fs-caption);
    font-weight: 600;
    color: var(--color-text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.mkt-card__wa {
    flex: none;
    width: 44px;
    height: 44px;
    border: none;
    padding: 0;
    cursor: pointer;
    border-radius: 50%;
    background: #25d366;
    color: var(--buho-navy-950);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 160ms var(--ease-out);
}

.mkt-card__wa:hover {
    transform: scale(1.06);
    box-shadow: 0 4px 12px rgba(37, 211, 102, 0.4);
}
</style>
