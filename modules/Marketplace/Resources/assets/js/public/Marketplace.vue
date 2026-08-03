<template>
    <div class="mkt">
        <header class="mkt-header">
            <div class="mkt-header__inner">
                <button type="button" class="mkt-header__brand" @click="goHome">
                    <img :src="logo" alt="Búho">
                    <span class="mkt-header__titles">
                        <span class="mkt-header__title">{{ settings.title || 'Marketplace' }}</span>
                        <span class="mkt-header__sub">by Búho</span>
                    </span>
                </button>
                <div v-if="settings.community_name" class="mkt-header__community">
                    <mkt-icon name="map-pin" :size="16"/>
                    <span>{{ settings.community_name }}</span>
                </div>
            </div>
        </header>

        <!-- Tienda no disponible: el enlace ya fue compartido, así que se
             responde 410 con una salida amable en vez de un 404 seco. -->
        <main v-if="gone" class="mkt-notice">
            <div class="mkt-notice__box">
                <img :src="logo" alt="">
                <h1>Esta tienda ya no está disponible</h1>
                <p>
                    El enlace que abriste pertenece a una tienda que ya no forma parte del
                    marketplace. Pero hay más vecinos publicando sus productos.
                </p>
                <button class="btn btn--primary" @click="goHome">
                    Ver el marketplace <span class="mkt-mono">→</span>
                </button>
            </div>
        </main>

        <main v-else class="mkt__main">
            <mkt-store-header v-if="store" :store="store"
                              @home="goHome" @share="share" @report="openReport(store)"
                              @contact="openGate({ type: 'store', store: store.slug })"/>

            <section class="mkt-search-band" :class="{ 'is-compact': !!store }">
                <div class="mkt__wrap">
                    <h1 v-if="!store && heroVisible" class="mkt-hero__title">
                        {{ settings.hero_title }}
                        <em v-if="settings.hero_highlight">{{ settings.hero_highlight }}</em>
                    </h1>

                    <mkt-search v-model="q" :prefix="prefix"
                                @pick-product="openProduct" @pick-store="goStore"/>
                </div>
            </section>

            <div class="mkt__wrap">
                <!-- Los tabs solo tienen sentido en la vista global: dentro de
                     una tienda no hay nada que alternar. -->
                <div v-if="!store" class="mkt-toolbar">
                    <div class="tabs-line" role="tablist">
                        <button role="tab" class="tab-line" :class="{ 'tab-line--active': tab === 'productos' }"
                                @click="setTab('productos')">
                            Productos <span class="mkt-mono mkt-count">{{ totals.products }}</span>
                        </button>
                        <button role="tab" class="tab-line" :class="{ 'tab-line--active': tab === 'tiendas' }"
                                @click="setTab('tiendas')">
                            Tiendas <span class="mkt-mono mkt-count">{{ totals.stores }}</span>
                        </button>
                    </div>

                    <!-- Fuera del tablist a propósito: no es una pestaña más,
                         es un orden que se aplica a la que esté activa. -->
                    <button type="button" class="tag mkt-sort"
                            :class="{ 'tag--active': orden === 'recomendados' }"
                            :aria-pressed="orden === 'recomendados' ? 'true' : 'false'"
                            @click="toggleOrden">
                        <mkt-icon name="heart" :size="14"/> Más recomendados
                    </button>
                </div>

                <div v-if="showCategories" class="mkt-pills">
                    <button v-for="c in categories" :key="c.slug" type="button"
                            class="tag" :class="{ 'tag--active': categoria === c.slug }"
                            @click="toggleCategory(c.slug)">
                        {{ c.name }}
                        <mkt-icon v-if="categoria === c.slug" name="x" :size="14"/>
                    </button>
                </div>

                <div v-if="chips.length" class="mkt-chips">
                    <span v-for="chip in chips" :key="chip.key" class="mkt-chip">
                        {{ chip.label }}
                        <button type="button" aria-label="Quitar filtro" @click="chip.remove()">
                            <mkt-icon name="x" :size="13"/>
                        </button>
                    </span>
                    <button type="button" class="mkt-chips__clear" @click="clearFilters">Limpiar todo</button>
                </div>

                <!-- Cargando -->
                <div v-if="loading" class="mkt-grid">
                    <div v-for="n in 8" :key="n" class="mkt-skeleton">
                        <div class="mkt-skeleton__media"></div>
                        <div class="mkt-skeleton__body">
                            <span class="mkt-skeleton__line" style="width: 90%"></span>
                            <span class="mkt-skeleton__line" style="width: 60%"></span>
                        </div>
                    </div>
                </div>

                <template v-else>
                    <!-- Marketplace todavía sin nada publicado -->
                    <div v-if="isEmptyMarket" class="empty mkt-empty">
                        <mkt-icon name="building-store" :size="40"/>
                        <h2>Las tiendas están en camino</h2>
                        <p>
                            Los negocios<template v-if="settings.community_name"> de {{ settings.community_name }}</template>
                            ya están preparando sus catálogos. Vuelve pronto para descubrir lo que venden tus vecinos.
                        </p>
                    </div>

                    <!-- Hay contenido, pero no para estos filtros -->
                    <div v-else-if="noResults" class="empty mkt-empty">
                        <mkt-icon name="zoom-question" :size="40"/>
                        <h2 v-if="q">No encontramos nada para «{{ q }}»</h2>
                        <h2 v-else>No hay productos con estos filtros</h2>
                        <p>Prueba con otra palabra o revisa las categorías.</p>
                        <button class="btn btn--secondary btn--sm" @click="clearFilters">Limpiar búsqueda</button>
                    </div>

                    <template v-else>
                        <!-- Productos -->
                        <div v-if="showProducts" role="list" class="mkt-grid">
                            <mkt-product-card v-for="p in products" :key="p.id" :product="p" :prefix="prefix"
                                              :currency="settings.currency_symbol"
                                              @open="openProduct" @open-store="goStore"
                                              @added="onAddedToCart" @contact="contactProduct"/>
                        </div>

                        <nav v-if="showProducts && lastPage > 1" class="mkt-pager" aria-label="Paginación">
                            <button class="btn btn--secondary btn--sm" :disabled="page === 1"
                                    aria-label="Página anterior" @click="goPage(page - 1)">
                                <mkt-icon name="chevron-left" :size="16"/>
                            </button>
                            <button v-for="n in pageNumbers" :key="n" class="tag"
                                    :class="{ 'tag--active': n === page }" @click="goPage(n)">
                                <span class="mkt-mono">{{ n }}</span>
                            </button>
                            <button class="btn btn--secondary btn--sm" :disabled="page === lastPage"
                                    aria-label="Página siguiente" @click="goPage(page + 1)">
                                <mkt-icon name="chevron-right" :size="16"/>
                            </button>
                        </nav>

                        <!-- Tiendas -->
                        <div v-if="showStores" role="list" class="mkt-grid mkt-grid--stores">
                            <button v-for="s in stores" :key="s.slug" type="button"
                                    class="mkt-store-card" @click="goStore(s)">
                                <span class="mkt-store-card__logo">
                                    <img v-if="s.logo_url" :src="s.logo_url" :alt="s.name">
                                    <span v-else class="avatar avatar--lg avatar--navy">{{ s.initials }}</span>
                                </span>
                                <span class="mkt-store-card__body">
                                    <span class="mkt-store-card__head">
                                        <span class="mkt-store-card__name">{{ s.name }}</span>
                                        <span v-if="s.is_new" class="mkt-new">Nuevo</span>
                                    </span>
                                    <span class="mkt-store-card__meta">
                                        <span class="mkt-mono">{{ s.items_count }}</span> productos
                                        <template v-if="s.main_category"> · {{ s.main_category }}</template>
                                    </span>
                                </span>
                                <!-- Aproximado (1k, 2.5k): a quien elige tienda
                                     el número exacto no le dice nada más. -->
                                <span v-if="s.recommendations_count" class="mkt-store-card__reco"
                                      :title="s.recommendations_count + ' recomendaciones'">
                                    <mkt-icon name="heart" :size="14"/>
                                    <span class="mkt-mono">{{ compact(s.recommendations_count) }}</span>
                                </span>
                                <mkt-icon name="chevron-right" :size="18"/>
                            </button>
                        </div>
                    </template>
                </template>
            </div>
        </main>

        <footer class="mkt-footer">
            <div class="mkt-footer__inner">
                <div class="mkt-footer__brand">
                    <img :src="logo" alt="">
                    <span>
                        {{ settings.title || 'Marketplace' }} · by Búho<template v-if="settings.community_name"> — {{ settings.community_name }}</template>
                    </span>
                </div>
                <div class="mkt-footer__links">
                    <!-- Si no hay T&C activos, el enlace no se muestra en absoluto. -->
                    <a v-if="termsUrl" :href="termsUrl" class="link link--muted">Términos y condiciones</a>
                </div>
            </div>
        </footer>

        <mkt-product-modal v-if="modalProduct" :product="modalProduct" :prefix="prefix"
                           :currency="settings.currency_symbol"
                           @close="closeProduct" @open-store="goStore" @report="openReport"
                           @added="onAddedToCart" @contact="contactProduct"/>

        <mkt-report-modal v-if="reportTarget" :target="reportTarget"
                          :reasons="settings.report_reasons || []" :prefix="prefix"
                          @close="reportTarget = null"/>

        <!-- El pedido: el FAB solo aparece con algo dentro y cede el paso al
             drawer mientras está abierto. -->
        <mkt-cart-fab v-if="cartCount > 0 && !cartOpen" @open="cartOpen = true"/>
        <mkt-cart-drawer v-if="cartOpen"
                         @close="cartOpen = false" @contact="contactCart"/>

        <!-- La puerta de contacto: TODO camino a WhatsApp pasa por aquí. -->
        <mkt-contact-gate v-if="gateTarget" :target="gateTarget" :prefix="prefix"
                          @close="gateTarget = null" @opened="onGateOpened"/>

        <!-- Aviso a pantalla completa al cruzar la mitad del throttle. -->
        <mkt-throttle-warn v-if="throttleWarn"
                           @continue="warnContinue" @cancel="warnCancel"/>

        <!-- En la vista 410 no hay nada que aceptar: no se registra nada ahí. -->
        <mkt-cookie-bar v-if="!gone" :terms-url="termsUrl" :arco-email="settings.arco_email || ''"/>

        <div v-if="toast" class="mkt-toast">
            <mkt-icon :name="toastIcon" :size="16"/> {{ toast }}
        </div>
    </div>
