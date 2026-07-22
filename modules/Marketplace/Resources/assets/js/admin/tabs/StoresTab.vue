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

        <el-table :data="records" v-loading="loading" empty-text="No hay tiendas todavía">
            <el-table-column type="expand">
                <template slot-scope="props">
                    <div v-loading="catalogs[props.row.id] === 'loading'" class="p-2">
                        <div v-if="catalog(props.row.id).length" class="mkt-catalog">
                            <div v-for="item in catalog(props.row.id)" :key="item.id"
                                 class="mkt-catalog__item" :class="{ 'is-blocked': item.status === 'blocked' }">
                                <img v-if="item.image_url" :src="item.image_url" :alt="item.name" loading="lazy">
                                <span v-else class="mkt-catalog__placeholder">{{ item.name.charAt(0) }}</span>
                                <div class="mkt-catalog__body">
                                    <span class="mkt-catalog__name" :title="item.name">{{ item.name }}</span>
                                    <small class="text-muted">{{ item.internal_code || '—' }}</small>
                                    <span class="mkt-catalog__tags">
                                        <!-- Las denuncias del ítem son lo que decide cuál bloquear. -->
                                        <span v-if="item.reports_count" class="badge badge-pill badge-danger"
                                              :title="item.reports_count + ' denuncia(s)'">
                                            <i class="fas fa-flag"></i> {{ item.reports_count }}
                                        </span>
                                        <!-- Y las recomendaciones son el contrapeso: un producto
                                             denunciado que además acumula recomendaciones suele ser
                                             una denuncia interesada, no un problema real. -->
                                        <span v-if="item.recommendations_count" class="badge badge-pill badge-success"
                                              :title="item.recommendations_count + ' recomendación(es)'">
                                            <i class="fas fa-heart"></i> {{ item.recommendations_count }}
                                        </span>
                                        <span v-if="item.status !== 'active'" class="badge badge-pill"
                                              :class="item.status === 'blocked' ? 'badge-danger' : 'badge-secondary'"
                                              :title="item.blocked_reason || ''">
                                            {{ item.status === 'blocked' ? 'Bloqueado' : 'Inactivo' }}
                                        </span>
                                    </span>
                                </div>
                                <el-button v-if="item.status === 'blocked'" type="text"
                                           @click="unblock(item, props.row.id)">Desbloquear</el-button>
                                <el-button v-else type="text" class="text-danger"
                                           @click="askBlock(item, props.row.id)">Bloquear</el-button>
                            </div>
                        </div>
                        <p v-else-if="catalogs[props.row.id] !== 'loading'" class="text-muted mb-0">
                            Esta tienda todavía no ha sincronizado productos.
                        </p>
                    </div>
                </template>
            </el-table-column>

            <el-table-column label="Tienda" min-width="230">
                <template slot-scope="scope">
                    <div class="d-flex align-items-center">
                        <img v-if="scope.row.logo_url" :src="scope.row.logo_url" class="mkt-logo" :alt="scope.row.name">
                        <span v-else class="mkt-logo mkt-logo--placeholder">{{ scope.row.name.charAt(0) }}</span>
                        <div>
                            <strong>{{ scope.row.name }}</strong><br>
                            <small class="text-muted">{{ scope.row.tax_id || 'Sin RUC' }}</small>
                        </div>
                    </div>
                </template>
            </el-table-column>

            <el-table-column label="WhatsApp" width="130">
                <template slot-scope="scope">
                    <a :href="'https://wa.me/' + scope.row.whatsapp" target="_blank" rel="noopener nofollow">
                        {{ scope.row.whatsapp }}
                    </a>
                </template>
            </el-table-column>

            <el-table-column label="Productos" width="100" align="center" prop="items_count"/>

            <el-table-column label="Denuncias" width="100" align="center">
                <template slot-scope="scope">
                    <span v-if="scope.row.reports_count" class="badge badge-pill badge-danger">
                        {{ scope.row.reports_count }}
                    </span>
                    <span v-else class="text-muted">—</span>
                </template>
            </el-table-column>

            <!-- Exacto, no abreviado: el público ve «2.5k», el admin necesita el número. -->
            <el-table-column label="Recomendaciones" width="140" align="center">
                <template slot-scope="scope">
                    <span v-if="scope.row.recommendations_count" class="badge badge-pill badge-success">
                        {{ scope.row.recommendations_count }}
                    </span>
                    <span v-else class="text-muted">—</span>
                </template>
            </el-table-column>

            <el-table-column label="Estado" width="170">
                <template slot-scope="scope">
                    <span class="badge badge-pill" :class="statusClass(scope.row.status)">
                        {{ statusLabel(scope.row.status) }}
                    </span>
                    <el-tooltip v-if="scope.row.status_reason" :content="scope.row.status_reason" placement="top">
                        <i class="el-icon-info text-muted ms-1"></i>
                    </el-tooltip>

                    <!-- «Ocultar mi tienda»: sigue aprobada, pero se despublicó
                         ella misma. El admin no tiene que hacer nada, solo saberlo. -->
                    <div v-if="scope.row.hidden">
                        <span class="badge badge-pill badge-secondary mt-1"
                              title="La tienda se ocultó a sí misma desde su app. Volverá al sincronizar.">
                            <i class="fas fa-eye-slash"></i> Oculta por la tienda
                        </span>
                    </div>

                    <!-- Acción requerida (alertas de seguridad sin revisar):
                         mismo trato visual que una tienda pendiente de aprobar.
                         Se atiende desde el menú de acciones de la fila. -->
                    <div v-for="alert in scope.row.alerts" :key="alert.id">
                        <el-tooltip :content="alert.message" placement="top">
                            <span class="badge badge-pill badge-warning mt-1 mkt-alert-badge">
                                <i class="fas fa-exclamation-triangle"></i> {{ alertLabel(alert.type) }}
                            </span>
                        </el-tooltip>
                    </div>
                </template>
            </el-table-column>

            <el-table-column label="Último sync" width="130">
                <template slot-scope="scope">
                    <small>{{ scope.row.last_synced_at || '—' }}</small>
                </template>
            </el-table-column>

            <el-table-column label="Acciones" width="120" align="right">
                <template slot-scope="scope">
                    <el-dropdown trigger="click">
                        <el-button type="text" class="dropdown-trigger">
                            <i class="fas fa-ellipsis-v"></i>
                        </el-button>
                        <el-dropdown-menu slot="dropdown">
                            <el-dropdown-item v-if="scope.row.status !== 'approved'"
                                              @click.native="approve(scope.row)">
                                <i class="el-icon-check"></i>
                                {{ scope.row.status === 'disabled' ? 'Habilitar' : 'Aprobar' }}
                            </el-dropdown-item>
                            <el-dropdown-item v-if="scope.row.status === 'pending'"
                                              @click.native="askReason(scope.row, 'reject')">
                                <i class="el-icon-close"></i> Rechazar
                            </el-dropdown-item>
                            <el-dropdown-item v-if="scope.row.status === 'approved'"
                                              @click.native="askReason(scope.row, 'disable')">
                                <i class="el-icon-remove-outline"></i> Deshabilitar
                            </el-dropdown-item>
                            <!-- Atender la alerta = revisarla y marcarla. El
                                 mensaje completo está en el tooltip del badge. -->
                            <el-dropdown-item v-for="alert in scope.row.alerts" :key="alert.id"
                                              @click.native="markAlertRead(alert)">
                                <i class="el-icon-check text-warning"></i>
                                Revisado: {{ alertLabel(alert.type) }}
                            </el-dropdown-item>

                            <!-- Ancla nativa a ancho completo del ítem (no
                                 window.open): la descarga la maneja el
                                 navegador y ningún bloqueador de popups la
                                 corta. Sin target: un CSV con
                                 Content-Disposition no navega, descarga. -->
                            <el-dropdown-item :disabled="!scope.row.contacts_count">
                                <a v-if="scope.row.contacts_count"
                                   :href="'/marketplace/admin/contact-requests?export=1&store_id=' + scope.row.id"
                                   class="mkt-drop-link">
                                    <i class="el-icon-download"></i>
                                    Contactos ({{ scope.row.contacts_count }}) — CSV
                                </a>
                                <template v-else>
                                    <i class="el-icon-download"></i> Contactos (0) — CSV
                                </template>
                            </el-dropdown-item>

                            <!-- Para cuando la tienda cambió o perdió su
                                 dispositivo: el próximo sync emite credencial
                                 nueva (trust-on-first-use). -->
                            <el-dropdown-item v-if="scope.row.has_secret"
                                              @click.native="askResetSecret(scope.row)">
                                <i class="el-icon-key"></i> Restablecer credencial
                            </el-dropdown-item>

                            <!-- Ancla nativa que cubre TODO el ítem (margen
                                 negativo contra el padding del li): el
                                 problema original era que solo el texto del
                                 <a> era clickeable y el resto del ítem cerraba
                                 el menú sin navegar. -->
                            <el-dropdown-item v-if="scope.row.public_url">
                                <a :href="scope.row.public_url" target="_blank" rel="noopener"
                                   class="mkt-drop-link">
                                    <i class="el-icon-view"></i> Ver página pública
                                </a>
                            </el-dropdown-item>
                        </el-dropdown-menu>
                    </el-dropdown>
                </template>
            </el-table-column>
        </el-table>

        <el-pagination v-if="pagination.total > pagination.per_page" class="mt-3" background
                       layout="prev, pager, next"
                       :current-page="pagination.current_page" :page-size="pagination.per_page"
                       :total="pagination.total" @current-change="load"/>

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
export default {
    data() {
        return {
            loading: false,
            isFiltersVisible: false,
            records: [],
            catalogs: {},
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
                this.catalogs = {}
            }).finally(() => { this.loading = false })
        },

        catalog(storeId) {
            const value = this.catalogs[storeId]
            if (value === undefined) {
                this.$set(this.catalogs, storeId, 'loading')
                this.$http.get(`/marketplace/admin/stores/${storeId}/items`)
                    .then(({ data }) => this.$set(this.catalogs, storeId, data.data))
                    .catch(() => this.$set(this.catalogs, storeId, []))
                return []
            }
            return value === 'loading' ? [] : value
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
                    this.reloadCatalog(storeId)
                    this.load(this.pagination.current_page)
                }).catch(this.onError)
            }).catch(() => {})
        },

        unblock(item, storeId) {
            this.$http.post(`/marketplace/admin/items/${item.id}/unblock`).then(({ data }) => {
                this.$message.success(data.message)
                this.reloadCatalog(storeId)
                this.load(this.pagination.current_page)
            }).catch(this.onError)
        },

        reloadCatalog(storeId) {
            this.$http.get(`/marketplace/admin/stores/${storeId}/items`)
                .then(({ data }) => this.$set(this.catalogs, storeId, data.data))
        },

        onError(error) {
            const message = error.response && error.response.data && error.response.data.message
            this.$message.error(typeof message === 'object' ? Object.values(message)[0][0] : (message || 'Ocurrió un error.'))
        },
    },
}
</script>

