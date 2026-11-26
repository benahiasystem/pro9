<template>
    <div class="row">
        <div class="card col-md-8">
            <div class="card-header justify-content-center d-block">
                <h4>Generar backup</h4>
                <br>
                <form class="row pt-2" @submit.prevent="onSubmitBackups">
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
                    <div class="col-6 col-md-3 form-group">
                        <el-button @click.prevent="start()" :loading="loading_submit" :disabled="loading_submit">Iniciar Proceso</el-button>
                    </div>
                </form>
                <br><br>
                <p class="mb-0">Espacio disponible en disco: {{discUsed}}</p>
                <p class="mb-0">Espacio ocupado por archivos de facturación: {{storageSize}}</p>
            </div>
            <div class="card-body">
                <p v-if="newLastZip !== ''">Ultimo Backup generado: <strong>{{newLastZip.name}} {{newLastZipDate}}</strong></p>
                <el-button @click.prevent="clickDownload()" >Descargar</el-button>
                <hr>
                <!-- ########## INICIO CAMBIO POSTGRESQL 17 -->
                <p class="mb-2">El respaldo PostgreSQL incluye un manifiesto con verificaciones de integridad. Restáurelo primero en una base independiente.</p>
                <code>php artisan sab:restore-tenant [empresa] [respaldo.zip]</code>
                <p class="mb-2">La activación requiere mantenimiento y los trabajos detenidos. Consulte el manual de recuperación antes de utilizar --activate.</p>
                <!-- ######### FIN CAMBIO POSTGRESQL 17 -->
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
    </div>
</template>
<script>
import $ from 'jquery'

export default {
    props: ['storageSize','discUsed', 'lastZip', 'clients'],
    data() {
        return {
            newLastZipDate: '',
            headers: null,
            resource: 'backup',
            errors: {},
            form: {},
            loading_submit: false,
            loading_upload: false,
            db: {
                error: '',
                content: '',
                status: '',
            },
            files: {
                error: '',
                content: '',
                status: '',
            },
            newLastZip: '',
            formBackups: {
                type: 'todos',
            },
            errors: {},
        }
    },
    created() {
        this.initForm();
    },
    methods: {
        onGenerateNnameLastFile() {
            if (this.newLastZip) {
                if (this.newLastZip.date) {
                    this.newLastZipDate = `creado el ${this.newLastZip.date}`;
                }
            }
        },
        clickDownload() {
            window.open(`/${this.resource}/download/${this.newLastZip.name}`, '_blank');
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
        async start() {
            this.initContent()
            this.loading_submit = true
            this.backupDb()
        },
        initContent() {
            this.db.error = ''
            this.db.content = ''
            this.db.status = false
            this.files.error = ''
            this.files.content = ''
            this.files.status = false
        },
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        async backupDb() {
            // Complete file backups already include their matching MySQL snapshot.
            return this.backupFiles()
        },
        async backupFiles() {
            try {
                const { data } = await this.$http.post(`/${this.resource}/files`, this.formBackups)
                this.files.content = 'Operación: ' + data.operation_id
                this.$message.info('Respaldo en proceso: ' + data.operation_id)
                for (let attempt = 0; attempt < 180; attempt++) {
                    await this.sleep(2000)
                    const { data: operation } = await this.$http.get(`/storage-management/operations/${data.operation_id}`)
                    if (operation.status === 'failed') throw new Error(operation.diagnostic)
                    if (operation.status === 'completed') {
                        this.db.status = 'success'; this.files.status = 'success'
                        this.mostRecent(); this.$message.success('Respaldo verificado y guardado'); return
                    }
                }
                this.$message.info('El respaldo continúa en segundo plano: ' + data.operation_id)
            } catch (error) {
                this.files.error = error.response?.data?.message || error.message
                this.files.status = 'false'
                this.$message.error(this.files.error)
            } finally { this.loading_submit = false }
        },
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        mostRecent(){
            this.$http.get(`/${this.resource}/last-backup`)
                .then(response => {
                    if (response.data !== '') {
                        this.newLastZip = response.data
                        this.loading_submit = false
                        this.onGenerateNnameLastFile();
                    }
                }).catch(error => {
                    if (error.response.status !== 200) {
                        this.files.error = error.response.data.message
                    } else {
                        console.log(error)
                    }
                })
        },
        uploadFtp() {
            this.loading_upload = true
            this.sendFtp()
        },
        sleep(timeout){
            return new Promise(resolve => setTimeout(resolve, timeout)); 
        },
        sendFtp() {
            this.$http.post(`${this.resource}/upload`, this.form)
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message)
                        this.$eventHub.$emit('reloadData')
                        this.loading_upload = false
                        // this.close()
                        this.initForm()
                    } else {
                        this.$message.error(response.data.message)
                    }
                })
                .catch(error => {
                    if (error.response.status === 422) {
                        this.errors = error.response.data
                    }else if(error.response.status === 500){
                        this.$message.error(error.response.data.message);
                    }
                        else {
                        console.log(error.response)
                    }
                })
                .then(()=>{
                    this.loading_upload = false
                })


        }
    }
}
</script>