</template>

<script>
import MktIcon from './components/MktIcon.vue'
import MktSearch from './components/MktSearch.vue'
import MktProductCard from './components/MktProductCard.vue'
import MktProductModal from './components/MktProductModal.vue'
import MktReportModal from './components/MktReportModal.vue'
import MktStoreHeader from './components/MktStoreHeader.vue'
import MktCartFab from './components/MktCartFab.vue'
import MktCartDrawer from './components/MktCartDrawer.vue'
import MktContactGate from './components/MktContactGate.vue'
import MktThrottleWarn from './components/MktThrottleWarn.vue'
import MktCookieBar from './components/MktCookieBar.vue'
import recommendations from './recommendations'
import cart from './cart'
import usage from './contact-usage'
import { compactCount } from './format'
import logo from '../../img/buho-logo.svg'

export default {
    name: 'Marketplace',

    components: { MktIcon, MktSearch, MktProductCard, MktProductModal, MktReportModal, MktStoreHeader, MktCartFab, MktCartDrawer, MktContactGate, MktThrottleWarn, MktCookieBar },

    data() {
        // Todo lo que el servidor inyecta viaja en un único objeto, serializado
        // con @json() en el Blade.
        const boot = window.__marketplace || {}

        return {
            logo,
            settings: boot.settings || {},
            termsUrl: boot.terms_url || null,
            gone: !!boot.gone,
            prefix: boot.prefix || 'marketplace',
            store: boot.store || null,

            q: '',
            categoria: null,
            tab: 'productos',
            // null = alfabético (el de siempre) · 'recomendados' = ranking.
            orden: null,
            page: 1,

            loading: true,
            products: [],
            stores: [],
            categories: [],
            totals: { products: 0, stores: 0 },
            lastPage: 1,

            // Deep-link ?p={id}: el servidor ya resolvió el producto.
            modalProduct: boot.initial_item || null,
            reportTarget: null,
            toast: '',
            toastIcon: 'link',
            cartOpen: false,
            searchTimer: null,
            toastTimer: null,

            // La puerta de contacto y su aviso de throttle (plan de
            // seguridad, B1/B3). pendingGate guarda el destino mientras el
            // aviso a pantalla completa espera el «Entiendo».
            gateTarget: null,
            pendingGate: null,
            throttleWarn: false,
        }
    },

    computed: {
        heroVisible() {
            return !!(this.settings.hero_title || this.settings.hero_highlight)
        },

        showProducts() {
            return (this.tab === 'productos' || !!this.store) && this.products.length > 0
        },

        showStores() {
            return !this.store && this.tab === 'tiendas' && this.stores.length > 0
        },

        showCategories() {
            // Con una sola categoría el filtro no filtra nada.
            return (this.tab === 'productos' || !!this.store) && this.categories.length > 1
        },

        hasFilters() {
            return !!this.q.trim() || !!this.categoria
        },

        /** Nada publicado en todo el marketplace, y sin filtros de por medio. */
        isEmptyMarket() {
            return !this.hasFilters && this.totals.products === 0 && this.totals.stores === 0
        },

        noResults() {
            if (this.isEmptyMarket) return false
            return this.tab === 'tiendas' && !this.store
                ? this.stores.length === 0
                : this.products.length === 0
        },

        chips() {
            const chips = []

            if (this.categoria) {
                const cat = this.categories.find((c) => c.slug === this.categoria)
                chips.push({
                    key: 'cat',
                    label: 'Categoría: ' + (cat ? cat.name : this.categoria),
                    remove: () => { this.categoria = null; this.reload(1) },
                })
            }

            if (this.q.trim()) {
                chips.push({
                    key: 'q',
                    label: `Búsqueda: «${this.q.trim()}»`,
                    remove: () => { this.q = ''; this.reload(1) },
                })
            }

            return chips
        },

        /** Unidades en el pedido; mueve el FAB. Reactivo por cart.state observable. */
        cartCount() {
            return cart.count()
        },

        /** Ventana de 5 páginas alrededor de la actual. */
        pageNumbers() {
            const total = this.lastPage
            const start = Math.max(1, Math.min(this.page - 2, total - 4))
            const end = Math.min(total, start + 4)
            const out = []
            for (let n = start; n <= end; n++) out.push(n)
            return out
        },
    },

    watch: {
        q() {
            clearTimeout(this.searchTimer)
            this.searchTimer = setTimeout(() => this.reload(1), 300)
        },
    },

    created() {
        // Siembra el pulgar del producto del deep-link antes del primer feed,
        // por si no cae en la primera página. El feed rellena el resto.
        recommendations.hydrate((window.__marketplace || {}).recommended_ids)
        this.reload(1)
    },

    methods: {
        reload(page) {
            if (page) this.page = page
            this.loading = true

            this.$http.get(`/${this.prefix}/feed`, {
                params: {
                    q: this.q,
                    categoria: this.categoria,
                    tienda: this.store ? this.store.slug : null,
                    tab: this.tab,
                    orden: this.orden,
                    page: this.page,
                },
            }).then(({ data }) => {
                this.products = data.products.data
                this.lastPage = data.products.last_page
                this.stores = data.stores
                this.categories = data.categories
                this.totals = data.totals
                // Qué recomendó este visitante en la página que acaba de llegar.
                recommendations.hydrate(data.recommended)
            }).finally(() => { this.loading = false })
        },

        setTab(tab) {
            if (this.tab === tab) return
            this.tab = tab
            this.categoria = null
            // El orden se mantiene al cambiar de pestaña: en productos ordena
            // por las recomendaciones de cada producto y en tiendas por el
            // ranking de la tienda, así que sigue significando lo mismo.
            this.reload(1)
        },

        toggleOrden() {
            this.orden = this.orden === 'recomendados' ? null : 'recomendados'
            this.reload(1)
        },

        compact: compactCount,

        toggleCategory(slug) {
            this.categoria = this.categoria === slug ? null : slug
            this.reload(1)
        },

        clearFilters() {
            this.q = ''
            this.categoria = null
            // El watch de `q` ya dispara una recarga en 300 ms; se fuerza aquí
            // para que el clic responda de inmediato.
            clearTimeout(this.searchTimer)
            this.reload(1)
        },

        goPage(n) {
            if (n < 1 || n > this.lastPage) return
            this.reload(n)
            window.scrollTo({ top: 0, behavior: 'smooth' })
        },

        openProduct(product) {
            this.modalProduct = product
            this.pushDeepLink(product.id)
        },

        closeProduct() {
            this.modalProduct = null
            this.pushDeepLink(null)
        },

        /** Mantiene ?p={id} en la URL para que el modal se pueda compartir. */
        pushDeepLink(id) {
            if (!window.history || !window.history.replaceState) return
            const url = new URL(window.location.href)
            if (id) url.searchParams.set('p', id)
            else url.searchParams.delete('p')
            window.history.replaceState({}, '', url)
        },

        goHome() {
            window.location.href = `/${this.prefix}`
        },

        goStore(store) {
            window.location.href = `/${this.prefix}/tienda/${store.slug}`
        },

        openReport(target) {
            this.modalProduct = null
            this.reportTarget = target
        },

        share() {
            const url = this.store ? this.store.url : window.location.href

            if (navigator.share) {
                navigator.share({ title: this.store ? this.store.name : this.settings.title, url }).catch(() => {})
                return
            }

            const done = () => this.showToast('Enlace copiado — listo para pegar en WhatsApp')

            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(done).catch(() => this.fallbackCopy(url, done))
            } else {
                this.fallbackCopy(url, done)
            }
        },

        fallbackCopy(text, done) {
            const input = document.createElement('input')
            input.value = text
            document.body.appendChild(input)
            input.select()
            try { document.execCommand('copy'); done() } catch (e) { /* sin portapapeles */ }
            document.body.removeChild(input)
        },

        showToast(message, icon = 'link') {
            this.toast = message
            this.toastIcon = icon
            clearTimeout(this.toastTimer)
            this.toastTimer = setTimeout(() => { this.toast = '' }, 2200)
        },

        onAddedToCart() {
            this.showToast('Agregado a tu pedido', 'shopping-bag')
        },

        // -------------------------------------------------------------
        // La puerta de contacto (plan de seguridad, B1–B3)
        // -------------------------------------------------------------

        contactProduct(product) {
            this.modalProduct = null
            this.openGate({ type: 'product', store: product.store.slug, item_id: product.id })
        },

        /** Del drawer: un grupo del pedido → puerta con ítems y cantidades. */
        contactCart(group) {
            this.openGate({
                type: 'cart',
                store: group.store.slug,
                items: group.items.map((it) => ({ id: it.id, qty: it.qty })),
            })
        },

        /**
         * Todo camino a WhatsApp pasa por aquí. Si el uso registrado ya cruzó
         * la mitad del límite del servidor, primero el aviso a pantalla
         * completa; el destino queda en espera hasta el «Entiendo».
         */
        openGate(target) {
            if (usage.shouldWarn()) {
                this.pendingGate = target
                this.throttleWarn = true
                return
            }

            this.gateTarget = target
        },

        warnContinue() {
            usage.acknowledge()
            this.throttleWarn = false
            this.gateTarget = this.pendingGate
            this.pendingGate = null
        },

        warnCancel() {
            this.throttleWarn = false
            this.pendingGate = null
        },

        /** El comprador abrió WhatsApp desde el paso final del gate. */
        onGateOpened() {
            const target = this.gateTarget
            this.gateTarget = null

            // Pedido enviado: sus líneas salen del carrito para que no se
            // reenvíe por error. Si era la última tienda, el drawer se cierra
            // (quedaría vacío) y el FAB desaparece solo.
            if (target && target.type === 'cart') {
                cart.removeStore(target.store)
                if (cart.count() === 0) this.cartOpen = false
                this.showToast('Pedido enviado — abriendo WhatsApp…', 'whatsapp')
                return
            }

            this.showToast('Abriendo WhatsApp…', 'whatsapp')
        },
    },
}
</script>

