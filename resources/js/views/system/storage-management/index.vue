<template>
    <div>
        <header class="page-header">
            <h2 class="text-white"><a href="/dashboard">
                <svg  xmlns="http://www.w3.org/2000/svg"  width="20"  height="20" viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-database" style="margin-top: -5px;"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 6m-8 0a8 3 0 1 0 16 0a8 3 0 1 0 -16 0" /><path d="M4 6v6a8 3 0 0 0 16 0v-6" /><path d="M4 12v6a8 3 0 0 0 16 0v-6" /></svg>
            </a></h2>
            <ol class="breadcrumbs">
                <li class="active"><span class="text-white">Manejo de almacenamiento</span></li>
            </ol>
        </header>

        <div class="card" v-loading="loading">
            <div class="card-header bg-teal">
                <h3 class="my-0">Uso del disco</h3>
            </div>
            <div class="card-body">

                <div class="sm-alert" v-if="!loading && !measured">
                    No se pudo leer el espacio del disco. Es posible que el servidor
                    restrinja el acceso a esa información.
                </div>

                <template v-if="measured">
                    <div class="sm-stats">
                        <div class="sm-stat">
                            <span class="sm-stat__label">Capacidad total</span>
                            <span class="sm-stat__value">{{ formatBytes(total_space) }}</span>
                        </div>
                        <div class="sm-stat">
                            <span class="sm-stat__label">Ocupado</span>
                            <span class="sm-stat__value">{{ formatBytes(used_space) }}</span>
                        </div>
                        <div class="sm-stat">
                            <span class="sm-stat__label">Disponible</span>
                            <span class="sm-stat__value">{{ formatBytes(free_space) }}</span>
                        </div>
                        <div class="sm-stat">
                            <span class="sm-stat__label">Usado por empresas</span>
                            <span class="sm-stat__value">{{ formatBytes(tenants_space) }}</span>
                        </div>
                    </div>

                    <!--
                        Medidor de llenado: un solo color a la vez segun el nivel,
                        siempre acompañado del porcentaje en texto para no depender del color
                    -->
                    <div class="sm-meter">
                        <div class="sm-meter__head">
                            <span class="sm-meter__state" :style="{ color: usageColor }">
                                {{ usageLabel }}
                            </span>
                            <span class="sm-meter__percent">{{ usedPercent }}% del disco ocupado</span>
                        </div>
                        <div class="sm-meter__track">
                            <div
                                class="sm-meter__fill"
                                :style="{ width: usedPercent + '%', backgroundColor: usageColor }"
                            ></div>
                        </div>
                    </div>
                </template>

            </div>
        </div>

        <div class="card" v-loading="loading">
            <div class="card-header bg-teal">
                <h3 class="my-0">Almacenamiento por empresa</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover sm-table">
                        <thead>
                            <tr>
                                <th class="sm-col-index">#</th>
                                <th>Empresa</th>
                                <th class="sm-col-domain">Dominio</th>
                                <th class="text-end sm-col-size">Tamaño</th>
                                <th class="sm-col-share">Proporción</th>
                                <th class="text-center sm-col-actions">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in storages" :key="row.uuid">
                                <td class="sm-table__muted">{{ index + 1 }}</td>
                                <td>
                                    <span class="sm-table__name">{{ row.description }}</span>
                                    <!-- el guion mantiene el alto de la fila cuando no hay número -->
                                    <span class="sm-table__sub">{{ row.number || '—' }}</span>
                                </td>
                                <td class="sm-table__muted">{{ row.fqdn || '—' }}</td>
                                <td class="text-end sm-table__size">{{ formatBytes(row.space) }}</td>
                                <td>
                                    <div class="sm-share" :title="`${formatBytes(row.space)} de ${formatBytes(tenants_space)}`">
                                        <div class="sm-bar">
                                            <div
                                                class="sm-bar__fill"
                                                :style="{ width: barWidth(row.space) + '%' }"
                                            ></div>
                                        </div>
                                        <span class="sm-share__percent">{{ sharePercent(row.space) }}%</span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <el-dropdown trigger="click" @command="handleCommand" placement="bottom-end">
                                        <el-button type="text" size="small" class="dropdown-trigger">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </el-button>
                                        <el-dropdown-menu slot="dropdown">
                                            <el-dropdown-item :command="{action: 'clean', row: row}">
                                                <svg  xmlns="http://www.w3.org/2000/svg"  width="16"  height="16"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-edit me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" /><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" /><path d="M16 5l3 3" /></svg>
                                                Limpiar
                                            </el-dropdown-item>
                                        </el-dropdown-menu>
                                    </el-dropdown>
                                </td>
                            </tr>
                            <tr v-if="!loading && storages.length === 0">
                                <td colspan="6" class="text-center sm-table__muted">
                                    No se encontraron empresas registradas
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card" v-loading="loading_configurations">
            <div class="card-header bg-teal d-flex justify-content-between align-items-center">
                <h3 class="my-0">Programación de limpieza</h3>
                <el-button type="primary" size="small" @click.prevent="clickCreateConfiguration()">
                    Nueva programación
                </el-button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover sm-table">
                        <thead>
                            <tr>
                                <th class="sm-col-index">#</th>
                                <th>Empresa</th>
                                <th class="sm-col-packages">Carpetas</th>
                                <th class="sm-col-when">Cuándo</th>
                                <th class="sm-col-time">Hora</th>
                                <th class="sm-col-state">Estado</th>
                                <th class="sm-col-last-run">Última corrida</th>
                                <th class="text-center sm-col-actions">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in configurations" :key="row.id">
                                <td class="sm-table__muted">{{ index + 1 }}</td>
                                <td><span class="sm-table__name">{{ row.description }}</span></td>
                                <td>
                                    <!-- las etiquetas necesitan su propio contenedor: sueltas en la
                                         celda se apilan sin aire y se salen hacia la columna vecina -->
                                    <div class="sm-tags">
                                        <el-tag v-for="(item, key) in row.package_labels"
                                                :key="key"
                                                size="mini"
                                                type="info">{{ item }}</el-tag>
                                    </div>
                                </td>
                                <td class="sm-table__wrap">{{ row.frequency_label }}</td>
                                <td class="sm-table__size">{{ row.time }}</td>
                                <td>
                                    <el-tag size="mini" :type="row.active ? 'success' : 'info'">
                                        {{ row.active ? 'Activa' : 'Inactiva' }}
                                    </el-tag>
                                </td>
                                <td class="sm-table__muted">{{ row.last_run_at || 'Nunca' }}</td>
                                <td class="text-center">
                                    <el-dropdown trigger="click" @command="handleConfigurationCommand" placement="bottom-end">
                                        <el-button type="text" size="small" class="dropdown-trigger">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </el-button>
                                        <el-dropdown-menu slot="dropdown">
                                            <el-dropdown-item :command="{action: 'edit', row: row}">Editar</el-dropdown-item>
                                            <!--
                                                sin divided: el margen que agrega queda fuera del li
                                                y deja una franja de 6px que no responde al clic
                                            -->
                                            <el-dropdown-item :command="{action: 'delete', row: row}">Eliminar</el-dropdown-item>
                                        </el-dropdown-menu>
                                    </el-dropdown>
                                </td>
                            </tr>
                            <tr v-if="!loading_configurations && configurations.length === 0">
                                <td colspan="8" class="text-center sm-table__muted">
                                    No hay programaciones registradas
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <clean-storage :showDialog.sync="showCleanStorageDialog" :record="cleanStorageRecord"></clean-storage>
        <cleanup-configuration :showDialog.sync="showConfigurationDialog" :record="configurationRecord"></cleanup-configuration>
    </div>
