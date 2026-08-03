<template>
    <div>
        <!-- Cabecera fuera del card, como el resto del panel (payment-orders, planes). -->
        <header class="page-header">
            <h2><a href="/marketplace/admin">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-building-store" style="margin-top: -3px;"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 21l18 0" /><path d="M3 7v1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1h-18l2 -4h14l2 4" /><path d="M5 21l0 -10.15" /><path d="M19 21l0 -10.15" /><path d="M9 21v-4a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v4" /></svg>
            </a></h2>
            <ol class="breadcrumbs">
                <li class="active"><span>Marketplace</span></li>
            </ol>

            <el-tooltip class="item" :content="stateHelp" effect="dark" placement="bottom-start">
                <button type="button" class="badge badge-pill mkt-state mt-2"
                        :class="state.is_enabled ? 'badge-success' : 'badge-warning'"
                        @click="tab = 'settings'">
                    <span class="mkt-state__dot"></span>
                    {{ state.is_enabled ? 'Publicado' : 'Apagado' }}
                </button>
            </el-tooltip>

            <div class="right-wrapper pull-right">
                <a :href="state.public_url" target="_blank" rel="noopener">
                    <button type="button" class="btn btn-custom btn-sm mt-2 me-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-eye me-1"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6" /></svg>
                        Ver el marketplace
                    </button>
                </a>
            </div>
        </header>

        <div class="card">
            <div class="card-body mx-2">
                <el-tabs v-model="tab">
                    <el-tab-pane name="stores">
                        <span slot="label">
                            Tiendas
                            <el-badge v-if="state.pending" :value="state.pending" type="warning" class="ms-1"/>
                        </span>
                        <stores-tab v-if="loaded.stores" @changed="refreshSummary"/>
                    </el-tab-pane>

                    <el-tab-pane name="reports">
                        <span slot="label">
                            Denuncias
                            <el-badge v-if="state.open_reports" :value="state.open_reports" type="danger" class="ms-1"/>
                        </span>
                        <reports-tab v-if="loaded.reports" @changed="refreshSummary"/>
                    </el-tab-pane>

                    <el-tab-pane label="Categorías" name="categories">
                        <categories-tab v-if="loaded.categories"/>
                    </el-tab-pane>

                    <el-tab-pane label="Ajustes" name="settings">
                        <settings-tab v-if="loaded.settings" @changed="refreshSummary"/>
                    </el-tab-pane>
                </el-tabs>
            </div>
        </div>
    </div>
</template>

<script>
import StoresTab from './tabs/StoresTab.vue'
import ReportsTab from './tabs/ReportsTab.vue'
import CategoriesTab from './tabs/CategoriesTab.vue'
import SettingsTab from './tabs/SettingsTab.vue'

export default {
    components: { StoresTab, ReportsTab, CategoriesTab, SettingsTab },

    props: {
        summary: { type: Object, required: true },
    },

    data() {
        return {
            tab: 'stores',
            // Copia local: la prop llega del Blade y no debe mutarse.
            state: { ...this.summary },
            // Cada tab carga su data solo cuando se abre por primera vez, y a
            // partir de ahí se mantiene montado para no perder filtros.
            loaded: { stores: true, reports: false, categories: false, settings: false },
        }
    },

    computed: {
        stateHelp() {
            return this.state.is_enabled
                ? 'El público puede navegar el marketplace y las apps sincronizan con normalidad. Clic para ir a Ajustes.'
                : 'Los visitantes ven una página de «no disponible» y las apps reciben un aviso, no un error. No se pierde ningún dato. Clic para ir a Ajustes y publicarlo.'
        },
    },

    watch: {
        tab(value) {
            this.loaded[value] = true
        },
    },

    methods: {
        refreshSummary() {
            this.$http.get('/marketplace/admin/settings').then(({ data }) => {
                this.state.is_enabled = !!data.data.is_enabled
            })
        },
    },
}
</script>

<style scoped>
.mkt-state {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-left: 14px;
    padding: 4px 12px;
    border: none;
    vertical-align: middle;
    font-size: 12px;
    cursor: pointer;
}
.mkt-state__dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: currentColor;
    flex: none;
}
</style>
