<template>
    <el-dialog :title="titleDialog" :visible="showDialog" @close="close" @open="create" class="dialog-import">
        <form autocomplete="off" @submit.prevent="submit">
            <div class="form-body">
                <div class="row">
                    <div class="col-12 form-group" :class="{'has-danger': errors.warehouse_id}">
                        <label for="warehouse">Almacén</label>
                        <el-select v-model="form.warehouse_id">
                            <el-option v-for="w in warehouses" :key="w.id" :label="w.description" :value="w.id"></el-option>
                        </el-select>
                        <small class="form-control-feedback" v-if="errors.warehouse_id" v-text="errors.warehouse_id[0]"></small>
                    </div>
                    <div class="col-12 form-group" :class="{'has-danger': errors.file}">
                        <!-- ########### INICIO CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS -->
                        <el-upload
                                ref="upload"
                                :headers="headers"
                                action="/items/import"
                                accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                :show-file-list="true"
                                :auto-upload="false"
                                :multiple="false"
                                :on-error="errorUpload"
                                :before-upload="onBeforeUpload"
                                :limit="1"
                                :data="form"
                                :on-success="successUpload">
                            <el-button slot="trigger" type="primary">Seleccione un archivo (xlsx)</el-button>
                        </el-upload>
                        <!-- ########### FIN CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS -->
                        <small class="form-control-feedback" v-if="errors.file" v-text="errors.file[0]"></small>
                    </div>
                    <div class="col-12 mt-4 mb-2">
                        <a class="text-dark mr-auto" href="/formats/items.xlsx" target="_new">
                            <span class="mr-2">Descargar formato de ejemplo para importar</span>
                            <i class="fa fa-download"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="form-actions text-right mt-5">
                <el-button class="second-buton" @click.prevent="close()">Cancelar</el-button>
                <el-button type="primary" native-type="submit" :loading="loading_submit">Procesar</el-button>
            </div>
        </form>
    </el-dialog>
</template>

<script>

    export default {
        props: ['showDialog'],
        data() {
            return {
                loading_submit: false,
                headers: headers_token,
                titleDialog: null,
                resource: 'items',
                errors: {},
                form: {},
                warehouses: []
            }
        },
        async created() {
            this.initForm();
            await this.onFetchTables();
        },
        methods: {
            onBeforeUpload(file) {},
            async onFetchTables() {
                this.loading_submit = true;
                await this.$http.get('/items/import/tables').then(response => {
                    this.warehouses = response.data.warehouses;
                }).finally(() => this.loading_submit = false);
            },
            initForm() {
                this.errors = {}
                this.form = {
                    warehouse_id: null
                }
            },
            create() {
                this.titleDialog = 'Importar Productos'
            },
            // ########### INICIO CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
            submit() {
                if (! this.form.warehouse_id) {
                    this.$message.warning('Seleccione un almacén para poder continuar');
                    return;
                }

                if (!this.$refs.upload.uploadFiles.length) {
                    this.$message.warning('Seleccione un archivo XLSX para poder continuar')
                    return
                }

                this.loading_submit = true
                this.$refs.upload.submit()
            },
            close() {
                this.$emit('update:showDialog', false)
                this.initForm()
            },
            async successUpload(response, file, fileList) {
                try {
                    if (response.success) {
                        this.$message.success(response.message)
                        this.$eventHub.$emit('reloadData')
                        this.$eventHub.$emit('reloadTables')
                        this.$refs.upload.clearFiles()
                        this.close()
                        return
                    }

                    this.$message({message: response.message, type: 'error', duration: 8000, showClose: true})

                    if (response.validation_failed && !response.validation_url) {
                        this.$message.error('No se pudo generar el Excel de errores. Intente procesar el archivo nuevamente.')
                        return
                    }

                    if (response.validation_failed) {
                        await this.downloadValidation(response.validation_url)
                        this.$message.success('Se descargó el Excel con los errores para corregirlo.')
                        this.$refs.upload.clearFiles()
                    }
                } catch (exception) {
                    this.$message.error('No se pudo descargar el Excel de errores. Intente procesar el archivo nuevamente.')
                } finally {
                    this.loading_submit = false
                }
            },
            async downloadValidation(url) {
                const response = await this.$http.get(url, {responseType: 'blob'})
                const blob = response.data instanceof Blob
                    ? response.data
                    : new Blob([response.data], {
                        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                    })

                if (!blob.size) {
                    throw new Error('El archivo de validación está vacío.')
                }

                const disposition = response.headers['content-disposition'] || ''
                const filenameMatch = disposition.match(/filename\*?=(?:UTF-8'')?["']?([^"';]+)["']?/i)
                const filename = filenameMatch
                    ? decodeURIComponent(filenameMatch[1])
                    : 'VALIDACION_ITEMS.xlsx'
                const objectUrl = window.URL.createObjectURL(blob)
                const link = document.createElement('a')
                link.href = objectUrl
                link.download = filename
                document.body.appendChild(link)
                link.click()
                document.body.removeChild(link)
                window.setTimeout(() => window.URL.revokeObjectURL(objectUrl), 1000)
            },
            errorUpload(error) {
                this.loading_submit = false
                let message = 'No se pudo importar el archivo. Verifique el formato e intente nuevamente.'

                if (error && error.message) {
                    try {
                        const body = JSON.parse(error.message)
                        if (body.message) {
                            message = body.message
                        }
                    } catch (e) {
                        message = error.message
                    }
                }

                this.$message({message, type: 'error', duration: 8000, showClose: true})
            }
            // ########### FIN CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
        }
    }
</script>
