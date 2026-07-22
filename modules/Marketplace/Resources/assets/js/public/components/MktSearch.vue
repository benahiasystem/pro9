<template>
    <div class="mkt-search">
        <mkt-icon name="search" class="mkt-search__icon"/>
        <input class="input mkt-search__input" type="search" role="searchbox"
               aria-label="Buscar productos y tiendas"
               placeholder="Busca un producto o una tienda…"
               :value="value"
               @input="onInput"
               @focus="focused = true"
               @blur="onBlur"
               @keydown.enter="close">

        <div v-if="open" class="mkt-search__panel">
            <button v-for="p in suggestions.products" :key="'p' + p.id" type="button"
                    class="mkt-search__row" @mousedown.prevent="$emit('pick-product', p)">
                <span class="mkt-search__thumb">
                    <img v-if="p.image_url" :src="p.image_url" :alt="p.name" loading="lazy">
                    <mkt-icon v-else name="package" :size="18"/>
                </span>
                <span class="mkt-search__body">
                    <span class="mkt-search__name">{{ p.name }}</span>
                    <span class="mkt-search__store">{{ p.store.name }}</span>
                </span>
                <span v-if="p.internal_code" class="mkt-search__code mkt-mono">{{ p.internal_code }}</span>
            </button>

            <button v-for="s in suggestions.stores" :key="'s' + s.slug" type="button"
                    class="mkt-search__row mkt-search__row--store" @mousedown.prevent="$emit('pick-store', s)">
                <span class="avatar avatar--sm avatar--navy">{{ s.initials }}</span>
                <span class="mkt-search__body">
                    <span class="mkt-search__name">{{ s.name }}</span>
                </span>
                <span class="badge badge--navy badge--status">Tienda</span>
            </button>

            <p v-if="empty" class="mkt-search__none">
                Sin coincidencias — presiona Enter para ver resultados
            </p>
        </div>
    </div>
</template>

<script>
import MktIcon from './MktIcon.vue'

export default {
    name: 'MktSearch',

    components: { MktIcon },

    props: {
        value: { type: String, default: '' },
        prefix: { type: String, default: 'marketplace' },
    },

    data() {
        return {
            focused: false,
            suggestions: { products: [], stores: [] },
            timer: null,
        }
    },

    computed: {
        open() {
            return this.focused && this.value.trim().length > 0
        },
        empty() {
            return !this.suggestions.products.length && !this.suggestions.stores.length
        },
    },

    methods: {
        onInput(event) {
            const value = event.target.value
            this.$emit('input', value)

            // Debounce de 300 ms: escribir no debe disparar una petición por tecla.
            clearTimeout(this.timer)
            this.timer = setTimeout(() => this.fetch(value), 300)
        },

        fetch(q) {
            if (!q.trim()) {
                this.suggestions = { products: [], stores: [] }
                return
            }

            this.$http.get(`/${this.prefix}/feed`, { params: { suggest: 1, q } })
                .then(({ data }) => { this.suggestions = data })
                .catch(() => { this.suggestions = { products: [], stores: [] } })
        },

        onBlur() {
            // Margen para que el mousedown de una sugerencia llegue antes que el blur.
            setTimeout(() => { this.focused = false }, 150)
        },

        close() {
            this.focused = false
        },
    },
}
</script>

<style scoped>
.mkt-search {
    position: relative;
    max-width: 720px;
    margin: 0 auto;
}

.mkt-search__icon {
    position: absolute;
    left: 18px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-text-muted);
    pointer-events: none;
}

.mkt-search__input {
    width: 100%;
    box-sizing: border-box;
    height: 56px;
    padding-left: 48px;
    font-size: var(--fs-body);
    border-radius: var(--radius-full);
    background: var(--white);
}

.mkt-search__panel {
    position: absolute;
    top: 62px;
    left: 0;
    right: 0;
    background: var(--white);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-xl);
    overflow: hidden;
    z-index: 30;
    animation: mkt-fade 160ms var(--ease-out);
}

.mkt-search__row {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 10px 16px;
    background: none;
    border: none;
    cursor: pointer;
    text-align: left;
    font-family: var(--font-sans);
}

.mkt-search__row:hover { background: var(--buho-cream); }
.mkt-search__row--store { border-top: 1px solid var(--color-border); }

.mkt-search__thumb {
    width: 36px;
    height: 36px;
    border-radius: var(--radius-sm);
    background: var(--buho-cream);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--color-text-muted);
    flex: none;
    overflow: hidden;
}

.mkt-search__thumb img { width: 100%; height: 100%; object-fit: cover; }

.mkt-search__body { flex: 1; min-width: 0; }

.mkt-search__name {
    display: block;
    font-size: var(--fs-body-sm);
    font-weight: 600;
    color: var(--buho-navy-950);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.mkt-search__store {
    display: block;
    font-size: var(--fs-micro);
    color: var(--color-text-muted);
}

.mkt-search__code {
    font-size: var(--fs-micro);
    color: var(--color-text-muted);
    flex: none;
}

.mkt-search__none {
    padding: 14px 16px;
    margin: 0;
    font-size: var(--fs-caption);
    color: var(--color-text-muted);
}
</style>