</template>

<script>
import CleanStorage from './partials/clean-storage.vue'
import CleanupConfiguration from './partials/cleanup-configuration.vue'

export default {
    components: { CleanStorage, CleanupConfiguration },
    data() {
        return {
            resource: 'storage-management',
            loading: true,
            loading_configurations: true,
            measured: false,
            showCleanStorageDialog: false,
            cleanStorageRecord: {},
            showConfigurationDialog: false,
            configurationRecord: {},
            configurations: [],
            total_space: 0,
            free_space: 0,
            used_space: 0,
            tenants_space: 0,
            storages: [],
        }
    },
    created() {
        this.$eventHub.$on('reloadData', () => {
            this.getRecords()
        })
        this.$eventHub.$on('reloadConfigurations', () => {
            this.getConfigurations()
        })
        this.getRecords()
        this.getConfigurations()
    },
    computed: {
        usedPercent() {

            if (!this.measured || !this.total_space) return 0

            return Math.round(this.used_space / this.total_space * 1000) / 10

        },
        // umbrales de llenado, el color nunca viaja solo: siempre hay etiqueta y porcentaje
        usageColor() {

            if (this.usedPercent >= 90) return '#d93636'
            if (this.usedPercent >= 75) return '#c47d10'

            return '#4a9e22'

        },
        usageLabel() {

            if (this.usedPercent >= 90) return 'Espacio crítico'
            if (this.usedPercent >= 75) return 'Espacio limitado'

            return 'Espacio suficiente'

        },
    },
    methods: {
        handleCommand(command) {
            switch (command.action) {
                case 'clean':
                    this.clickCleanStorage(command.row);
                    break;
            }
        },
        clickCleanStorage(row) {
            this.cleanStorageRecord = row
            this.showCleanStorageDialog = true
        },
        handleConfigurationCommand(command) {

            if (!command) return

            // sin este try el menu se cierra y no pasa nada mas: una excepcion
            // sincrona aca no deja rastro en pantalla
            try {

                switch (command.action) {
                    case 'edit':
                        this.clickEditConfiguration(command.row)
                        break
                    case 'delete':
                        this.clickDeleteConfiguration(command.row)
                        break
                }

            } catch (error) {

                console.error('Error en la acción del menú', command.action, error)
                this.$message.error(`No se pudo ejecutar la acción: ${error.message}`)

            }
        },
        clickCreateConfiguration() {
            this.configurationRecord = {}
            this.showConfigurationDialog = true
        },
        clickEditConfiguration(row) {
            this.configurationRecord = row
            this.showConfigurationDialog = true
        },
        clickDeleteConfiguration(row) {

            this.$confirm(`¿Eliminar la programación de ${row.description}?`, 'Confirmar', {
                confirmButtonText: 'Eliminar',
                cancelButtonText: 'Cancelar',
                type: 'warning',
            })
                // el rechazo del confirm no es un error, solo cancelacion:
                // se corta aca para que el catch de abajo solo vea fallas reales
                .catch(() => 'cancel')
                .then(result => {

                    if (result === 'cancel') return

                    return this.$http.delete(`/${this.resource}/configurations/${row.id}`)
                        .then(response => {
                            this.$message.success(response.data.message)
                            this.getConfigurations()
                        })

                })
                .catch(error => {

                    // el motivo real importa: sin el, un 403 o un 500 se ven igual
                    console.error('Error al eliminar la programación', error)

                    const status = error.response && error.response.status
                    const message = error.response && error.response.data && error.response.data.message

                    this.$message.error(
                        message || `No se pudo eliminar la programación${status ? ` (error ${status})` : ''}`
                    )

                })

        },
        getConfigurations() {

            this.loading_configurations = true

            this.$http.get(`/${this.resource}/configurations`)
                .then(response => {
                    this.configurations = response.data.records
                })
                .catch(() => {
                    this.$message.error('No se pudieron obtener las programaciones')
                })
                .then(() => {
                    this.loading_configurations = false
                })

        },
        getRecords() {

            this.loading = true

            this.$http.get(`/${this.resource}/records`)
                .then(response => {

                    const data = response.data

                    this.measured = data.measured
                    this.total_space = data.total_space || 0
                    this.free_space = data.free_space || 0
                    this.used_space = data.used_space || 0
                    this.tenants_space = data.tenants_space || 0
                    this.storages = data.storages || []

                })
                .catch(() => {
                    this.$message.error('No se pudo obtener la información de almacenamiento')
                })
                .then(() => {
                    this.loading = false
                })

        },
        /**
         * Cuanto del total ocupado por empresas representa una fila
         */
        sharePercent(space) {

            if (!this.tenants_space || !space) return 0

            const percent = space / this.tenants_space * 100

            // bajo 10% un decimal separa a los que si no quedarian todos en 0%
            return percent < 10 ? Math.round(percent * 10) / 10 : Math.round(percent)

        },
        barWidth(space) {

            if (!this.tenants_space || !space) return 0

            // la barra dice lo mismo que el porcentaje de al lado, con un minimo
            // visible para que una empresa pequeña no parezca vacia
            return Math.max(space / this.tenants_space * 100, 2)

        },
        /**
         * Bytes a la unidad legible mas cercana
         */
        formatBytes(bytes) {

            const value = Number(bytes) || 0

            if (value === 0) return '0 B'

            const units = ['B', 'KB', 'MB', 'GB', 'TB']
            const exponent = Math.min(Math.floor(Math.log(value) / Math.log(1024)), units.length - 1)
            const size = value / Math.pow(1024, exponent)

            return `${size.toFixed(exponent === 0 ? 0 : 2)} ${units[exponent]}`

        },
    },
}
</script>

