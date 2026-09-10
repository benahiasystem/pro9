<template>
    <div class="row">
        <div class="card col-md-8">
            <div class="card-header justify-content-center d-block">
                <h4>Generar backup</h4>
                <br>
                <form class="row pt-2" @submit.prevent="generate">
                    <div class="col-12 mb-2">
                        <span>Tipo</span> <br>
                        <el-radio v-model="formBackups.type" label="todos">Todos</el-radio>
                        <el-radio v-model="formBackups.type" label="individual">Individual</el-radio>
                    </div>
                    <div class="col-6 col-md-3 form-group" v-if="formBackups.type === 'individual'" :class="{'has-danger': errors.hostname_id}">
                        <el-select v-model="formBackups.hostname_id" clearable filterable placeholder="Selecciona un cliente">
                            <el-option v-for="cl in clients" :key="cl.hostname_id" :label="cl.name" :value="cl.hostname_id"></el-option>
                        </el-select>
                        <small class="form-control-feedback" v-if="errors.hostname_id" v-text="errors.hostname_id[0]"></small>
                    </div>
                    <div class="col-6 col-md-4 form-group">
                        <el-switch v-model="formBackups.includes_files" active-text="Incluir archivos"></el-switch>
                    </div>
                    <div class="col-6 col-md-3 form-group">
                        <el-button @click.prevent="generate()" :loading="loading_submit" :disabled="loading_submit">Iniciar Proceso</el-button>
                    </div>
                </form>
                <br>
                <p class="mb-0">Espacio disponible en disco: {{discUsed}}</p>
                <p class="mb-0">Espacio ocupado por archivos de facturación: {{storageSize}}</p>
            </div>
            <div class="card-body">
                <p class="mb-2">Para restaurar una base de datos debe descomprimir el .zip y ejecutar:</p>
                <code>gunzip &lt; [archivo].sql.gz | mysql -u [user] -p [database_name]</code>
                <br>
                <hr>
                <p class="mb-2">Para restaurar los archivos descargados debe copiar todas carpetas dentro de la carpeta del cliente.</p>
                <code>cp [path_del_zip]/pdf storage/app/tenancy/tenants/tenancy_[subdominio del cliente]</code> <br>
                <p>Repetir para todas las carpetas que estan dentro del .zip</p>
            </div>
        </div>
        <div class="card col-md-4 mt-0">
            <div class="card-header">
                Enviar por FTP último backup generado
            </div>
            <div class="card-body">

                <small class="text-muted">Por seguridad sus datos FTP no son guardados</small>
                <form v-if="newLastZip !== ''">
                    <div class="form-group" :class="{'has-danger': errors.host}">
                        <label class="control-label">Host/IP</label>
                        <el-input v-model="form.host"></el-input>
                    </div>
                    <div class="form-group" :class="{'has-danger': errors.port}">
                        <label class="control-label">Puerto</label>
                        <el-input v-model="form.port"></el-input>
                    </div>
                    <div class="form-group" :class="{'has-danger': errors.username}">
                        <label class="control-label">Usuario</label>
                        <el-input v-model="form.username"></el-input>
                    </div>
                    <div class="form-group" :class="{'has-danger': errors.password}">
                        <label class="control-label">Contraseña</label>
                        <el-input v-model="form.password"></el-input>
                    </div>
                    <div v-if="newLastZip !== ''" class="form-group">
                        <el-button @click.prevent="uploadFtp()" :loading="loading_upload">Enviar</el-button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card col-md-12">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>
                    Bandeja de descargas
                    <small class="text-muted ml-2" v-if="in_progress > 0">
                        {{in_progress}} en proceso
                    </small>
                </span>
                <el-button size="mini" @click.prevent="getTray()" :loading="loading_tray">Actualizar</el-button>
            </div>
            <div class="card-body">
                <el-table :data="tray" v-loading="loading_tray" style="width: 100%" empty-text="Todavía no se generó ningún backup">
                    <el-table-column prop="client_name" label="Cliente" min-width="180">
                        <template slot-scope="scope">
                            {{scope.row.client_name || scope.row.database}}
                            <br>
                            <small class="text-muted">{{scope.row.database}}</small>
                        </template>
                    </el-table-column>
                    <el-table-column prop="scope" label="Tipo" width="110"></el-table-column>
                    <el-table-column label="Estado" width="140">
                        <template slot-scope="scope">
                            <el-tooltip v-if="scope.row.status === 'FAILED'" :content="scope.row.error_message || 'Error desconocido'" placement="top">
                                <el-tag type="danger" size="mini">Fallido</el-tag>
                            </el-tooltip>
                            <el-tag v-else :type="statusType(scope.row.status)" size="mini">
                                {{statusLabel(scope.row.status)}}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="created_at" label="Solicitado" width="140"></el-table-column>
                    <el-table-column prop="date_end" label="Finalizado" width="140"></el-table-column>
                    <el-table-column label="Tamaño" width="110">
                        <template slot-scope="scope">
                            {{formatSize(scope.row.size)}}
                        </template>
                    </el-table-column>
                    <el-table-column label="Acciones" width="180" align="right">
                        <template slot-scope="scope">
                            <el-button
                                type="text"
                                v-if="scope.row.downloadable"
                                @click.prevent="downloadTray(scope.row)">Descargar</el-button>
                            <el-button
                                type="text"
                                class="text-danger"
                                v-if="scope.row.status !== 'IN_PROCESS' && scope.row.status !== 'PENDING'"
                                @click.prevent="deleteTray(scope.row)">Eliminar</el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
        </div>
    </div>
