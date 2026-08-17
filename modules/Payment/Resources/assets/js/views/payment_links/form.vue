<!-- ######## INICIO MIGRACIÓN MONEDA VENEZUELA ######## -->
<template>
    <el-dialog
        :title="titleDialog"
        :visible="showDialog"
        width="720px"
        top="6vh"
        @close="close"
        @open="create">
        <form autocomplete="off" @submit.prevent="submit">
            <div class="pl-form">

                <div class="pl-toggles">
                    <div class="pl-toggle">
                        <div class="pl-toggle__text">
                            <span class="pl-toggle__title">Agregar cliente</span>
                            <span class="pl-toggle__hint">Asocia el link a un cliente registrado</span>
                        </div>
                        <el-switch v-model="form.with_customer"></el-switch>
                    </div>
                    <div class="pl-toggle" v-if="form.with_customer">
                        <div class="pl-toggle__text">
                            <span class="pl-toggle__title">Agregar comprobantes</span>
                            <span class="pl-toggle__hint">El total se calcula con lo que agregues</span>
                        </div>
                        <el-switch v-model="form.with_document"></el-switch>
                    </div>
                </div>

                <div class="form-group" :class="{'has-danger': errors.customer_id}" v-if="form.with_customer">
                    <label class="control-label">Cliente</label>
                    <el-select
                        v-model="form.customer_id"
                        :loading="loading_search"
                        :remote-method="searchRemoteCustomers"
                        filterable
                        remote
                        clearable
                        popper-class="el-select-customers"
                        placeholder="Escriba el nombre o número de documento del cliente">
                        <el-option v-for="option in customers" :key="option.id" :value="option.id" :label="option.description"></el-option>

                        <template slot="empty">
                            <p v-if="loading_search" class="el-select-dropdown__empty">Cargando...</p>
                            <p v-else class="el-select-dropdown__empty">No se encontraron resultados</p>
                        </template>
                    </el-select>
                    <small class="form-control-feedback" v-if="errors.customer_id" v-text="errors.customer_id[0]"></small>
                </div>

                <template v-if="form.with_document">
                    <div class="form-group" :class="{'has-danger': errors.documents}">
                        <label class="control-label">Factura</label>
                        <div class="pl-inline">
                            <el-select
                                v-model="document_id"
                                :loading="loading_search_document"
                                :remote-method="searchRemoteDocuments"
                                filterable
                                remote
                                clearable
                                placeholder="Escriba la serie o el número de la factura">
                                <el-option v-for="option in documents" :key="option.id" :value="option.id" :label="option.description"></el-option>

                                <template slot="empty">
                                    <p v-if="loading_search_document" class="el-select-dropdown__empty">Cargando...</p>
                                    <p v-else class="el-select-dropdown__empty">No se encontraron resultados</p>
                                </template>
                            </el-select>
                            <el-button icon="el-icon-plus" @click.prevent="clickAddDocument">Agregar</el-button>
                        </div>
                        <small class="form-control-feedback" v-if="errors.documents" v-text="errors.documents[0]"></small>
                    </div>

                    <div class="pl-docs" v-if="hasDocuments">
                        <table class="pl-docs__table">
                            <thead>
                                <tr>
                                    <th>Comprobante</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-end">Pendiente</th>
                                    <th class="pl-docs__col-input">Monto a pagar</th>
                                    <th class="pl-docs__col-action"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, index) in form.documents" :key="row.document_id">
                                    <td class="pl-docs__number">{{ row.number_full }}</td>
                                    <td class="text-end pl-docs__muted">{{ row.total }}</td>
                                    <td class="text-end pl-docs__muted">{{ row.pending }}</td>
                                    <td>
                                        <el-input v-model="row.payment" size="small" @input="changeDocumentPayment(row)"></el-input>
                                    </td>
                                    <td class="text-center">
                                        <button
                                            type="button"
                                            class="pl-docs__remove"
                                            title="Quitar comprobante"
                                            @click.prevent="clickRemoveDocument(index)">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="pl-empty" v-else>
                        <i class="fa fa-file-text-o"></i>
                        <span>Busca una factura y presiona <strong>Agregar</strong> para incluirla en el cobro</span>
                    </div>
                </template>

                <div class="pl-total" :class="{'has-danger': errors.total}">
                    <template v-if="form.with_document">
                        <div class="pl-total__summary">
                            <span class="pl-total__label">Total a cobrar</span>
                            <span class="pl-total__value">Bs. {{ formattedTotal }}</span>
                        </div>
                        <p class="pl-total__hint">Se calcula con los montos a pagar de los comprobantes</p>
                    </template>
                    <template v-else>
                        <label class="control-label">Total a cobrar</label>
                        <el-input v-model="form.total">
                            <template slot="prepend">Bs.</template>
                        </el-input>
                    </template>
                    <small class="form-control-feedback" v-if="errors.total" v-text="errors.total[0]"></small>
                </div>

            </div>
            <div class="form-actions text-end mt-3">
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
                resource: 'payment-links',
                errors: {},
                form: {},
                customers: [],
                all_customers: [],
                loading_search: false,
                documents: [],
                loading_search_document: false,
                document_id: null
            }
        },
        created() {
            this.initForm()
            this.getTables()
        },
        computed: {
            hasDocuments() {
                return !!(this.form.documents && this.form.documents.length > 0)
            },
            formattedTotal() {
                return Number(this.form.total || 0).toLocaleString('es-PE', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                })
            }
        },
        watch: {
            'form.with_customer'(value) {
                if (!value) {
                    this.form.customer_id = null
                    this.filterCustomers()
                }
            },
            'form.with_document'(value) {
                if (!value) {
                    this.form.documents = []
                    this.document_id = null
                    this.documents = []
                    this.form.total = 0
                }
            },
        },
        methods: {
            getTables(){

                this.$http.get(`/${this.resource}/tables`).then(response => {
                        this.all_customers = response.data.customers
                        this.filterCustomers()
                    })

            },
            filterCustomers() {
                this.customers = this.all_customers
            },
            initForm() {

                this.errors = {}

                this.form = {
                    id: null,
                    total: 0,
                    without_payment: true,
                    with_customer: false,
                    customer_id: null,
                    with_document: false,
                    documents: [],
                }

                this.filterCustomers()
                this.documents = []
                this.document_id = null

            },
            searchRemoteCustomers(input) {

                if (input.length > 0) {

                    this.loading_search = true

                    this.$http.get(`/reports/data-table/persons/customers?input=${input}`)
                        .then(response => {
                            this.customers = response.data.persons
                            this.loading_search = false
                        })
                        .catch(() => {
                            this.loading_search = false
                        })

                } else {
                    this.filterCustomers()
                }

            },
            searchRemoteDocuments(input) {

                if (input.length > 0) {

                    this.loading_search_document = true
                    let parameters = `input=${input}`

                    if (this.form.customer_id) {
                        parameters += `&customer_id=${this.form.customer_id}`
                    }

                    this.$http.get(`/${this.resource}/search/documents?${parameters}`)
                        .then(response => {
                            this.documents = response.data.documents
                            this.loading_search_document = false
                        })
                        .catch(() => {
                            this.loading_search_document = false
                        })

                } else {
                    this.documents = []
                }

            },
            clickAddDocument() {

                if (!this.document_id) {
                    return this.$message.error('Seleccione una factura')
                }

                if (_.find(this.form.documents, {document_id: this.document_id})) {
                    return this.$message.error('La factura ya fue agregada')
                }

                const document = _.find(this.documents, {id: this.document_id})

                if (!document) return

                this.form.documents.push({
                    instance_type: document.instance_type,
                    document_id: document.id,
                    number_full: document.number_full,
                    currency_type_id: document.currency_type_id,
                    total: document.total,
                    pending: document.pending,
                    payment: document.pending,
                })

                this.document_id = null
                this.calculateTotal()

            },
            clickRemoveDocument(index) {

                this.form.documents.splice(index, 1)
                this.calculateTotal()

            },
            changeDocumentPayment(row) {

                // solo números con hasta 2 decimales
                row.payment = String(row.payment).replace(/[^\d.]/g, '')

                this.calculateTotal()

            },
            calculateTotal() {

                const total = this.form.documents.reduce((accumulator, row) => {
                    return accumulator + (parseFloat(row.payment) || 0)
                }, 0)

                this.form.total = _.round(total, 2)

            },
            create() {

                this.titleDialog = (this.recordId)? 'Editar link de pago':'Nuevo link de pago'

                if (this.recordId) {
                    this.$http.get(`/${this.resource}/record/${this.recordId}`).then(response => {
                            this.form = response.data.data

                            this.$set(this.form, 'documents', this.form.documents || [])
                            this.$set(this.form, 'with_customer', !!this.form.customer_id)
                            this.$set(this.form, 'with_document', this.form.documents.length > 0)

                            if (this.form.customer_number) {
                                this.searchRemoteCustomers(this.form.customer_number)
                            }
                        })
                }
            },
            submit() {

                this.loading_submit = true
                this.$http.post(`${this.resource}/store`, this.form)

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
                        if (error.response.status === 422) {
                            this.errors = error.response.data
                        } else {
                            this.$message.error(error.response.data.message || 'No se pudo generar el link de pago')
                        }
                    })
                    .then(() => {
                        this.loading_submit = false
                    })

            },
            close() {
                this.$emit('update:showDialog', false)
                this.initForm()
            }
        }
    }
