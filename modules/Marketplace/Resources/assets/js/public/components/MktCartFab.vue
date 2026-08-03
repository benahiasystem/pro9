<template>
    <button type="button" class="mkt-fab" aria-label="Ver mi pedido" @click="$emit('open')">
        <mkt-icon name="shopping-bag" :size="26"/>
        <span class="mkt-fab__count mkt-mono">{{ count }}</span>
    </button>
</template>

<script>
import MktIcon from './MktIcon.vue'
import cart from '../cart'

// Botón flotante que abre el pedido. Solo se monta cuando hay algo dentro (lo
// decide la pantalla con un v-if), así que aquí no hay estado vacío.
export default {
    name: 'MktCartFab',

    components: { MktIcon },

    computed: {
        count() {
            return cart.count()
        },
    },
}
</script>

<style scoped>
.mkt-fab {
    position: fixed;
    bottom: 28px;
    right: 28px;
    z-index: 55;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    border: none;
    background: var(--buho-navy-950);
    color: var(--white);
    cursor: pointer;
    box-shadow: var(--shadow-xl);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: transform 160ms var(--ease-out);
    animation: mkt-fade 200ms var(--ease-out);
}

.mkt-fab:hover { transform: translateY(-2px); }

.mkt-fab__count {
    position: absolute;
    top: -4px;
    right: -4px;
    min-width: 24px;
    height: 24px;
    border-radius: var(--radius-full);
    background: var(--buho-pink-600);
    color: var(--white);
    font-size: 12px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 7px;
    box-sizing: border-box;
}

@media (max-width: 640px) {
    .mkt-fab { bottom: 20px; right: 20px; }
}
</style>