<style scoped>
/* resumen */
.sm-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
    margin-bottom: 24px;
}
.sm-stat {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 14px 16px;
    border: 1px solid #e4e7ed;
    border-radius: 8px;
}
.sm-stat__label {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    opacity: 0.6;
}
.sm-stat__value {
    font-size: 22px;
    font-weight: 700;
    white-space: nowrap;
}

/* medidor de llenado del disco */
.sm-meter__head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 8px;
}
.sm-meter__state {
    font-size: 14px;
    font-weight: 600;
}
.sm-meter__percent {
    font-size: 13px;
    opacity: 0.7;
}
.sm-meter__track {
    height: 10px;
    border-radius: 5px;
    background: #eef0f4;
    overflow: hidden;
}
.sm-meter__fill {
    height: 100%;
    border-radius: 5px;
    transition: width 0.3s ease;
}

/* tabla */
.sm-table {
    /* sin esto los anchos declarados abajo son solo una sugerencia */
    table-layout: fixed;
}
.sm-table th,
.sm-table td {
    vertical-align: middle;
}
.sm-table th {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    white-space: nowrap;
}

/* anchos: lo variable es el nombre de la empresa, el resto es de tamaño conocido */
.sm-col-index { width: 48px; }
.sm-col-domain { width: 22%; }
.sm-col-size { width: 110px; }
.sm-col-share { width: 220px; }
.sm-col-actions { width: 80px; }

