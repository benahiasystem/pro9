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
            <div class="right-wrapper pull-right">
                <span class="badge badge-pill me-2" :class="state.is_enabled ? 'badge-success' : 'badge-secondary'">
                    {{ state.is_enabled ? 'Publicado' : 'Apagado' }}
                </span>
                <a :href="state.public_url" target="_blank" rel="noopener">
                    <button type="button" class="btn btn-custom btn-sm mt-2 me-2">
                        <i class="fa fa-eye"></i> Ver el marketplace
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
