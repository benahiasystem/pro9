<template>
    <button type="button" class="mkt-add" :class="[`mkt-add--${variant}`, { 'is-in': inCart }]"
            :aria-label="inCart ? 'En tu pedido' : 'Agregar al pedido'"
            @click.stop="add">
        <mkt-icon :name="inCart ? 'check' : 'plus'" :size="variant === 'full' ? 20 : 20"/>
        <span v-if="variant === 'full'" class="mkt-add__label">
            {{ inCart ? 'En tu pedido' : 'Agregar al pedido' }}
        </span>
    </button>
</template>

<script>
import MktIcon from './MktIcon.vue'
import cart from '../cart'

/**
 * Añade el producto al pedido. Siempre suma una unidad (no alterna): la
 * cantidad y el quitar se manejan en el drawer, que es donde tiene sentido.
 * El check + «En tu pedido» solo indican que ya está dentro.
 */
export default {
    name: 'MktCartButton',

    components: { MktIcon },

    props: {
        product: { type: Object, required: true },
        // 'compact' en la grilla (solo icono), 'full' en el modal (con etiqueta).
        variant: { type: String, default: 'compact' },
    },

    computed: {
        inCart() {
            return cart.has(this.product.id)
        },
    },

    methods: {
        add() {
            cart.add(this.product)
            // El toast lo muestra la pantalla, que es quien lo posee.
            this.$emit('added')
        },
    },
}
</script>

<style scoped>
.mkt-add {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    cursor: pointer;
    border: 1px solid var(--color-border);
    background: var(--white);
    color: var(--buho-navy-950);
    font-family: var(--font-sans);
    font-weight: 700;
    border-radius: var(--radius-full);
    transition: all 160ms var(--ease-out);
}

.mkt-add:hover { border-color: var(--buho-navy-950); }

/* Ya en el pedido: relleno navy sólido, como en el mockup. */
.mkt-add.is-in {
    background: var(--buho-navy-950);
    border-color: var(--buho-navy-950);
    color: var(--white);
}

.mkt-add--compact { width: 44px; height: 44px; flex: none; }

.mkt-add--full {
    width: 100%;
    height: 52px;
    font-size: var(--fs-body-sm);
    border-width: 1.5px;
}
</style>