<style scoped>
/* Ancla que rellena el el-dropdown-item completo: contrarresta el padding
   0 20px del li para que cualquier punto del ítem navegue, no solo el texto.
   Funciona aunque el menú se monte en <body> (popper): el atributo scoped
   viaja con el nodo. */
.mkt-drop-link {
    display: block;
    margin: 0 -20px;
    padding: 0 20px;
    color: inherit;
    text-decoration: none;
}

.mkt-logo {
    width: 34px; height: 34px; border-radius: 6px; object-fit: cover;
    margin-right: 10px; flex: none;
}
.mkt-logo--placeholder {
    display: inline-flex; align-items: center; justify-content: center;
    background: #020F3C; color: #fff; font-weight: 700;
}
.mkt-catalog {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 10px;
}
.mkt-catalog__item {
    display: flex; align-items: center; gap: 10px; padding: 8px;
    border: 1px solid #ebeef5; border-radius: 6px; background: #fff;
}
.mkt-catalog__item.is-blocked { background: #fef0f0; border-color: #fbc4c4; }
.mkt-catalog__item img,
.mkt-catalog__placeholder {
    width: 40px; height: 40px; border-radius: 4px; object-fit: cover; flex: none;
}
.mkt-catalog__placeholder {
    display: inline-flex; align-items: center; justify-content: center;
    background: #f2f6fc; color: #909399; font-weight: 700;
}
.mkt-catalog__body { flex: 1; min-width: 0; display: flex; flex-direction: column; align-items: flex-start; }
.mkt-catalog__name { font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
.mkt-catalog__tags { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
</style>
