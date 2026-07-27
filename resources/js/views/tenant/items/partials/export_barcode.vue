<template>
    <el-dialog :visible="showDialog"
               :close-on-click-modal="false"
               class="dialog-import"
               title="Codigo de barras"
               @close="close">
        <form autocomplete="off"
              @submit.prevent="submit">
            <div class="form-body">
                <div class="row">
                    <div class="col-12">
                        <el-select v-model="type">
                            <el-option
                                v-for="option in types"
                                :key="option.id"
                                :label="option.description"
                                :value="option.id"
                            ></el-option>
                        </el-select>
                    </div>

                    <div class="col-6">
                        <div class="form-group">
                            <label class="control-label">
                                Desde el Número
                                <el-tooltip class="item"
                                            content="Este número especifica el campo ID del item."
                                            effect="dark"
                                            placement="top-start">
                                    <i class="fa fa-info-circle"></i>
                                </el-tooltip>
                            </label>
                            <el-input-number
                                v-model="rangeMin"
                                :max="max_item"
                                :min="1"
                                :precision="0"
                                :step="1"
                                @change="syncFromInputs"></el-input-number>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label class="control-label">
                                Hasta el Número
                                <el-tooltip class="item"
                                            content="Este número especifica el campo ID del item."
                                            effect="dark"
                                            placement="top-start">
                                    <i class="fa fa-info-circle"></i>
                                </el-tooltip>
                            </label>
                            <el-input-number
                                v-model="rangeMax"
                                :max="max_item"
                                :min="1"
                                :precision="0"
                                :step="1"
                                @change="syncFromInputs"></el-input-number>
                        </div>
                    </div>

                    <div class="col-12">
                        <template>
                            <label class="control-label">
                                Seleccione rango de impresión
                                <el-tooltip class="item"
                                            content="Seleccione el rango de id para los items. Seleccionar una cantidad muy alta puede causar lentitud en el servidor"
                                            effect="dark"
                                            placement="top-start">
                                    <i class="fa fa-info-circle"></i>
                                </el-tooltip>
                            </label>
                            <el-slider
                                v-model="sliderRange"
                                :max="max_item"
                                :min="1"
                                range>
                            </el-slider>
                        </template>
                    </div>
                </div>
                <div class="form-actions text-end mt-4">
                    <el-button class="second-buton me-2" @click.prevent="close()">Cancelar</el-button>
                    <el-button :loading="loading_submit"
                               native-type="submit"
                               type="primary">Procesar
                    </el-button>
                </div>
            </div>
        </form>
    </el-dialog>
</template>

<script>
import queryString from 'query-string'
import Swal from 'sweetalert2'

export default {
    props: [
        'showDialog',
        'pharmacy',
    ],
    data() {
        return {
            loading_submit: false,
            headers: headers_token,
            resource: 'items',
            errors: {},
            rangeMin: 1,
            rangeMax: 1,
            max_item: 1,
            type: 0,
            fromPharmacy: false,
            types: [
                {'id': 0, 'description': 'Normal'},
                {'id': 1, 'description': 'Impresión 5cm x 2.5cm'},
            ],
        }
    },
    computed: {
        sliderRange: {
            get() {
                return [this.rangeMin, this.rangeMax]
            },
            set(value) {
                this.rangeMin = value[0]
                this.rangeMax = value[1]
            }
        }
    },
    created() {
        if (this.pharmacy !== undefined && this.pharmacy === true) {
            this.fromPharmacy = true;
        }
        this.initForm()
    },
    methods: {
        initForm() {
            this.lastItem()
            this.errors = {}
        },
        close() {
            this.$emit('update:showDialog', false)
            this.initForm()
        },
        syncFromInputs() {
            if (this.rangeMin > this.rangeMax) {
                this.rangeMax = this.rangeMin
            }
            if (this.rangeMax < this.rangeMin) {
                this.rangeMin = this.rangeMax
            }
        },
        showNoResultsAlert(message) {
            return Swal.fire({
                icon: 'warning',
                title: 'Sin resultados',
                text: message,
                confirmButtonText: 'Entendido',
                customClass: {
                    container: 'barcode-export-swal-container',
                },
                didOpen: () => {
                    const container = document.querySelector('.barcode-export-swal-container')
                    if (container) {
                        container.style.zIndex = '100000'
                    }
                },
            })
        },
        async submit() {
            this.loading_submit = true
            this.syncFromInputs()

            try {
                const params = {
                    isPharmacy: this.fromPharmacy,
                    0: this.rangeMin,
                    1: this.rangeMax,
                    full: this.type == 1,
                }

                const { data } = await this.$http.get(`/${this.resource}/export/barcode/count`, { params })

                if (!data.count) {
                    await this.showNoResultsAlert(data.message)
                    return
                }

                const query = queryString.stringify({
                    isPharmacy: this.fromPharmacy,
                    0: this.rangeMin,
                    1: this.rangeMax,
                })

                const endpoint = this.type == 1 ? 'barcode_full' : 'barcode'
                window.open(`/${this.resource}/export/${endpoint}/?${query}`, '_blank')

                this.$emit('update:showDialog', false)
                this.initForm()
            } catch (error) {
                this.$message.error('No se pudo validar el rango de exportación.')
            } finally {
                this.loading_submit = false
            }
        },
        lastItem() {
            this.$http.get(`${this.resource}/export/barcode/last`, {
                params: {
                    isPharmacy: this.fromPharmacy,
                }
            })
                .then(response => {
                    let total = parseInt(response.data.data, 10)

                    if (isNaN(total) || total < 1) {
                        total = 1
                    }

                    this.max_item = total
                    this.rangeMin = 1
                    this.rangeMax = total
                })
        }
    }
}
</script>

<style>
.barcode-export-swal-container {
    z-index: 100000 !important;
}
</style>