/* programación de limpieza */
.sm-col-packages { width: 34%; }
.sm-col-when { width: 150px; }
.sm-col-time { width: 80px; }
.sm-col-state { width: 90px; }
.sm-col-last-run { width: 130px; }

.sm-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}
.sm-tags .el-tag {
    /* el nombre completo importa mas que la altura de la fila */
    height: auto;
    line-height: 1.6;
    white-space: normal;
}
.sm-table__wrap {
    white-space: normal;
}

.sm-table__name {
    display: block;
    font-weight: 600;
    /* un nombre largo no debe empujar al resto de columnas */
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.sm-table__sub {
    display: block;
    font-size: 12px;
    opacity: 0.6;
}
.sm-table__muted {
    opacity: 0.7;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.sm-table__size {
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}
.sm-share {
    display: flex;
    align-items: center;
    gap: 10px;
}
.sm-share__percent {
    font-size: 12px;
    font-variant-numeric: tabular-nums;
    opacity: 0.7;
    white-space: nowrap;
    /* ancho fijo: sin el, cada fila corta la barra en un punto distinto */
    width: 44px;
    text-align: right;
}
.sm-bar {
    flex: 1;
    height: 8px;
    border-radius: 4px;
    background: #eef0f4;
    overflow: hidden;
}
.sm-bar__fill {
    height: 100%;
    border-radius: 4px;
    /* tono unico: la identidad la da el nombre de la fila, no el color */
    background: #2f7ed8;
    transition: width 0.3s ease;
}

/* aviso */
.sm-alert {
    padding: 14px 16px;
    border: 1px solid #f0d8a8;
    border-radius: 8px;
    background: #fdf6ec;
    color: #8a5d08;
    font-size: 13px;
}
</style>
