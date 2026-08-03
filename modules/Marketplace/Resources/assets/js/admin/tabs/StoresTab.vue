<template>
    <div>
        <div class="btn-filter-content mb-3 d-flex">
            <el-button type="secondary" class="btn-show-filter" :class="{ shift: isFiltersVisible }"
                       @click="isFiltersVisible = !isFiltersVisible">
                {{ isFiltersVisible ? 'Ocultar filtros' : 'Mostrar filtros' }}
            </el-button>
            <el-button v-if="isFiltersVisible" type="secondary" icon="el-icon-search" @click="load(1)">
                Aplicar filtros
            </el-button>
            <el-button v-if="filtersChanged" type="secondary" icon="el-icon-refresh"
                       @click="initFilters(); load(1)">
                Limpiar filtros
            </el-button>
        </div>

        <div v-if="isFiltersVisible" class="filter-section mb-3">
            <div class="row">
                <div class="form-group col-lg-3 col-md-6 col-sm-12 mb-2">
                    <label class="control-label">Estado</label>
                    <el-select v-model="filters.status" placeholder="Todos los estados" clearable @change="load(1)">
                        <el-option v-for="s in statuses" :key="s.value" :label="s.label" :value="s.value"/>
                    </el-select>
                </div>
                <div class="form-group col-lg-3 col-md-6 col-sm-12 mb-2">
                    <label class="control-label">Buscar</label>
                    <el-input v-model="filters.q" placeholder="Nombre o RUC" clearable
                              prefix-icon="el-icon-search"
                              @keyup.enter.native="load(1)" @clear="load(1)"/>
                </div>
            </div>
        </div>

        <div class="table-responsive" v-loading="loading">
            <table class="table">
                <thead>
                <tr>
                    <th style="width: 40px;"></th>
                    <th>Tienda</th>
                    <th>WhatsApp</th>
                    <th class="text-center">Productos</th>
                    <th class="text-center">Denuncias</th>
                    <th class="text-center">Recomendaciones</th>
                    <th>Estado</th>
                    <th>Último sync</th>
                    <th class="text-end">Acciones</th>
                </tr>
                </thead>
                <tbody>
                <template v-for="row in records">
                    <tr :key="row.id">
                        <td>
                            <button type="button" class="mkt-expand"
                                    :class="{ 'is-open': isExpanded(row.id) }"
                                    :title="isExpanded(row.id) ? 'Ocultar catálogo' : 'Ver catálogo'"
                                    @click.prevent="toggleRow(row)">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <img v-if="row.logo_url" :src="row.logo_url" class="mkt-logo" :alt="row.name">
                                <span v-else class="mkt-logo mkt-logo--placeholder">{{ row.name.charAt(0) }}</span>
                                <div>
                                    <strong>{{ row.name }}</strong><br>
                                    <small class="text-muted">{{ row.tax_id || 'Sin RUC' }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            {{ row.whatsapp }}
                            <a :href="'https://wa.me/' + row.whatsapp" target="_blank" rel="noopener nofollow">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-external-link text-dark" style="margin-top: -5px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M12 6h-6a2 2 0 0 0 -2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-6" /><path d="M11 13l9 -9" /><path d="M15 4h5v5" /></svg>
                            </a>
                        </td>
                        <td class="text-center">{{ row.items_count }}</td>
                        <td class="text-center">
                            <span v-if="row.reports_count" class="badge badge-pill badge-danger">
                                {{ row.reports_count }}
                            </span>
                            <span v-else class="text-muted">—</span>
                        </td>
                        <!-- Exacto, no abreviado: el público ve «2.5k», el admin necesita el número. -->
                        <td class="text-center">
                            <span v-if="row.recommendations_count" class="badge badge-pill badge-success">
                                {{ row.recommendations_count }}
                            </span>
                            <span v-else class="text-muted">—</span>
                        </td>
                        <td>
                            <span class="badge badge-pill" :class="statusClass(row.status)">
                                {{ statusLabel(row.status) }}
                            </span>
                            <el-tooltip v-if="row.status_reason" :content="row.status_reason" placement="top">
                                <i class="el-icon-info text-muted ms-1"></i>
                            </el-tooltip>

                            <!-- «Ocultar mi tienda»: sigue aprobada, pero se despublicó
                                 ella misma. El admin no tiene que hacer nada, solo saberlo. -->
                            <div v-if="row.hidden">
                                <span class="badge badge-pill badge-secondary mt-1"
                                      title="La tienda se ocultó a sí misma desde su app. Volverá al sincronizar.">
                                    <i class="fas fa-eye-slash"></i> Oculta por la tienda
                                </span>
                            </div>

                            <!-- Acción requerida (alertas de seguridad sin revisar):
                                 mismo trato visual que una tienda pendiente de aprobar.
                                 Se atiende desde el menú de acciones de la fila. -->
                            <div v-for="alert in row.alerts" :key="alert.id">
                                <el-tooltip :content="alert.message" placement="top">
                                    <span class="badge badge-pill badge-warning mt-1 mkt-alert-badge">
                                        <i class="fas fa-exclamation-triangle"></i> {{ alertLabel(alert.type) }}
                                    </span>
                                </el-tooltip>
                            </div>
                        </td>
                        <td><small>{{ row.last_synced_at || '—' }}</small></td>
                        <td class="text-end">
                            <el-dropdown trigger="click">
                                <el-button type="text" class="dropdown-trigger">
                                    <i class="fas fa-ellipsis-v"></i>
                                </el-button>
                                <el-dropdown-menu slot="dropdown">
                                    <el-dropdown-item v-if="row.status !== 'approved'"
                                                      @click.native="approve(row)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-check me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M5 12l5 5l10 -10" /></svg>
                                        {{ row.status === 'disabled' ? 'Habilitar' : 'Aprobar' }}
                                    </el-dropdown-item>
                                    <el-dropdown-item v-if="row.status === 'pending'"
                                                      @click.native="askReason(row, 'reject')">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-x me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M18 6l-12 12" /><path d="M6 6l12 12" /></svg>
                                        Rechazar
                                    </el-dropdown-item>
                                    <el-dropdown-item v-if="row.status === 'approved'"
                                                      @click.native="askReason(row, 'disable')">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-circle-minus me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M9 12l6 0" /></svg>
                                        Deshabilitar
                                    </el-dropdown-item>
                                    <!-- Atender la alerta = revisarla y marcarla. El
                                         mensaje completo está en el tooltip del badge. -->
                                    <el-dropdown-item v-for="alert in row.alerts" :key="alert.id"
                                                      @click.native="markAlertRead(alert)" class="text-warning option-warning">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-check me-2 text-warning"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M5 12l5 5l10 -10" /></svg>
                                        Revisado: {{ alertLabel(alert.type) }}
                                    </el-dropdown-item>

                                    <!-- Ancla nativa a ancho completo del ítem (no
                                         window.open): la descarga la maneja el
                                         navegador y ningún bloqueador de popups la
                                         corta. Sin target: un CSV con
                                         Content-Disposition no navega, descarga. -->
                                    <el-dropdown-item :disabled="!row.contacts_count">
                                        <a v-if="row.contacts_count"
                                           :href="'/marketplace/admin/contact-requests?export=1&store_id=' + row.id"
                                           class="mkt-drop-link">
                                           <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-download me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" /><path d="M7 11l5 5l5 -5" /><path d="M12 4l0 12" /></svg>
                                            Contactos ({{ row.contacts_count }}) — CSV
                                        </a>
                                        <template v-else>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-download me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" /><path d="M7 11l5 5l5 -5" /><path d="M12 4l0 12" /></svg>
                                            Contactos (0) — CSV
                                        </template>
                                    </el-dropdown-item>

                                    <!-- Para cuando la tienda cambió o perdió su
                                         dispositivo: el próximo sync emite credencial
                                         nueva (trust-on-first-use). -->
                                    <el-dropdown-item v-if="row.has_secret" @click.native="askResetSecret(row)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-key me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M16.555 3.843l3.602 3.602a2.877 2.877 0 0 1 0 4.069l-2.643 2.643a2.877 2.877 0 0 1 -4.069 0l-.301 -.301l-6.558 6.558a2 2 0 0 1 -1.239 .578l-.175 .008h-1.172a1 1 0 0 1 -.993 -.883l-.007 -.117v-1.172a2 2 0 0 1 .467 -1.284l.119 -.13l.414 -.414h2v-2h2v-2l2.144 -2.144l-.301 -.301a2.877 2.877 0 0 1 0 -4.069l2.643 -2.643a2.877 2.877 0 0 1 4.069 0" /><path d="M15 9h.01" /></svg>
                                        Restablecer credencial
                                    </el-dropdown-item>

                                    <!-- Ancla nativa que cubre TODO el ítem (margen
                                         negativo contra el padding del li): el
                                         problema original era que solo el texto del
                                         <a> era clickeable y el resto del ítem cerraba
                                         el menú sin navegar. -->
                                    <el-dropdown-item v-if="row.public_url">
                                        <a :href="row.public_url" target="_blank" rel="noopener"
                                           class="mkt-drop-link">
                                           <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-eye me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6" /></svg> 
                                           Ver página pública
                                        </a>
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </el-dropdown>
                        </td>
                    </tr>

                    <tr v-if="isExpanded(row.id)" :key="row.id + '-catalog'" class="mkt-catalog-row">
                        <td colspan="9">
                            <div class="mkt-catalog" v-loading="catalogs[row.id] === 'loading'">
                                <div v-if="preview(row.id).length" class="mkt-catalog__grid">
                                    <item-card v-for="item in preview(row.id)" :key="item.id" :item="item"
                                               @block="askBlock($event, row.id)"
                                               @unblock="unblock($event, row.id)"/>
                                </div>

                                <p v-else-if="catalogs[row.id] !== 'loading'" class="mkt-catalog__empty">
                                    <i class="el-icon-box"></i>
                                    Esta tienda todavía no ha sincronizado productos.
                                </p>

                                <div v-if="preview(row.id).length" class="mkt-catalog__footer">
                                    <span class="mkt-catalog__count">
                                        <template v-if="previewTotal(row.id) > preview(row.id).length">
                                            Mostrando {{ preview(row.id).length }} de {{ previewTotal(row.id) }} productos
                                        </template>
                                        <template v-else>
                                            {{ previewTotal(row.id) }} producto{{ previewTotal(row.id) === 1 ? '' : 's' }}
                                        </template>
                                    </span>
                                    <button class="btn btn-sm second-buton" @click="openCatalog(row)">
                                        Ver todo el catálogo <i class="el-icon-arrow-right"></i>
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                </template>

                <tr v-if="!records.length && !loading">
                    <td colspan="9" class="text-center text-muted">No hay tiendas todavía</td>
                </tr>
                </tbody>
            </table>
        </div>

        <el-pagination v-if="pagination.total > pagination.per_page" class="mt-3" background
                       layout="prev, pager, next"
                       :current-page="pagination.current_page" :page-size="pagination.per_page"
                       :total="pagination.total" @current-change="load"/>

        <el-drawer :visible.sync="drawer.visible" :with-header="false" size="440px" direction="rtl"
                   custom-class="mkt-drawer" modal-class="mkt-catalog-modal" append-to-body>
            <div class="mkt-drawer__inner" v-if="drawer.store">
                <div class="theme-sidebar-header">
                    <div class="mkt-drawer__title">
                        <h4>Catálogo</h4>
                        <small>{{ drawer.store.name }}</small>
                    </div>
                    <button type="text" class="close-theme-sidebar" @click="drawer.visible = false">
                        <i class="el-icon-close"></i>
                    </button>
                </div>

                <div class="mkt-drawer__filters">
                    <el-input v-model="drawer.q" placeholder="Buscar por nombre o código" clearable
                              prefix-icon="el-icon-search" size="small"
                              @input="searchCatalog" @clear="searchCatalog"/>
                    <el-radio-group v-model="drawer.status" size="mini" class="mt-2"
                                    @change="loadCatalog">
                        <el-radio-button label="">Todos</el-radio-button>
                        <el-radio-button label="active">Activos</el-radio-button>
                        <el-radio-button label="blocked">Bloqueados</el-radio-button>
                        <el-radio-button label="inactive">Inactivos</el-radio-button>
                    </el-radio-group>
                </div>

                <div class="mkt-drawer__body" v-loading="drawer.loading">
                    <div v-if="drawer.items.length" class="mkt-drawer__list">
                        <item-card v-for="item in drawer.items" :key="item.id" :item="item"
                                   @block="askBlock($event, drawer.store.id)"
                                   @unblock="unblock($event, drawer.store.id)"/>
                    </div>
                    <p v-else-if="!drawer.loading" class="mkt-catalog__empty">
                        <i class="el-icon-search"></i>
                        No hay productos que coincidan.
                    </p>
                </div>

                <div class="mkt-drawer__footer">
                    <span>{{ drawer.total }} producto{{ drawer.total === 1 ? '' : 's' }}</span>
                    <small v-if="drawer.total > drawer.items.length" class="text-warning">
                        Mostrando los primeros {{ drawer.items.length }} · refina la búsqueda para ver el resto
                    </small>
                </div>
            </div>
        </el-drawer>

        <!-- Motivo obligatorio: la tienda lo lee tal cual en GET /status -->
        <el-dialog :visible.sync="reasonDialog.visible" :title="reasonDialog.title" width="460px"
                   append-to-body :close-on-click-modal="false">
            <div class="form-group" :class="{ 'has-danger': reasonDialog.error }">
                <p class="text-muted">{{ reasonDialog.hint }}</p>
                <label class="control-label">Motivo</label>
                <el-input v-model="reasonDialog.reason" type="textarea" :rows="3" maxlength="500" show-word-limit
                          placeholder="Explica qué debe corregir la tienda"/>
                <small v-if="reasonDialog.error" class="form-control-feedback" v-text="reasonDialog.error"></small>
            </div>
            <span slot="footer" class="dialog-footer">
                <el-button @click="reasonDialog.visible = false">Cancelar</el-button>
                <el-button type="primary" :loading="reasonDialog.loading" @click="submitReason">Confirmar</el-button>
            </span>
        </el-dialog>
    </div>
</template>

<script>
import ItemCard from '../partials/ItemCard.vue'

const PREVIEW_LIMIT = 6

export default {
    components: { ItemCard },

    data() {
        return {
            loading: false,
            isFiltersVisible: false,
            records: [],
            catalogs: {},
            expandedRows: [],
            drawer: { visible: false, loading: false, store: null, items: [], total: 0, q: '', status: '' },
            filters: {},
            originalFilters: {},
            pagination: { current_page: 1, per_page: 20, total: 0 },
            statuses: [
                { value: 'pending', label: 'Pendientes' },
                { value: 'approved', label: 'Aprobadas' },
                { value: 'rejected', label: 'Rechazadas' },
                { value: 'disabled', label: 'Deshabilitadas' },
            ],
            reasonDialog: { visible: false, loading: false, reason: '', error: null, action: null, store: null, title: '', hint: '' },
        }
    },

    computed: {
        filtersChanged() {
            return JSON.stringify(this.filters) !== JSON.stringify(this.originalFilters)
        },
    },

    created() {
        this.initFilters()
        this.load(1)
    },

    beforeDestroy() {
        clearTimeout(this.searchTimer)
    },

    methods: {
        initFilters() {
            this.filters = { status: null, q: '' }
            this.originalFilters = { ...this.filters }
        },

        load(page = 1) {
            this.loading = true
            this.$http.get('/marketplace/admin/stores', {
                params: { page, status: this.filters.status, q: this.filters.q },
            }).then(({ data }) => {
                this.records = data.data
                this.pagination = { current_page: data.current_page, per_page: data.per_page, total: data.total }

                const ids = data.data.map((r) => r.id)
                this.expandedRows = this.expandedRows.filter((id) => ids.indexOf(id) !== -1)
                this.catalogs = {}
                this.expandedRows.forEach((id) => this.fetchPreview(id))
            }).finally(() => { this.loading = false })
        },

        isExpanded(storeId) {
            return this.expandedRows.indexOf(storeId) !== -1
        },

        toggleRow(row) {
            const index = this.expandedRows.indexOf(row.id)

            if (index !== -1) {
                this.expandedRows.splice(index, 1)
                return
            }

            this.expandedRows.push(row.id)

            if (this.catalogs[row.id] === undefined) {
                this.fetchPreview(row.id)
            }
        },

        fetchPreview(storeId) {
            this.$set(this.catalogs, storeId, 'loading')
            this.$http.get(`/marketplace/admin/stores/${storeId}/items`, { params: { limit: PREVIEW_LIMIT } })
                .then(({ data }) => this.$set(this.catalogs, storeId, { items: data.data, total: data.total }))
                .catch(() => this.$set(this.catalogs, storeId, { items: [], total: 0 }))
        },

        preview(storeId) {
            const value = this.catalogs[storeId]
            return (value === undefined || value === 'loading') ? [] : value.items
        },

        previewTotal(storeId) {
            const value = this.catalogs[storeId]
            return (value === undefined || value === 'loading') ? 0 : value.total
        },

        openCatalog(store) {
            this.drawer = { visible: true, loading: true, store, items: [], total: 0, q: '', status: '' }
            this.loadCatalog()
        },

        loadCatalog() {
            if (!this.drawer.store) {
                return
            }

            const storeId = this.drawer.store.id
            this.drawer.loading = true

            this.$http.get(`/marketplace/admin/stores/${storeId}/items`, {
                params: { q: this.drawer.q, status: this.drawer.status },
            }).then(({ data }) => {
                if (this.drawer.store && this.drawer.store.id === storeId) {
                    this.drawer.items = data.data
                    this.drawer.total = data.total
                }
            }).finally(() => { this.drawer.loading = false })
        },

        searchCatalog() {
            clearTimeout(this.searchTimer)
            this.searchTimer = setTimeout(this.loadCatalog, 300)
        },

        statusLabel(status) {
            return { pending: 'Pendiente', approved: 'Aprobada', rejected: 'Rechazada', disabled: 'Deshabilitada' }[status]
        },

        alertLabel(type) {
            return {
                whatsapp_changed: 'Cambió su WhatsApp',
                secret_reset: 'Credencial restablecida',
                feed_sweep: 'Barrido del catálogo',
            }[type] || 'Alerta de seguridad'
        },

        markAlertRead(alert) {
            this.$http.post(`/marketplace/admin/alerts/${alert.id}/read`).then(() => {
                this.$message.success('Alerta marcada como revisada.')
                this.load(this.pagination.current_page)
            }).catch(this.onError)
        },


        askResetSecret(store) {
            this.$confirm(
                'El dispositivo actual dejará de poder sincronizar y el próximo sync emitirá una credencial nueva. Hazlo solo si la tienda cambió o perdió su equipo.',
                `Restablecer la credencial de «${store.name}»`,
                { confirmButtonText: 'Restablecer', cancelButtonText: 'Cancelar', type: 'warning' },
            ).then(() => {
                this.$http.post(`/marketplace/admin/stores/${store.id}/reset-secret`).then(({ data }) => {
                    this.$message.success(data.message)
                    this.load(this.pagination.current_page)
                }).catch(this.onError)
            }).catch(() => {})
        },

        statusClass(status) {
            return {
                pending: 'badge-warning',
                approved: 'badge-success',
                rejected: 'badge-secondary',
                disabled: 'badge-danger',
            }[status]
        },

        approve(store) {
            const action = store.status === 'disabled' ? 'enable' : 'approve'
            this.$http.post(`/marketplace/admin/stores/${store.id}/${action}`).then(({ data }) => {
                this.$message.success(data.message)
                this.load(this.pagination.current_page)
                this.$emit('changed')
            }).catch(this.onError)
        },

        askReason(store, action) {
            this.reasonDialog = {
                visible: true,
                loading: false,
                reason: '',
                error: null,
                action,
                store,
                title: action === 'reject' ? `Rechazar «${store.name}»` : `Deshabilitar «${store.name}»`,
                hint: action === 'reject'
                    ? 'La tienda verá este motivo en su app. Es lo único que le dice qué corregir antes de reenviar.'
                    : 'Sus productos dejarán de ser visibles de inmediato. La tienda verá este motivo en su app.',
            }
        },

        submitReason() {
            const { store, action, reason } = this.reasonDialog

            if (!reason.trim()) {
                this.reasonDialog.error = 'El motivo es obligatorio.'
                return
            }

            this.reasonDialog.error = null
            this.reasonDialog.loading = true

            this.$http.post(`/marketplace/admin/stores/${store.id}/${action}`, { reason })
                .then(({ data }) => {
                    this.$message.success(data.message)
                    this.reasonDialog.visible = false
                    this.load(this.pagination.current_page)
                    this.$emit('changed')
                })
                .catch((error) => {
                    const message = error.response && error.response.data && error.response.data.message
                    this.reasonDialog.error = typeof message === 'object'
                        ? Object.values(message)[0][0]
                        : (message || 'Ocurrió un error.')
                })
                .finally(() => { this.reasonDialog.loading = false })
        },

        askBlock(item, storeId) {
            this.$prompt('Motivo del bloqueo', `Bloquear «${item.name}»`, {
                confirmButtonText: 'Bloquear',
                cancelButtonText: 'Cancelar',
                inputPlaceholder: 'Ej. contenido inapropiado',
                inputValidator: (v) => (v && v.trim() ? true : 'El motivo es obligatorio'),
            }).then(({ value }) => {
                this.$http.post(`/marketplace/admin/items/${item.id}/block`, { reason: value }).then(({ data }) => {
                    this.$message.success(data.message)
                    this.afterItemChange(storeId)
                }).catch(this.onError)
            }).catch(() => {})
        },

        unblock(item, storeId) {
            this.$http.post(`/marketplace/admin/items/${item.id}/unblock`).then(({ data }) => {
                this.$message.success(data.message)
                this.afterItemChange(storeId)
            }).catch(this.onError)
        },

        afterItemChange(storeId) {
            if (this.drawer.visible && this.drawer.store && this.drawer.store.id === storeId) {
                this.loadCatalog()
            }

            this.load(this.pagination.current_page)
        },

        onError(error) {
            const message = error.response && error.response.data && error.response.data.message
            this.$message.error(typeof message === 'object' ? Object.values(message)[0][0] : (message || 'Ocurrió un error.'))
        },
    },
}
</script>

<style scoped>
.mkt-drop-link {
    display: block;
    margin: 0 -20px;
    padding: 0 20px;
    color: inherit;
    text-decoration: none;
}
.mkt-expand {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    padding: 0;
    border: none;
    border-radius: 50%;
    background: transparent;
    color: var(--muted);
    font-size: 11px;
    cursor: pointer;
    transition: color .15s ease;
}
.mkt-expand:hover,
.mkt-expand:focus,
.mkt-expand.is-open {
    outline: none;
    color: var(--primary-color);
}
.mkt-expand i {
    transition: transform .2s ease;
}
.mkt-expand.is-open i {
    transform: rotate(90deg);
}

.mkt-logo {
    width: 34px; height: 34px; border-radius: 8px; object-fit: cover;
    margin-right: 10px; flex: none;
}
.mkt-logo--placeholder {
    display: inline-flex; align-items: center; justify-content: center;
    background: var(--accent-color); color: var(--dark-color);
    font-weight: 700; text-transform: uppercase;
}
.mkt-catalog-row > td {
    padding: 0 0 12px;
}
.mkt-catalog {
    padding-left: 40px;
}
.mkt-catalog__grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}
@media (max-width: 767px) {
    .mkt-catalog {
        padding-left: 0;
    }
}
.mkt-catalog__empty {
    margin: 0;
    padding: 8px 0;
    color: var(--muted);
    font-size: 13px;
}
.mkt-catalog__empty i {
    margin-right: 4px;
}
.mkt-catalog__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 12px;
    padding-top: 10px;
    border-top: 1px solid var(--accent-color);
}
.mkt-catalog__count {
    font-size: 12px;
    color: var(--muted);
}
.mkt-drawer__inner {
    display: flex;
    flex-direction: column;
    height: 100%;
}
.mkt-drawer__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 18px;
    background: #f2f4fa;
    border-bottom: 1px solid #e0e6f7;
}
.mkt-drawer__title h4 {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
}
.mkt-drawer__title small {
    font-size: 12px;
    color: var(--light-color);
}
.mkt-drawer__close {
    color: var(--muted, #8492a6);
    font-size: 18px;
    padding: 0;
}
.mkt-drawer__filters {
    padding: 12px 18px;
    border-bottom: 1px solid #e0e6f7;
}
.mkt-drawer__body {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 14px 18px;
}
.mkt-drawer__list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.mkt-drawer__footer {
    display: flex;
    flex-direction: column;
    padding: 10px 18px;
    background: #f2f4fa;
    border-top: 1px solid #e0e6f7;
    font-size: 12px;
    color: var(--muted, #8492a6);
}
.mkt-drawer__footer .text-warning {
    color: var(--warning, #ff8400) !important;
}
</style>
<style>
.v-modal.mkt-catalog-modal {
    background: rgb(243 244 251 / 65%) !important;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    opacity: 1 !important;
    transition: all 0.3s ease;
}
</style>
