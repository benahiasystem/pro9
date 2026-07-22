<template>
    <section class="mkt-store">
        <div class="mkt__wrap">
            <button type="button" class="mkt-store__back" @click="$emit('home')">
                <mkt-icon name="arrow-left" :size="16"/> Todo el marketplace
            </button>

            <div class="mkt-store__row">
                <span class="mkt-store__logo">
                    <img v-if="store.logo_url" :src="store.logo_url" :alt="store.name">
                    <span v-else class="avatar avatar--lg avatar--navy mkt-store__initials">{{ store.initials }}</span>
                </span>

                <div class="mkt-store__info">
                    <h1 class="mkt-store__name">
                        {{ store.name }}
                        <span v-if="store.is_new" class="mkt-new">Nuevo</span>
                    </h1>
                    <p v-if="store.description" class="mkt-store__desc">{{ store.description }}</p>
                    <div class="mkt-store__meta">
                        <!-- Con show_address apagado llega la zona referencial
                             (o nada); se marca como tal para que el vecino
                             sepa que no es la dirección exacta. -->
                        <span v-if="store.address">
                            <mkt-icon name="map-pin" :size="16"/> {{ store.address }}<template v-if="store.address_is_zone"> · zona referencial</template>
                        </span>
                        <span>
                            <mkt-icon name="building-store" :size="16"/>
                            <span class="mkt-mono">{{ store.items_count }}</span> productos
                        </span>
                        <span v-if="store.created_label" :title="store.created_on ? ('Desde el ' + store.created_on) : null">
                            <mkt-icon name="calendar" :size="16"/> {{ store.created_label }}
                        </span>
                        <span v-if="store.recommendations_count" class="mkt-store__reco"
                              :title="store.recommendations_count + ' recomendaciones'">
                            <mkt-icon name="heart" :size="16"/>
                            <span class="mkt-mono">{{ compact(store.recommendations_count) }}</span>
                            recomendaciones
                        </span>
                    </div>
                </div>

                <div class="mkt-store__actions">
                    <!-- Sin wa_link: la puerta de contacto genera el enlace en
                         el servidor y deja rastro de quién lo pidió. -->
                    <button type="button" class="mkt-wa mkt-store__wa" @click="$emit('contact')">
                        <mkt-icon name="whatsapp" :size="20"/> WhatsApp
                    </button>
                    <button type="button" class="btn btn--secondary" @click="$emit('share')">
                        <mkt-icon name="share" :size="18"/> Compartir
                    </button>
                    <button type="button" class="mkt-store__report" @click="$emit('report')">
                        Denunciar
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>

<script>
import MktIcon from './MktIcon.vue'
import { compactCount } from '../format'

export default {
    name: 'MktStoreHeader',
    components: { MktIcon },
    props: {
        store: { type: Object, required: true },
    },
    methods: {
        compact: compactCount,
    },
}
</script>

<style scoped>
.mkt-store {
    background: var(--buho-cream);
    border-bottom: 1px solid var(--color-border);
    padding: 32px 0;
}

.mkt-store__back {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: none;
    border: none;
    cursor: pointer;
    color: var(--color-text-muted);
    font-size: var(--fs-caption);
    font-family: var(--font-sans);
    padding: 0;
    margin-bottom: 20px;
}

.mkt-store__back:hover { color: var(--buho-navy-950); }

.mkt-store__row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 24px;
}

.mkt-store__logo { flex: none; }

.mkt-store__logo img,
.mkt-store__initials {
    width: 72px;
    height: 72px;
    border-radius: var(--radius-md);
    object-fit: cover;
    font-size: 24px;
}

.mkt-store__info { flex: 1; min-width: 260px; }

.mkt-store__name {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin: 0 0 6px;
    font-size: var(--fs-h3);
    font-weight: 700;
    letter-spacing: -0.01em;
    color: var(--buho-navy-950);
}

.mkt-store__desc {
    margin: 0 0 10px;
    color: var(--color-text-muted);
    font-size: var(--fs-body-sm);
    max-width: 560px;
    line-height: 1.55;
}

.mkt-store__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: center;
    font-size: var(--fs-caption);
    color: var(--color-text-muted);
}

.mkt-store__meta span { display: inline-flex; align-items: center; gap: 6px; }

.mkt-store__reco { color: var(--buho-pink-600); font-weight: 600; }
.mkt-store__reco svg { fill: currentColor; }

.mkt-store__actions {
    display: flex;
    gap: 12px;
    flex: none;
    align-items: center;
    flex-wrap: wrap;
}

.mkt-store__wa {
    height: 48px;
    padding: 0 22px;
    border-radius: var(--radius-full);
    font-size: var(--fs-body-sm);
}

.mkt-store__report {
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