</script>

<style scoped>
.pl-form .form-group {
    margin-bottom: 18px;
}
.pl-form .control-label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
}
.pl-form .el-select {
    width: 100%;
}

/* interruptores */
.pl-toggles {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.pl-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex: 1 1 260px;
    padding: 12px 16px;
    border: 1px solid #e4e7ed;
    border-radius: 8px;
}
.pl-toggle__text {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}
.pl-toggle__title {
    font-weight: 600;
    font-size: 14px;
}
.pl-toggle__hint {
    font-size: 12px;
    opacity: 0.65;
}

/* busqueda de comprobante + boton */
.pl-inline {
    display: flex;
    align-items: center;
    gap: 10px;
}
.pl-inline .el-select {
    flex: 1;
    min-width: 0;
}

/* tabla de comprobantes */
.pl-docs {
    margin-bottom: 20px;
    border: 1px solid #e4e7ed;
    border-radius: 8px;
    overflow-x: auto;
}
.pl-docs__table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.pl-docs__table th {
    padding: 10px 12px;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    opacity: 0.6;
    white-space: nowrap;
    border-bottom: 1px solid #e4e7ed;
}
.pl-docs__table td {
    padding: 8px 12px;
    vertical-align: middle;
    border-bottom: 1px solid #f0f2f5;
}
.pl-docs__table tbody tr:last-child td {
    border-bottom: none;
}
.pl-docs__number {
    font-weight: 600;
    white-space: nowrap;
}
.pl-docs__muted {
    opacity: 0.7;
    white-space: nowrap;
}
.pl-docs__col-input {
    width: 150px;
}
.pl-docs__col-action {
    width: 48px;
}
.pl-docs__remove {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    padding: 0;
    border: none;
    border-radius: 6px;
    background: transparent;
    color: #f56c6c;
    cursor: pointer;
    transition: background-color 0.15s ease;
}
.pl-docs__remove:hover {
    background: #fef0f0;
}

/* estado vacio */
.pl-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-bottom: 20px;
    padding: 18px;
    border: 1px dashed #dcdfe6;
    border-radius: 8px;
    font-size: 13px;
    opacity: 0.75;
    text-align: center;
}

/* total */
.pl-total {
    padding-top: 16px;
    border-top: 1px solid #e4e7ed;
}
.pl-total__summary {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 16px;
}
.pl-total__label {
    font-weight: 600;
}
.pl-total__value {
    font-size: 24px;
    font-weight: 700;
    white-space: nowrap;
}
.pl-total__hint {
    margin: 4px 0 0;
    font-size: 12px;
    opacity: 0.6;
}
</style>
<!-- ######## FIN MIGRACIÓN MONEDA VENEZUELA ######## -->
