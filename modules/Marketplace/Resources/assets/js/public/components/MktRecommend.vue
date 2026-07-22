<template>
    <button type="button" class="mkt-reco" :class="[`mkt-reco--${variant}`, { 'is-on': done }]"
            :aria-pressed="done ? 'true' : 'false'"
            :aria-label="done ? 'Quitar recomendación' : 'Recomendar este producto'"
            :disabled="busy"
            @click.stop="toggle">
        <mkt-icon name="heart" :size="variant === 'full' ? 20 : 18"/>
        <span v-if="variant === 'full'" class="mkt-reco__label">
            {{ done ? 'Recomendado' : 'Recomendar' }}
        </span>
    </button>
</template>

<script>
import MktIcon from './MktIcon.vue'
import recommendations from '../recommendations'

/**
 * El corazón: la interacción más barata del marketplace.
 *
 * Reversible y honesto. Un clic recomienda, otro lo quita; el estado sale del
 * servidor, así que el corazón refleja siempre lo que de verdad hay registrado,
 * no una suposición. Sin número en el producto —el número es de la tienda—, sin
 * confirmación y sin toast: al contrario que la denuncia, que sí abre un
 * diálogo porque tiene consecuencias.
 */
export default {
    name: 'MktRecommend',

    components: { MktIcon },

    props: {
        product: { type: Object, required: true },
        prefix: { type: String, default: 'marketplace' },
        // 'compact' en la grilla (solo el pulgar), 'full' en el modal, donde
        // hay sitio para la etiqueta.
        variant: { type: String, default: 'compact' },
    },

    data() {
        return { busy: false }
    },

    computed: {
        done() {
            return recommendations.has(this.product.id)
        },
    },

    methods: {
        toggle() {
            // Un clic mientras la petición anterior sigue en vuelo dejaría el
            // estado y el servidor en desacuerdo; se ignora hasta que resuelva.
            if (this.busy) return

            const recomendar = !this.done
            const id = this.product.id

            // Optimista: el pulgar cambia ya. La respuesta no trae nada que
            // pintar, solo confirma; si falla, se revierte al estado previo.
            recomendar ? recommendations.mark(id) : recommendations.unmark(id)
            this.busy = true

            this.$http.post(`/${this.prefix}/recomendacion`, { item_id: id, recomendar })
                .catch(() => {
                    recomendar ? recommendations.unmark(id) : recommendations.mark(id)
                })
                .finally(() => { this.busy = false })
        },
    },
}
</script>

<style scoped>
.mkt-reco {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    border: 1px solid var(--color-border);
    background: var(--white);
    color: var(--color-text-muted);
    font-family: var(--font-sans);
    font-weight: 600;
    border-radius: var(--radius-full);
    transition: all 160ms var(--ease-out);
}

.mkt-reco:hover { border-color: var(--buho-pink-600); color: var(--buho-pink-600); }
.mkt-reco:disabled { cursor: default; }

/* Ya recomendado: relleno sólido. El fill de CSS gana al fill="none" que trae
   el svg como atributo de presentación, así que no hace falta un segundo icono.
   El selector alcanza al svg porque es el nodo raíz de MktIcon, y la raíz de un
   hijo sí hereda el scope del padre. */
.mkt-reco.is-on {
    border-color: var(--buho-pink-600);
    color: var(--buho-pink-600);
    background: var(--buho-cream);
}

.mkt-reco.is-on svg { fill: currentColor; }

.mkt-reco--compact { width: 44px; height: 44px; flex: none; }

.mkt-reco--full {
    height: 48px;
    padding: 0 20px;
    font-size: var(--fs-body-sm);
}
</style>
