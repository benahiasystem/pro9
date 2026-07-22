<template>
    <div>
        <div class="btn-filter-content mb-3 d-flex">
            <el-radio-group v-model="status" @change="load(1)">
                <el-radio-button label="open">Abiertas</el-radio-button>
                <el-radio-button label="dismissed">Descartadas</el-radio-button>
            </el-radio-group>
        </div>

        <el-table :data="records" v-loading="loading" empty-text="No hay denuncias">
            <el-table-column label="Denunciado" min-width="260">
                <template slot-scope="scope">
                    <div v-if="scope.row.item" class="d-flex align-items-center">
                        <img v-if="scope.row.item.image_url" :src="scope.row.item.image_url" class="mkt-thumb" alt="">
                        <span v-else class="mkt-thumb mkt-thumb--placeholder">{{ scope.row.item.name.charAt(0) }}</span>
                        <div>
                            <strong>{{ scope.row.item.name }}</strong>
                            <span v-if="scope.row.item.status === 'blocked'" class="badge badge-pill badge-danger ms-1">
                                Bloqueado
                            </span>
                            <br>
                            <small class="text-muted">
                                {{ scope.row.item.internal_code || 'sin código' }} ·
                                {{ scope.row.store ? scope.row.store.name : '' }}
                            </small>
                        </div>
                    </div>
                    <div v-else-if="scope.row.store">
                        <strong>{{ scope.row.store.name }}</strong>
                        <span class="badge badge-pill badge-secondary ms-1">Tienda</span>
                    </div>
                </template>
            </el-table-column>

            <el-table-column label="Motivo" prop="reason" width="180"/>

            <el-table-column label="Acumuladas" width="110" align="center">
                <template slot-scope="scope">
                    <span class="badge badge-pill badge-danger">
                        {{ scope.row.item ? scope.row.item.reports_count : (scope.row.store ? scope.row.store.reports_count : 0) }}
                    </span>
                </template>
            </el-table-column>

            <el-table-column label="IP" prop="ip" width="130"/>
            <el-table-column label="Fecha" prop="created_at" width="130"/>

            <el-table-column label="Acciones" width="120" align="right">
                <template slot-scope="scope">
                    <el-dropdown v-if="scope.row.status === 'open'" trigger="click">
                        <el-button type="text" class="dropdown-trigger">
                            <i class="fas fa-ellipsis-v"></i>
                        </el-button>
                        <el-dropdown-menu slot="dropdown">
                            <el-dropdown-item v-if="scope.row.item && scope.row.item.status !== 'blocked'"
                                              @click.native="blockItem(scope.row)">
                                <i class="el-icon-remove-outline"></i> Bloquear producto
                            </el-dropdown-item>
                            <el-dropdown-item v-if="scope.row.item && scope.row.item.status === 'blocked'"
                                              @click.native="unblockItem(scope.row)">
                                <i class="el-icon-circle-check"></i> Desbloquear producto
                            </el-dropdown-item>
                            <el-dropdown-item @click.native="disableStore(scope.row)">
                                <i class="el-icon-close"></i> Deshabilitar tienda
                            </el-dropdown-item>
                            <el-dropdown-item divided @click.native="dismiss(scope.row)">
                                <i class="el-icon-check"></i> Descartar
                            </el-dropdown-item>
                        </el-dropdown-menu>
                    </el-dropdown>
                    <span v-else class="text-muted">—</span>
                </template>
            </el-table-column>
        </el-table>

        <el-pagination v-if="pagination.total > pagination.per_page" class="mt-3" background
                       layout="prev, pager, next"
                       :current-page="pagination.current_page" :page-size="pagination.per_page"
                       :total="pagination.total" @current-change="load"/>
    </div>
</template>

<script>
export default {
    data() {
        return {
            loading: false,
            status: 'open',
            records: [],
            pagination: { current_page: 1, per_page: 20, total: 0 },
        }
    },

    created() {
        this.load(1)
    },

    methods: {
        load(page = 1) {
            this.loading = true
            this.$http.get('/marketplace/admin/reports', { params: { page, status: this.status } })
                .then(({ data }) => {
                    this.records = data.data
                    this.pagination = { current_page: data.current_page, per_page: data.per_page, total: data.total }
                })
                .finally(() => { this.loading = false })
        },

        blockItem(report) {
            this.$prompt('Motivo del bloqueo', `Bloquear «${report.item.name}»`, {
                confirmButtonText: 'Bloquear',
                cancelButtonText: 'Cancelar',
                inputValue: report.reason,
                inputValidator: (v) => (v && v.trim() ? true : 'El motivo es obligatorio'),
            }).then(({ value }) => {
                this.$http.post(`/marketplace/admin/items/${report.item.id}/block`, { reason: value })
                    .then(({ data }) => { this.$message.success(data.message); this.load(this.pagination.current_page) })
                    .catch(this.onError)
            }).catch(() => {})
        },

        unblockItem(report) {
            this.$http.post(`/marketplace/admin/items/${report.item.id}/unblock`)
                .then(({ data }) => { this.$message.success(data.message); this.load(this.pagination.current_page) })
                .catch(this.onError)
        },

        disableStore(report) {
            this.$prompt('Motivo', `Deshabilitar «${report.store.name}»`, {
                confirmButtonText: 'Deshabilitar',
                cancelButtonText: 'Cancelar',
                inputValue: report.reason,
                inputValidator: (v) => (v && v.trim() ? true : 'El motivo es obligatorio'),
            }).then(({ value }) => {
                this.$http.post(`/marketplace/admin/stores/${report.store.id}/disable`, { reason: value })
                    .then(({ data }) => {
                        this.$message.success(data.message)
                        this.load(this.pagination.current_page)
                        this.$emit('changed')
                    })
                    .catch(this.onError)
            }).catch(() => {})
        },

        dismiss(report) {
            this.$http.post(`/marketplace/admin/reports/${report.id}/dismiss`)
                .then(({ data }) => { this.$message.success(data.message); this.load(this.pagination.current_page) })
                .catch(this.onError)
        },

        onError(error) {
            const message = error.response && error.response.data && error.response.data.message
            this.$message.error(typeof message === 'object' ? Object.values(message)[0][0] : (message || 'Ocurrió un error.'))
        },
    },
}
</script>

<style scoped>
.mkt-thumb { width: 36px; height: 36px; border-radius: 4px; object-fit: cover; margin-right: 10px; flex: none; }
.mkt-thumb--placeholder {
    display: inline-flex; align-items: center; justify-content: center;
    background: #f2f6fc; color: #909399; font-weight: 700;
}
</style>
