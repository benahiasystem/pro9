<template>
    <div>
        <div class="page-header pe-0">
            <h2><a :href="direccionUrl">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="#2b2b2d"
                    stroke-width="1.75"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    style="margin-top: -5px;">
                    <path d="M21 15h-2.5c-.398 0 -.779 .158 -1.061 .439c-.281 .281 -.439 .663 -.439 1.061c0 .398 .158 .779 .439 1.061c.281 .281 .663 .439 1.061 .439h1c.398 0 .779 .158 1.061 .439c.281 .281 .439 .663 .439 1.061c0 .398 -.158 .779 -.439 1.061c-.281 .281 -.663 .439 -1.061 .439h-2.5" />
                    <path d="M19 21v1m0 -8v1" />
                    <path d="M13 21h-7c-.53 0 -1.039 -.211 -1.414 -.586c-.375 -.375 -.586 -.884 -.586 -1.414v-10c0 -.53 .211 -1.039 .586 -1.414c.375 -.375 .884 -.586 1.414 -.586h2m12 3.12v-1.12c0 -.53 -.211 -1.039 -.586 -1.414c-.375 -.375 -.884 -.586 -1.414 -.586h-2" />
                    <path d="M16 10v-6c0 -.53 -.211 -1.039 -.586 -1.414c-.375 -.375 -.884 -.586 -1.414 -.586h-4c-.53 0 -1.039 .211 -1.414 .586c-.375 .375 -.586 .884 -.586 1.414v6m8 0h-8m8 0h1m-9 0h-1" />
                    <path d="M8 14v.01" />
                    <path d="M8 17v.01" />
                    <path d="M12 13.99v.01" />
                    <path d="M12 17v.01" />
                </svg>
            </a></h2>
            <ol class="breadcrumbs">
                <li class="active"><span> Vendeya Escritorio </span></li>
            </ol>
        </div>
        <div class="card mb-0">
            <div class="card-body pt-2">
                <el-tabs v-model="tab" @tab-click="load">
                    <el-tab-pane label="Máquinas" name="machines">
                        <el-table v-loading="loadingMachines" :data="machines" style="width: 100%" size="small">
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
                            <el-table-column label="Acciones" width="110" align="center">
                                <template slot-scope="scope">
                                    <el-tooltip v-if="scope.row.status === 'active'" content="Revocar máquina" placement="top">
                                        <el-button
                                            size="mini"
                                            type="danger"
                                            plain
                                            circle
                                            icon="el-icon-remove-outline"
                                            @click="revoke(scope.row)"
                                        ></el-button>
                                    </el-tooltip>
                                    <el-tooltip v-if="scope.row.status !== 'active' && scope.row.has_group" content="Liberar series para otra máquina" placement="top">
                                        <el-button
                                            size="mini"
                                            type="warning"
                                            plain
                                            circle
                                            icon="el-icon-unlock"
                                            @click="releaseSeries(scope.row)"
                                        ></el-button>
                                    </el-tooltip>
                                </template>
                            </el-table-column>
                        </el-table>
                    </el-tab-pane>

                    <el-tab-pane label="Bandeja de eventos" name="events">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="border rounded p-2 text-center" :class="stats.pending ? 'border-warning' : ''">
                                    <h4 class="mb-0" :class="stats.pending ? 'text-warning' : ''">{{ stats.pending }}</h4>
                                    <small class="text-muted">Pendientes de continuar (tarea programada o Reintentar)</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-2 text-center" :class="stats.error ? 'border-danger' : ''">
                                    <h4 class="mb-0" :class="stats.error ? 'text-danger' : ''">{{ stats.error }}</h4>
                                    <small class="text-muted">Con error (requieren revisión)</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-2 text-center">
                                    <h4 class="mb-0 text-success">{{ stats.accepted }}</h4>
                                    <small class="text-muted">Aceptados</small>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-3">
                                <el-select v-model="filters.machine_id" placeholder="Máquina" clearable size="small" @change="loadEvents(1)">
                                    <el-option v-for="m in machines" :key="m.id" :value="m.id" :label="m.name"></el-option>
                                </el-select>
                            </div>
                            <div class="col-md-3">
                                <el-select v-model="filters.type" placeholder="Tipo" clearable size="small" @change="loadEvents(1)">
                                    <el-option value="sale" label="Venta"></el-option>
                                    <el-option value="sale_note" label="Nota de venta"></el-option>
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

                        <el-table v-loading="loadingEvents" :data="events" style="width: 100%" size="small">
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
            stats: { pending: 0, error: 0, accepted: 0, discarded: 0 },
            working: null,
            loadingMachines: false,
            loadingEvents: false,
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
            this.loadingMachines = true
            this.$http.post('/sync/machines/records').then((res) => {
                this.machines = res.data.data
            }).finally(() => {
                this.loadingMachines = false
            })
        },
        loadEvents(page = 1) {
            this.loadingEvents = true
            this.$http
                .post(`/sync/events/records?page=${page}`, this.filters)
                .then((res) => {
                    this.events = res.data.data
                    this.meta = res.data.meta
                })
                .finally(() => {
                    this.loadingEvents = false
                })
            this.$http.post('/sync/events/stats').then((res) => {
                this.stats = res.data.data
            })
        },
        revoke(row) {
            this.$confirm(
                `Se revocará la máquina "${row.name}" y su acceso quedará deshabilitado. Recomendación: la máquina debe SINCRONIZAR sus comprobantes (cerrar caja y enviar) antes de esta acción. ¿Continuar?`,
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
                sale_note: 'Nota de venta',
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
