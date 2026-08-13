<template>
    <el-dialog width="35%" :title="title" :visible="showDialog" @close="close" @open="create" :close-on-click-modal="false">
        <div class="form-body" v-loading="loading_submit">
            <div class="row">

                <div class="col-md-12">
                    <div role="alert" class="el-alert el-alert--warning is-light">
                        <i class="el-alert__icon el-icon-warning is-big"></i>
                        <div class="el-alert__content">
                            <span class="el-alert__title is-bold fs-title">
                                ¿Está seguro de limpiar el almacenamiento de {{ record.description }}?
                            </span>
                            <p class="el-alert__description text-justify mt-3 fs-description">
                                <strong>Este proceso elimina los archivos generados que ya no son necesarios.</strong>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-12 mt-4" v-loading="loading_packages">

                    <label class="control-label font-weight-bold">Seleccione qué carpetas limpiar</label>

                    <el-checkbox-group v-model="form.packages" class="cs-packages">
                        <div class="cs-package" v-for="item in packages" :key="item.package">
                            <div class="cs-package__info">
                                <el-checkbox :label="item.package" :disabled="item.files === 0">
                                    {{ item.label }}
                                </el-checkbox>
                                <!-- el nombre de la carpeta no basta: hay que decir que se pierde -->
                                <span class="cs-package__description" v-if="item.description">
                                    {{ item.description }}
                                </span>
                            </div>
                            <span class="cs-package__meta">
                                {{ item.files }} archivo(s) · {{ formatBytes(item.space) }}
                            </span>
                        </div>
                    </el-checkbox-group>

                    <div class="cs-detail mt-3">
                        <span class="cs-detail__label">Espacio a liberar</span>
                        <span class="cs-detail__value">{{ formatBytes(selectedSpace) }}</span>
                    </div>

                </div>

            </div>

            <div class="form-actions text-right mt-3">
                <el-button class="second-buton" @click.prevent="close()">Cancelar</el-button>
                <el-button :loading="loading_submit"
                           :disabled="form.packages.length === 0"
                           type="danger"
                           @click.prevent="clickClean()">Limpiar
                </el-button>
            </div>

        </div>
    </el-dialog>
</template>

<script>
    export default {
        props: ['showDialog', 'record'],

        data() {
            return {
                title: null,
                resource: 'storage-management',
                loading_submit: false,
                loading_packages: false,
                packages: [],
                form: { packages: [] },
            }
        },
        computed: {
            // solo se suma lo seleccionado, el admin ve el impacto antes de confirmar
            selectedSpace() {
                return this.packages
                    .filter(item => this.form.packages.includes(item.package))
                    .reduce((total, item) => total + item.space, 0)
            },
        },
        methods: {
            create() {
                this.title = 'Limpiar almacenamiento'
                this.initForm()
                this.getPackages()
            },
            initForm() {
                this.packages = []
                this.form = { packages: [] }
            },
            async getPackages() {

                this.loading_packages = true

                await this.$http.get(`/${this.resource}/packages/${this.record.uuid}`)
                    .then(response => {
                        this.packages = response.data.packages
                    })
                    .catch(() => {
                        this.$message.error('No se pudo obtener el detalle de las carpetas')
                    })
                    .then(() => {
                        this.loading_packages = false
                    })

            },
            async clickClean() {

                this.loading_submit = true

                await this.$http.post(`/${this.resource}/clean/${this.record.uuid}`, { packages: this.form.packages })
                    .then(response => {

                        if (response.data.success) {
                            this.$message.success(response.data.message)
                            this.$eventHub.$emit('reloadData')
                            this.close()
                        } else {
                            this.$message.error(response.data.message)
                        }

                    })
                    .catch(() => {
                        this.$message.error('No se pudo limpiar el almacenamiento')
                    })
                    .then(() => {
                        this.loading_submit = false
                    })

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
            close() {
                this.initForm()
                this.$emit('update:showDialog', false)
            },
        }
    }
</script>

<style scoped>
    .fs-title {
        font-size: 15px;
    }

    .fs-description {
        font-size: 13px;
    }

    .cs-detail {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 16px;
        border: 1px solid #e4e7ed;
        border-radius: 8px;
    }

    .cs-detail__label {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        opacity: 0.6;
    }

    .cs-detail__value {
        font-size: 18px;
        font-weight: 700;
        white-space: nowrap;
    }

    .cs-packages {
        display: block;
        margin-top: 8px;
    }

    .cs-package {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 0;
        border-bottom: 1px solid #ebeef5;
    }

    .cs-package__info {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
    }

    .cs-package__description {
        /* alineada con el texto del checkbox, no con su casilla */
        padding-left: 24px;
        font-size: 12px;
        line-height: 1.4;
        opacity: 0.65;
        white-space: normal;
    }

    .cs-package__meta {
        font-size: 12px;
        opacity: 0.6;
        white-space: nowrap;
        padding-top: 4px;
    }
</style>
