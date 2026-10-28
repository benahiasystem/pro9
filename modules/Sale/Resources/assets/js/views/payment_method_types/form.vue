<template>
    <el-dialog :title="titleDialog" :visible="showDialog" @close="close" @open="create">
        <form autocomplete="off" @submit.prevent="submit">
            <div class="form-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group" :class="{'has-danger': errors.id}">
                        <label class="control-label">Código</label>
                        <el-input v-model="form.id" :readonly="recordId !== null" :maxlength="2"></el-input>
                        <small class="form-control-feedback" v-if="errors.id" v-text="errors.id[0]"></small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group" :class="{'has-danger': errors.description}">
                            <label class="control-label">Descripción</label>
                            <el-input v-model="form.description"></el-input>
                            <small class="form-control-feedback" v-if="errors.description" v-text="errors.description[0]"></small>
                        </div>
                    </div>
                    <!-- ######## INICIO CLASIFICACION DE COBROS Y CAJA ######## -->
                    <div class="col-md-4">
                        <div class="form-group">
                        <label class="control-label">Naturaleza del medio</label>
                        <el-select v-model="form.collection_kind" @change="changeCollectionKind">
                            <el-option value="cash" label="Efectivo físico"/><el-option value="account" label="Bancario / electrónico"/>
                            <el-option value="credit" label="Crédito"/><el-option v-if="recordId !== null" value="unclassified" label="Sin clasificar"/>
                        </el-select>
                        <small class="text-danger" v-if="errors.collection_kind">{{ errors.collection_kind[0] }}</small>
                        </div>
                    </div>
                    <!-- ######## INICIO MONEDA E IMPUESTO DE MEDIOS ######## -->
                    <div class="col-md-4">
                        <div class="form-group">
                        <label class="control-label">Moneda</label>
                        <el-select v-model="form.currency_type_id" placeholder="Seleccione una moneda">
                            <el-option v-for="currency in currencyTypes" :key="currency.id" :value="currency.id" :label="currency.description + ' (' + currency.symbol + ')'" />
                        </el-select>
                        <small class="text-danger" v-if="errors.currency_type_id">{{ errors.currency_type_id[0] }}</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                        <label class="control-label">Impuesto</label>
                        <div class="tax-controls">
                        <button v-if="!showTax" type="button" class="btn btn-sm btn-info" aria-label="Agregar impuesto" @click="showTax = true">+</button>
                        <template v-else>
                            <el-select v-model="form.iva_rate_type_id" placeholder="Seleccione un impuesto">
                                <el-option v-for="tax in taxes" :key="tax.id" :value="tax.id" :label="tax.id + ' - ' + formatPercentage(tax.percentage) + '%'" />
                            </el-select>
                            <button type="button" class="btn btn-sm btn-danger" aria-label="Quitar impuesto" @click="removeTax">×</button>
                        </template>
                        </div>
                        <small class="text-danger" v-if="errors.iva_rate_type_id">{{ errors.iva_rate_type_id[0] }}</small>
                        </div>
                    </div>
                    <!-- ######## FIN MONEDA E IMPUESTO DE MEDIOS ######## -->
                    <!-- ######## FIN CLASIFICACION DE COBROS Y CAJA ######## -->
                    <div class="col-md-3" v-show="form.is_credit">
                        <label class="control-label">Número de dias</label>
                        <el-input v-model="form.number_days" :maxlength="3"></el-input>
                    </div>
                </div>
            </div>
            <div class="form-actions text-end mt-4">
                <el-button class="second-buton me-2" @click.prevent="close()">Cancelar</el-button>
                <el-button type="primary" native-type="submit" :loading="loading_submit">Guardar</el-button>
            </div>
        </form>
    </el-dialog>
</template>

<script>

    export default {
        props: ['showDialog', 'recordId'],
        data() {
            return {
                loading_submit: false,
                titleDialog: null,
                resource: 'payment-method-types',
                errors: {},
                form: {},
                currencyTypes: [],
                taxes: [],
                showTax: false,
            }
        },
        created() {
            this.initForm()
        },
        methods: {
            formatPercentage(value) {
                const [whole, fraction = ''] = String(value).split('.')
                return whole + '.' + fraction.replace(/0+$/, '').padEnd(2, '0')
            },
            removeTax() { this.showTax = false; this.form.iva_rate_type_id = null },
            changeCollectionKind() {
                this.form.is_credit = this.form.collection_kind === 'credit' ? 1 : 0
                if (this.form.collection_kind === 'unclassified') this.form.is_active = false
            },
            initForm() {
                this.errors = {}
                this.showTax = false
                this.form = {
                    id: null,
                    description: null,
                    collection_kind: 'account',
                    currency_type_id: 'VES',
                    iva_rate_type_id: null,
                    is_active: true,
                    is_credit: 0,
                    number_days: 0,
                }
            },
            create() {
                this.titleDialog = (this.recordId)? 'Editar método de pago':'Nuevo método de pago'
                this.$http.get(`/${this.resource}/tables`).then(response => {
                    this.currencyTypes = response.data.currency_types
                    this.taxes = response.data.taxes
                }).catch(() => this.$message.error('No se pudieron cargar las monedas y los impuestos'))
                if (this.recordId) {
                    this.$http.get(`/${this.resource}/record/${this.recordId}`)
                        .then(response => {
                            this.showTax = !!response.data.iva_rate_type_id
                            this.form = {...response.data, is_active: !!response.data.is_active}
                        })
                }
            },
            submit() {
                this.loading_submit = true
                // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
                this.errors = {}
                const request = this.recordId !== null
                    ? this.$http.put(`/${this.resource}/${encodeURIComponent(this.recordId)}`, this.form)
                    : this.$http.post(`/${this.resource}`, this.form)
                request
                    .then(response => {
                        if (response.data.success) {
                            this.$message.success(response.data.message)
                            this.$eventHub.$emit('reloadData')
                            this.close()
                        } else {
                            this.$message.error(response.data.message)
                        }
                    })
                    .catch(error => {
                        if (error.response && error.response.status === 422) {
                            const data = error.response.data
                            this.errors = data.errors || (typeof data.message === 'object' ? data.message : data)
                        } else {
                            this.$message.error(error.response?.data?.message || 'No se pudo guardar el método de pago')
                        }
                    })
                // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
                    .then(() => {
                        this.loading_submit = false
                    })
            },
            close() {
                this.$emit('update:showDialog', false)
                this.initForm()
            },
        }
    }
</script>

<style scoped>
.form-group .el-select {
    width: 100%;
}

.tax-controls {
    display: flex;
    align-items: center;
    gap: 8px;
}

.tax-controls .el-select {
    flex: 1;
    min-width: 0;
}

.tax-controls .btn {
    flex-shrink: 0;
}
</style>
