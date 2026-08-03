<template>
    <div>
        <div class="btn-filter-content mb-3 d-flex">
            <el-radio-group v-model="status" @change="load(1)">
                <el-radio-button label="open">Abiertas</el-radio-button>
                <el-radio-button label="dismissed">Descartadas</el-radio-button>
            </el-radio-group>
        </div>

        <div class="table-responsive" v-loading="loading">
            <table class="table">
                <thead>
                <tr>
                    <th>Denunciado</th>
                    <th>Motivo</th>
                    <th class="text-center">Acumuladas</th>
                    <th>IP</th>
                    <th>Fecha</th>
                    <th class="text-end">Acciones</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="row in records" :key="row.id">
                    <td>
                        <div v-if="row.item" class="d-flex align-items-center">
                            <img v-if="row.item.image_url" :src="row.item.image_url" class="mkt-thumb" alt="">
                            <span v-else class="mkt-thumb mkt-thumb--placeholder">{{ row.item.name.charAt(0) }}</span>
                            <div>
                                <strong>{{ row.item.name }}</strong>
                                <span v-if="row.item.status === 'blocked'" class="badge badge-pill badge-danger ms-1">
                                    Bloqueado
                                </span>
                                <br>
                                <small class="text-muted">
                                    {{ row.item.internal_code || 'sin código' }} ·
                                    {{ row.store ? row.store.name : '' }}
                                </small>
                            </div>
                        </div>
                        <div v-else-if="row.store">
                            <strong>{{ row.store.name }}</strong>
                            <span class="badge badge-pill badge-secondary ms-1">Tienda</span>
                        </div>
                    </td>
                    <td>{{ row.reason }}</td>
                    <td class="text-center">
                        <span class="badge badge-pill badge-danger">
                            {{ row.item ? row.item.reports_count : (row.store ? row.store.reports_count : 0) }}
                        </span>
                    </td>
                    <td>{{ row.ip }}</td>
                    <td>{{ row.created_at }}</td>
                    <td class="text-end">
                        <el-dropdown v-if="row.status === 'open'" trigger="click">
                            <el-button type="text" class="dropdown-trigger">
                                <i class="fas fa-ellipsis-v"></i>
                            </el-button>
                            <el-dropdown-menu slot="dropdown">
                                <el-dropdown-item v-if="row.item && row.item.status !== 'blocked'"
                                                  @click.native="blockItem(row)" class="text-danger option-delete">
                                                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-ban me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M5.7 5.7l12.6 12.6" /></svg>
                                                  Bloquear producto
                                </el-dropdown-item>
                                <el-dropdown-item v-if="row.item && row.item.status === 'blocked'"
                                                  @click.native="unblockItem(row)">
                                                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-circle-check me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M9 12l2 2l4 -4" /></svg>
                                                  Desbloquear producto
                                </el-dropdown-item>
                                <el-dropdown-item @click.native="disableStore(row)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-x me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M18 6l-12 12" /><path d="M6 6l12 12" /></svg>
                                    Deshabilitar tienda
                                </el-dropdown-item>

                                <el-dropdown-item divided></el-dropdown-item>

                                <el-dropdown-item @click.native="dismiss(row)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-check me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M5 12l5 5l10 -10" /></svg>
                                    Descartar
                                </el-dropdown-item>
                            </el-dropdown-menu>
                        </el-dropdown>
                        <span v-else class="text-muted">—</span>
                    </td>
                </tr>

                <tr v-if="!records.length && !loading">
                    <td colspan="6" class="text-center text-muted">No hay denuncias</td>
                </tr>
                </tbody>
            </table>
        </div>

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