</template>
<script>

export default {
    props: ['storageSize','discUsed', 'lastZip', 'clients'],
    data() {
        return {
            newLastZipDate: '',
            resource: 'backup',
            errors: {},
            form: {},
            loading_submit: false,
            loading_upload: false,
            loading_tray: false,
            newLastZip: '',
            formBackups: {
                type: 'todos',
                hostname_id: null,
                includes_files: true,
            },
            tray: [],
            in_progress: 0,
            poll: null,
        }
    },
    created() {
        this.initForm();
    },
    mounted() {
        this.getTray();
    },
    beforeDestroy() {
        this.stopPolling();
    },
    methods: {
        onGenerateNnameLastFile() {
            if (this.newLastZip) {
                if (this.newLastZip.date) {
                    this.newLastZipDate = `creado el ${this.newLastZip.date}`;
                }
            }
        },
        initForm(){
            this.form = {
                host: null,
                port: null,
                username: null,
                password: null,
            }
            this.newLastZip = this.lastZip;
            this.onGenerateNnameLastFile();
        },
        /**
         * El backup ya no se genera dentro del request: se encola y el avance se
         * sigue desde la bandeja.
         */
        generate() {
            this.loading_submit = true
            this.errors = {}

            this.$http.post(`/${this.resource}/generate`, this.formBackups)
                .then(response => {
                    this.$message.success(response.data.message)
                    this.getTray()
                    this.startPolling()
                })
                .catch(error => {
                    const status = error.response.status
                    if (status === 422) {
                        this.errors = error.response.data
                    } else if (status === 409) {
                        this.$message.warning(error.response.data.message)
                    } else {
                        this.$message.error(error.response.data.message || 'Ocurrió un error inesperado')
                    }
                })
                .then(() => this.loading_submit = false)
        },
        getTray() {
            this.loading_tray = true

            return this.$http.get(`/${this.resource}/tray`)
                .then(response => {
                    this.tray = response.data.data
                    this.in_progress = response.data.in_progress

                    // Solo se consulta mientras haya algo corriendo.
                    if (this.in_progress > 0) {
                        this.startPolling()
                    } else {
                        this.stopPolling()
                    }
                })
                .catch(() => this.$message.error('No se pudo cargar la bandeja de descargas'))
                .then(() => this.loading_tray = false)
        },
        startPolling() {
            if (this.poll) return
            this.poll = setInterval(() => this.getTray(), 5000)
        },
        stopPolling() {
            if (!this.poll) return
            clearInterval(this.poll)
            this.poll = null
        },
        downloadTray(row) {
            window.open(`/${this.resource}/tray/${row.id}/download`, '_blank')
        },
        deleteTray(row) {
            this.$confirm(`¿Eliminar el backup de ${row.client_name || row.database}? Se borrará el archivo del servidor.`, 'Confirmar', {
                confirmButtonText: 'Eliminar',
                cancelButtonText: 'Cancelar',
                type: 'warning'
            }).then(() => {
                this.$http.delete(`/${this.resource}/tray/${row.id}`)
                    .then(response => {
                        this.$message.success(response.data.message)
                        this.getTray()
                    })
                    .catch(() => this.$message.error('No se pudo eliminar el registro'))
            }).catch(() => {})
        },
        statusLabel(status) {
            return {
                PENDING: 'En cola',
                IN_PROCESS: 'Procesando',
                FINISHED: 'Listo',
                FAILED: 'Fallido',
            }[status] || status
        },
        statusType(status) {
            return {
                PENDING: 'info',
                IN_PROCESS: 'warning',
                FINISHED: 'success',
                FAILED: 'danger',
            }[status] || 'info'
        },
        formatSize(bytes) {
            if (!bytes) return '-'
            const units = ['B', 'KB', 'MB', 'GB', 'TB']
            let i = 0
            let size = bytes
            while (size >= 1024 && i < units.length - 1) {
                size = size / 1024
                i++
            }
            return `${size.toFixed(1)} ${units[i]}`
        },
        uploadFtp() {
            this.loading_upload = true
            this.sendFtp()
        },
        sendFtp() {
            this.$http.post(`${this.resource}/upload`, this.form)
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message)
                        this.$eventHub.$emit('reloadData')
                        this.loading_upload = false
                        this.initForm()
                    } else {
                        this.$message.error(response.data.message)
                    }
                })
                .catch(error => {
                    if (error.response.status === 422) {
                        this.errors = error.response.data
                    } else if (error.response.status === 500) {
                        this.$message.error(error.response.data.message);
                    } else {
                        console.log(error.response)
                    }
                })
                .then(() => {
                    this.loading_upload = false
                })
        }
    }
}
</script>
