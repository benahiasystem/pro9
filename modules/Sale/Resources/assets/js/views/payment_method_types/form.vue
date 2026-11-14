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
                        <el-select v-model="form.currency_type_id" @change="changeCurrency" placeholder="Seleccione una moneda">
                            <el-option v-for="currency in currencyTypes" :key="currency.id" :value="currency.id" :label="currency.description + ' (' + currency.symbol + ')'" />
                        </el-select>
                        <small class="text-danger" v-if="errors.currency_type_id">{{ errors.currency_type_id[0] }}</small>
                        </div>
                    </div>
                    <template v-if="form.collection_kind === 'account'">
                        <div class="col-md-4 form-group">
                            <label class="control-label">Banco</label>
                            <el-select v-model="form.bank_id" @change="form.bank_account_id = null" placeholder="Seleccione un banco">
                                <el-option v-for="bank in banks" :key="bank.id" :value="bank.id" :label="bank.description" />
                            </el-select>
                            <small class="text-danger" v-if="errors.bank_id">{{ errors.bank_id[0] }}</small>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="control-label">Número de cuenta</label>
                            <el-select v-model="form.bank_account_id" placeholder="Seleccione una cuenta">
                                <el-option v-for="account in compatibleAccounts" :key="account.id" :value="account.id" :label="account.number + ' - ' + account.description" />
                            </el-select>
                            <small class="text-danger" v-if="errors.bank_account_id">{{ errors.bank_account_id[0] }}</small>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="control-label">% Comisión</label>
                            <el-input v-model="form.commission_percentage" placeholder="Opcional" />
                            <small class="text-danger" v-if="errors.commission_percentage">{{ errors.commission_percentage[0] }}</small>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="control-label">Comisión mínima por transacción ({{ form.currency_type_id }})</label>
                            <el-input v-model="form.minimum_commission" placeholder="Opcional" />
                            <small class="text-danger" v-if="errors.minimum_commission">{{ errors.minimum_commission[0] }}</small>
                        </div>
                    </template>
                    <div v-if="fiscalEmissionMode === 'digital'" class="col-md-4">
                        <div class="form-group">
                        <label class="control-label">Medio fiscal</label>
                        <el-select v-model="form.hka_code" placeholder="Seleccione un medio fiscal">
                            <el-option v-for="option in compatibleHkaMethods" :key="option.code" :value="option.code" :label="option.code + ' - ' + option.name" />
                        </el-select>
                        <small class="text-danger" v-if="errors.hka_code">{{ errors.hka_code[0] }}</small>
                        <small class="text-danger" v-if="incompatibleHka">El medio fiscal no corresponde a la naturaleza o moneda. Seleccione uno compatible.</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                        <label class="control-label">Impuesto asociado — informativo</label>
                        <div class="tax-controls">
                        <button v-if="!showTax" type="button" class="btn btn-sm btn-info" aria-label="Agregar impuesto" @click="showTax = true">+</button>
                        <template v-else>
                            <el-select v-model="form.iva_rate_type_id" placeholder="Seleccione un impuesto">
                                <el-option v-for="tax in taxes" :key="tax.id" :value="tax.id" :label="tax.id + ' - ' + formatPercentage(tax.percentage) + '%'" />
                            </el-select>
                            <button type="button" class="btn btn-sm btn-danger" aria-label="Quitar impuesto" @click="removeTax">×</button>
                        </template>
                        </div>
                        <small class="text-muted d-block">Seleccionar IGTF aquí no activa su cobro. El porcentaje del catálogo es informativo; se usa la tasa fiscal de la empresa y los datos reales del pago.</small>
                        <small class="text-danger" v-if="errors.iva_rate_type_id">{{ errors.iva_rate_type_id[0] }}</small>
                        </div>
                    </div>
                    <!-- ######## INICIO DISPONIBILIDAD PARA VUELTOS ######## -->
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="control-label d-block">Disponible para vueltos</label>
                            <el-switch v-model="form.available_for_change" :disabled="!['cash', 'account'].includes(form.collection_kind)" />
                            <small class="text-danger d-block" v-if="errors.available_for_change">{{ errors.available_for_change[0] }}</small>
                        </div>
                    </div>
                    <!-- ######## FIN DISPONIBILIDAD PARA VUELTOS ######## -->
                    <!-- ######## FIN MONEDA E IMPUESTO DE MEDIOS ######## -->
                    <!-- ######## FIN CLASIFICACION DE COBROS Y CAJA ######## -->
                    <div class="col-md-3" v-show="form.collection_kind === 'credit'">
                        <label class="control-label">Número de dias</label>
                        <el-input v-model="form.number_days" :maxlength="3"></el-input>
                    </div>
                </div>
            </div>
            <div class="form-actions text-end mt-4">
                <el-button class="second-buton me-2" @click.prevent="close()">Cancelar</el-button>
                <el-button type="primary" native-type="submit" :disabled="!formReady" :loading="loading_submit">Guardar</el-button>
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
                banks: [],
                bankAccounts: [],
                taxes: [],
                hkaPaymentMethods: [],
                fiscalEmissionMode: null,
                formReady: false,
                loadGeneration: 0,
                showTax: false,
            }
        },
        created() {
            this.initForm()
        },
        computed: {
            compatibleAccounts() {
                return this.bankAccounts.filter(account => String(account.bank_id) === String(this.form.bank_id) && account.currency_type_id === this.form.currency_type_id)
            },
            compatibleHkaMethods() {
                return this.hkaPaymentMethods.filter(option => option.kinds.includes(this.form.collection_kind)
                    && (!option.currencies.length || option.currencies.includes(this.form.currency_type_id)))
            },
            incompatibleHka() {
                return this.hkaPaymentMethods.length > 0 && !!this.form.hka_code
                    && !this.compatibleHkaMethods.some(option => option.code === this.form.hka_code)
            },
        },
        methods: {
            changeCurrency() {
                this.form.bank_account_id = null
                this.form.minimum_commission = null
                this.clearIncompatibleHka()
            },
            clearIncompatibleHka() {
                if (this.incompatibleHka) this.form.hka_code = null
            },
            formatPercentage(value) {
                const [whole, fraction = ''] = String(value).split('.')
                return whole + '.' + fraction.replace(/0+$/, '').padEnd(2, '0')
            },
            removeTax() { this.showTax = false; this.form.iva_rate_type_id = null },
            changeCollectionKind() {
                if (!['cash', 'account'].includes(this.form.collection_kind)) this.form.available_for_change = false
                if (this.form.collection_kind !== 'account') {
                    this.form.bank_id = this.form.bank_account_id = this.form.commission_percentage = this.form.minimum_commission = null
                }
                this.form.is_credit = this.form.collection_kind === 'credit' ? 1 : 0
                if (this.form.collection_kind === 'unclassified') this.form.is_active = false
                this.clearIncompatibleHka()
            },
            initForm() {
                this.errors = {}
                this.showTax = false
                this.form = {
                    id: null,
                    description: null,
                    collection_kind: 'account',
                    currency_type_id: 'VES',
                    bank_id: null,
                    bank_account_id: null,
                    commission_percentage: null,
                    minimum_commission: null,
                    hka_code: null,
                    iva_rate_type_id: null,
                    is_active: true,
                    available_for_change: false,
                    is_credit: 0,
                    number_days: 0,
                }
            },
            create() {
                this.titleDialog = (this.recordId)? 'Editar método de pago':'Nuevo método de pago'
                const generation = ++this.loadGeneration
                this.formReady = false
                this.fiscalEmissionMode = null
                const record = this.recordId ? this.$http.get(`/${this.resource}/record/${this.recordId}`) : Promise.resolve(null)
                return Promise.all([this.$http.get(`/${this.resource}/tables`), record]).then(([tables, response]) => {
                    if (generation !== this.loadGeneration) return
                    const data = tables.data
                    if (!['digital', 'fiscal_machine', 'free_form'].includes(data.fiscal_emission_mode)) throw new Error('Modalidad fiscal desconocida')
                    this.currencyTypes = data.currency_types
                    this.banks = data.banks
                    this.bankAccounts = data.bank_accounts
                    this.taxes = data.taxes
                    this.hkaPaymentMethods = data.hka_payment_methods
                    this.fiscalEmissionMode = data.fiscal_emission_mode
                    if (response) {
                        this.showTax = !!response.data.iva_rate_type_id
                        this.form = {...response.data, is_active: !!response.data.is_active, available_for_change: !!response.data.available_for_change}
                    }
                    this.formReady = true
                }).catch(() => {
                    if (generation === this.loadGeneration) this.$message.error('No se pudo cargar la configuración del método de pago')
                })
            },
            submit() {
                if (!this.formReady) return
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
                this.loadGeneration++
                this.formReady = false
                this.fiscalEmissionMode = null
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
