<template>
    <el-dialog :title="titleDialog" :visible="showDialog" @close="close" @open="create" class="dialog-import">
        <form autocomplete="off" @submit.prevent="submit">
            <div class="form-body">
                <div class="row">
                    <div class="col-md-12">
                        <a :href="formatUrl" target="_blank">Descargar formato de ejemplo</a>
                    </div>
                    <div class="col-md-12 mt-3">
                        <small class="text-muted d-block">
                            Crea de forma masiva los <strong>atributos y sus valores</strong> (catálogo).
                            No vincula variaciones con productos.
                        </small>
                        <small class="text-muted d-block mt-1">
                            Columnas: <code>Nombre</code>, <code>Tipo</code> (lista o color),
                            <code>Valor</code> (uno por fila o varios separados por coma),
                        </small>
                        <small class="text-muted d-block">
                            <code>Color</code> (#RRGGBB), <code>Orden</code>, <code>Activo</code> (1 o 0).
                        </small>
                    </div>
                    <div class="col-md-12 mt-4">
                        <div class="form-group text-center" :class="{'has-danger': errors.file}">
                            <el-upload
                                ref="upload"
                                :headers="headers"
                                action="/product-variables/import"
                                :show-file-list="true"
                                :auto-upload="false"
                                :multiple="false"
                                accept=".xlsx,.xls"
                                :on-error="errorUpload"
                                :limit="1"
                                :on-success="successUpload">
                                <el-button slot="trigger" type="primary">Seleccione un archivo (xlsx)</el-button>
                            </el-upload>
                            <small class="form-control-feedback" v-if="errors.file" v-text="errors.file[0]"></small>
                        </div>
                    </div>
                    <div v-if="importErrors.length > 0" class="col-md-12 mt-2">
                        <el-alert title="Filas con error" type="warning" :closable="false" show-icon>
                            <ul class="mb-0 ps-3">
                                <li v-for="(message, index) in importErrors" :key="'import-error-' + index">
                                    {{ message }}
                                </li>
                            </ul>
                        </el-alert>
                    </div>
                </div>
            </div>
            <div class="form-actions text-end mt-4">
                <el-button class="second-buton me-2" @click.prevent="close()">Cancelar</el-button>
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
            formatUrl: '/formats/product_variables.xlsx',
            errors: {},
            importErrors: [],
            form: {},
        }
    },
    created() {
        this.initForm()
    },
    methods: {
        initForm() {
            this.errors = {}
            this.importErrors = []
            this.form = {
                file: null,
            }
        },
        create() {
            this.titleDialog = 'Importar atributos'
        },
        async submit() {
            this.loading_submit = true
            this.importErrors = []
            await this.$refs.upload.submit()
            this.loading_submit = false
        },
        close() {
            this.$emit('update:showDialog', false)
            this.initForm()
            if (this.$refs.upload) {
                this.$refs.upload.clearFiles()
            }
        },
        successUpload(response) {
            if (response.success) {
                this.$message.success(response.message)
                this.importErrors = (response.data && response.data.errors) || []
                this.$emit('imported')
                if (this.importErrors.length === 0) {
                    if (this.$refs.upload) {
                        this.$refs.upload.clearFiles()
                    }
                    this.close()
                }
            } else {
                this.$message({ message: response.message, type: 'error' })
                this.importErrors = this.splitErrors(response.message)
            }
        },
        errorUpload() {
            this.$message.error('Error al importar el archivo')
        },
        splitErrors(message) {
            if (!message) {
                return []
            }
            return String(message).split(' | ').filter(Boolean)
        },
    }
}
</script>