<style scoped>
.mkt-search-band {
    background: var(--buho-cream);
    padding: 48px 0 40px;
}

.mkt-search-band.is-compact { background: var(--white); padding: 24px 0; }

.mkt-hero__title {
    margin: 0 0 28px;
    text-align: center;
    font-size: clamp(28px, 4vw, 40px);
    font-weight: 800;
    letter-spacing: -0.02em;
    color: var(--buho-navy-950);
}

.mkt-hero__title em { font-style: italic; font-weight: 300; color: var(--buho-pink-600); }

.mkt-count { font-size: var(--fs-micro); color: var(--color-text-muted); }

/* Las pestañas a la izquierda y el orden a la derecha. En móvil el orden cae
   debajo en vez de comprimir las pestañas. */
.mkt-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}

.mkt-sort {
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex: none;
}

.mkt-pills { display: flex; flex-wrap: wrap; gap: 8px; padding: 20px 0 4px; }
.mkt-pills .tag { cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }

.mkt-chips { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 12px 0 0; }

.mkt-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--buho-navy-950);
    color: var(--white);
    border-radius: var(--radius-full);
    padding: 6px 8px 6px 14px;
    font-size: var(--fs-caption);
    font-weight: 600;
}

.mkt-chip button {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    border: none;
    background: rgba(255, 255, 255, 0.16);
    color: var(--white);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
}

