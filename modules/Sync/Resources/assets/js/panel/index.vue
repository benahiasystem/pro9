<template>
    <div class="card mb-0">
        <div class="card-header bg-info py-2">
            <h4 class="my-0">Conexión Offline (VendeYa)</h4>
        </div>
        <div class="card-body pt-2">
            <el-tabs v-model="tab" @tab-click="load">
                <el-tab-pane label="Máquinas" name="machines">
                    <el-table :data="machines" style="width: 100%" size="small">
                        <el-table-column prop="name" label="Máquina" width="160"></el-table-column>
                        <el-table-column label="Series">
                            <template slot-scope="scope">
                                <div v-for="s in scope.row.series" :key="s">{{ s }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column label="Eventos" width="200">
                            <template slot-scope="scope">
                                <el-tag size="mini" type="success">{{ scope.row.accepted }} ok</el-tag>
                                <el-tag size="mini" type="warning">{{ scope.row.pending }} pend.</el-tag>
                                <el-tag size="mini" type="danger">{{ scope.row.error }} error</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="created_at" label="Enrolada" width="130"></el-table-column>
                        <el-table-column label="Estado" width="100">
                            <template slot-scope="scope">
                                <el-tag size="mini" :type="scope.row.status === 'active' ? 'success' : 'info'">
                                    {{ scope.row.status === 'active' ? 'Activa' : 'Revocada' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="Acciones" width="220">
                            <template slot-scope="scope">
                                <el-button
                                    v-if="scope.row.status === 'active'"
                                    size="mini"
                                    type="danger"
                                    plain
                                    @click="revoke(scope.row)"
                                >Revocar</el-button>
                                <el-button
                                    v-if="scope.row.status !== 'active' && scope.row.series.length"
                                    size="mini"
                                    type="warning"
                                    plain
                                    @click="releaseSeries(scope.row)"
                                >Liberar series</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <el-tab-pane label="Bandeja de eventos" name="events">
                    <div class="row mb-2">
                        <div class="col-md-3">
                            <el-select v-model="filters.machine_id" placeholder="Máquina" clearable size="small" @change="loadEvents(1)">
                                <el-option v-for="m in machines" :key="m.id" :value="m.id" :label="m.name"></el-option>
                            </el-select>
                        </div>
                        <div class="col-md-3">
                            <el-select v-model="filters.type" placeholder="Tipo" clearable size="small" @change="loadEvents(1)">
                                <el-option value="sale" label="Venta"></el-option>
                                <el-option value="void" label="Anulación"></el-option>
                                <el-option value="cash_open" label="Apertura de caja"></el-option>
                                <el-option value="cash_close" label="Cierre de caja"></el-option>
                            </el-select>
                        </div>
                        <div class="col-md-3">
                            <el-select v-model="filters.status" placeholder="Estado" clearable size="small" @change="loadEvents(1)">
                                <el-option value="error" label="Con error"></el-option>
                                <el-option value="pending" label="Pendiente"></el-option>
                                <el-option value="accepted" label="Aceptado"></el-option>
                                <el-option value="discarded" label="Descartado"></el-option>
                            </el-select>
                        </div>
                    </div>

                    <el-table :data="events" style="width: 100%" size="small">
                        <el-table-column prop="machine" label="Máquina" width="120"></el-table-column>
                        <el-table-column prop="seq" label="#" width="60"></el-table-column>
                        <el-table-column label="Evento" width="150">
                            <template slot-scope="scope">
                                {{ typeLabel(scope.row.type) }}
                                <div v-if="scope.row.detail && scope.row.detail !== scope.row.type">
                                    <b>{{ scope.row.detail }}</b>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column prop="occurred_at" label="Fecha" width="140"></el-table-column>
                        <el-table-column label="Estado" width="100">
                            <template slot-scope="scope">
                                <el-tag size="mini" :type="statusTag(scope.row.status)">{{ statusLabel(scope.row.status) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="message" label="Detalle"></el-table-column>
                        <el-table-column label="Acciones" width="190">
                            <template slot-scope="scope">
                                <el-button
                                    v-if="scope.row.can_retry"
                                    size="mini"
                                    type="primary"
                                    plain
                                    :loading="working === scope.row.id"
                                    @click="retry(scope.row)"
                                >Reintentar</el-button>
                                <el-button
                                    v-if="scope.row.can_discard"
                                    size="mini"
                                    plain
                                    @click="discard(scope.row)"
                                >Descartar</el-button>
                            </template>
                        </el-table-column>
                    </el-table>

                    <div class="mt-2 text-center">
                        <el-pagination
                            layout="prev, pager, next"
                            :total="meta.total"
                            :page-size="meta.per_page"
                            :current-page="meta.current_page"
                            @current-change="loadEvents"
                        ></el-pagination>
                    </div>
                </el-tab-pane>
            </el-tabs>
        </div>
    </div>
</template>

<script>
export default {
    data() {
        return {
            tab: 'machines',
            machines: [],
            events: [],
            meta: { total: 0, per_page: 20, current_page: 1 },
            filters: { machine_id: null, type: null, status: null },
            working: null,
        }
    },
    created() {
        this.load()
    },
    methods: {
        load() {
            this.loadMachines()
            if (this.tab === 'events') this.loadEvents(this.meta.current_page)
        },
        loadMachines() {
            this.$http.post('/sync/machines/records').then((res) => {
                this.machines = res.data.data
            })
        },
        loadEvents(page = 1) {
            this.$http
                .post(`/sync/events/records?page=${page}`, this.filters)
                .then((res) => {
                    this.events = res.data.data
                    this.meta = res.data.meta
                })
        },
        revoke(row) {
            this.$confirm(
                `Se revocará la máquina "${row.name}": su token dejará de autorizar y, si no quedan máquinas activas, el establecimiento vuelve a emitir online. ¿Continuar?`,
                'Revocar máquina',
                { type: 'warning', confirmButtonText: 'Revocar', cancelButtonText: 'Cancelar' }
            ).then(() => {
                this.$http.post(`/sync/machines/${row.id}/revoke`).then((res) => {
                    this.$message[res.data.success ? 'success' : 'error'](res.data.message)
                    this.loadMachines()
                })
            }).catch(() => {})
        },
        releaseSeries(row) {
            this.$confirm(
                `Las series de "${row.name}" quedarán libres para asignarse a otra máquina. ¿Continuar?`,
                'Liberar series',
                { type: 'warning', confirmButtonText: 'Liberar', cancelButtonText: 'Cancelar' }
            ).then(() => {
                this.$http.post(`/sync/machines/${row.id}/release-series`).then((res) => {
                    this.$message[res.data.success ? 'success' : 'error'](res.data.message)
                    this.loadMachines()
                })
            }).catch(() => {})
        },
        retry(row) {
            this.working = row.id
            this.$http.post(`/sync/events/${row.id}/retry`).then((res) => {
                this.$message[res.data.success ? 'success' : 'warning'](res.data.message)
            }).finally(() => {
                this.working = null
                this.loadEvents(this.meta.current_page)
                this.loadMachines()
            })
        },
        discard(row) {
            this.$confirm(
                'El evento quedará registrado como descartado y saldrá de la bandeja. ¿Continuar?',
                'Descartar evento',
                { type: 'warning', confirmButtonText: 'Descartar', cancelButtonText: 'Cancelar' }
            ).then(() => {
                this.$http.post(`/sync/events/${row.id}/discard`).then((res) => {
                    this.$message[res.data.success ? 'success' : 'error'](res.data.message)
                    this.loadEvents(this.meta.current_page)
                    this.loadMachines()
                })
            }).catch(() => {})
        },
        typeLabel(type) {
            return {
                sale: 'Venta',
                void: 'Anulación',
                cash_open: 'Apertura de caja',
                cash_close: 'Cierre de caja',
            }[type] || type
        },
        statusLabel(status) {
            return {
                accepted: 'Aceptado',
                pending: 'Pendiente',
                error: 'Con error',
                discarded: 'Descartado',
            }[status] || status
        },
        statusTag(status) {
            return { accepted: 'success', pending: 'warning', error: 'danger', discarded: 'info' }[status] || ''
        },
    },
}
</script>