.mkt-chip button:hover { background: rgba(255, 255, 255, 0.3); }

.mkt-chips__clear {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--color-text-muted);
    font-size: var(--fs-caption);
    font-family: var(--font-sans);
    text-decoration: underline;
    padding: 4px;
}

.mkt-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(232px, 1fr));
    gap: 24px;
    padding: 28px 0 8px;
}

.mkt-grid--stores { grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }

.mkt-empty { margin: 32px 0 56px; }
.mkt-empty h2 { margin: 12px 0 6px; font-size: var(--fs-h4); font-weight: 700; color: var(--buho-navy-950); }
.mkt-empty p { margin: 0 auto 18px; max-width: 420px; font-size: var(--fs-body-sm); line-height: 1.6; }

.mkt-pager { display: flex; justify-content: center; align-items: center; gap: 8px; padding: 24px 0 8px; }
.mkt-pager .tag { cursor: pointer; min-width: 44px; height: 44px; justify-content: center; }

.mkt-store-card {
    cursor: pointer;
    background: var(--white);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all 200ms var(--ease-out);
    font-family: var(--font-sans);
    text-align: left;
    color: var(--color-text-muted);
}

.mkt-store-card:hover {
    border-color: var(--buho-blue-400);
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.mkt-store-card__logo { flex: none; }
.mkt-store-card__logo img { width: 56px; height: 56px; border-radius: var(--radius-md); object-fit: cover; }
.mkt-store-card__body { flex: 1; min-width: 0; }

/* El nombre trunca; el badge «Nuevo» nunca se encoge ni empuja la fila. */
.mkt-store-card__head { display: flex; align-items: center; gap: 8px; min-width: 0; }

.mkt-store-card__name {
    flex: 1;
    min-width: 0;
    font-weight: 700;
    font-size: var(--fs-body);
    color: var(--buho-navy-950);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.mkt-store-card__meta { display: block; font-size: var(--fs-caption); color: var(--color-text-muted); margin-top: 2px; }

.mkt-store-card__reco {
    flex: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 11px;
    border-radius: var(--radius-full);
    background: var(--buho-cream);
    color: var(--buho-pink-600);
    font-size: var(--fs-caption);
    font-weight: 700;
}

.mkt-store-card__reco svg { fill: currentColor; }

/* Skeletons: el shimmer viene del design system (@keyframes mkt-shimmer) */
.mkt-skeleton { background: var(--white); border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden; }

.mkt-skeleton__media {
    aspect-ratio: 1 / 1;
    background: linear-gradient(90deg, var(--gray-100) 25%, var(--buho-cream) 50%, var(--gray-100) 75%);
    background-size: 800px 100%;
    animation: mkt-shimmer 1.4s linear infinite;
}

.mkt-skeleton__body { padding: 16px; display: flex; flex-direction: column; gap: 10px; }
.mkt-skeleton__line { height: 14px; border-radius: 4px; background: var(--gray-100); }

.mkt-toast {
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 80;
    background: var(--buho-navy-950);
    color: var(--white);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-xl);
    padding: 12px 20px;
    font-size: var(--fs-caption);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    animation: mkt-fade 200ms var(--ease-out);
}
</style>
