<template>
    <el-dialog :title="title || 'Cargando pagos...'" :visible="showDialog" @close="close" @open="getData(true)" width="80%">
        <p v-if="document.islr_retention && document.islr_retention.prevision">ISLR prevista: {{ document.islr_retention.prevision.amount }} {{ document.currency_type_id }}. Permanece como saldo pendiente.</p>
        <div class="form-body">
            <div class="row">
                <div class="col-md-12" v-if="records && records.length >= 0">
                    <!--<div class="right-wrapper pull-right">
                        <button type="button" @click.prevent="clickDownloadReport()" class="btn btn-custom btn-sm  mt-2 mr-2"><i class="fas fa-money-bill-wave-alt"></i> Reporte</button>
                    </div>-->

                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Fecha de pago</th>
                                <th>Método de pago <span class="text-danger">*</span></th>
                                <th>Destino <span class="text-danger">*</span></th>
                                <th class="text-center">Total entregado <span class="text-danger">*</span></th>
                                <!-- <th>Referencia</th> -->
                                <th>¿Pago recibido?</th>
                                <template v-if="external">
                                    <th>Imprimir</th>
                                </template>

                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="(row, index) in records" :key="row.id ? 'payment-' + row.id : 'draft-' + row.draftId">
                                <template v-if="row.id">
                                    <td>PAGO-{{ row.id }}</td>
                                    <td>{{ row.date_of_payment }}</td>
                                    <td>{{ row.payment_method_type_description }}</td>
                                    <td>{{ row.destination_description }}</td>
                                    <!-- <td>{{ row.reference }}</td> -->
                                    <!-- ######## INICIO MONEDA DEL MEDIO DE COBRO ######## -->
                                    <td class="text-center">{{ document.currency_type_id }} {{ row.payment }} aplicado<br>
                                        <small v-if="row.reversed_at">Cobro revertido</small>
                                        <small v-else-if="row.receipt_parent_id">IGTF del cobro PAGO-{{ row.receipt_parent_id }}</small>
                                        <small v-else-if="row.receipt_reused">Cobro reutilizado; no genera otra recepción</small>
                                        <small v-else-if="row.tendered_original_amount != null">{{ row.currency_type_id }} {{ row.tendered_original_amount }} recibido; vuelto {{ row.currency_type_id }} {{ row.received_change }}; IGTF {{ row.tax_amount }}</small>
                                        <small v-else-if="row.original_amount != null">{{ row.currency_type_id }} {{ row.original_amount }} neto recibido; IGTF {{ row.tax_amount }}</small>
                                        <small v-else>Importe recibido no disponible; IGTF {{ row.tax_amount }}</small>
                                        <br><small v-if="row.currency_type_id !== document.currency_type_id && row.exchange_rate">Tasa: {{ row.exchange_rate }} Bs./USD</small>
                                        <br><small v-if="hasPaymentAdjustment(row)">Ajuste de liquidación: {{ paymentAdjustmentText(row) }}</small>
                                    </td>
                                    <!-- ######## FIN MONEDA DEL MEDIO DE COBRO ######## -->

                                    <td class="text-left">

                                        <!-- pagos que no cuenten con la opcion pago recibido -->
                                        <template v-if="row.payment_received === null">

                                            <span class="d-block" v-if="row.reference"><b>Referencia:</b> {{ row.reference }}</span>
                                            <button  type="button" v-if="row.filename" class="btn waves-effect waves-light btn-xs btn-primary mb-2  mt-2" @click.prevent="clickDownloadFile(row.filename)">
                                                <i class="fas fa-fw fa-file-download"></i>
                                                Descargar voucher
                                            </button>
                                            <!-- <el-button type="primary" @click="showDialogLinkPayment(row)">Link de pago</el-button> -->

                                        </template>
                                        <!-- nuevo flujo -->
                                        <template v-else>

                                            <span class="d-block mb-2 font-bold">{{ row.payment_received_description }}</span>

                                            <template v-if="row.payment_received">

                                                <span class="d-block" v-if="row.reference"><b>Referencia:</b> {{ row.reference }}</span>
                                                <button  type="button" v-if="row.filename" class="btn btn-sm btn-primary mb-2  mt-2" @click.prevent="clickDownloadFile(row.filename)">
                                                    <i class="fas fa-fw fa-file-download"></i>
                                                    Descargar voucher
                                                </button>

                                            </template>
                                            <template v-else>
                                                <button  type="button" class="btn btn-sm btn-primary" @click="showDialogLinkPayment(row)">
                                                    <i class="fas fa-fw fa-link"></i>
                                                    Link de pago
                                                </button>
                                            </template>

                                        </template>

                                    </td>
                                <template  v-if="external">
                                    <td class="series-table-actions text-center">
                                        <button type="button" class="btn waves-effect waves-light btn-xs btn-primary" @click.prevent="clickOptionsPrint()"><i class="fas fa-file-upload"></i></button>
                                    </td>
                                </template>


                                    <td class="series-table-actions text-right">

                                        <template v-if="permissions.delete_payment && !row.reversed_at">
                                            <button type="button" class="btn waves-effect waves-light btn-xs btn-danger" @click.prevent="clickDelete(row.id)">Revertir</button>
                                        </template>

                                        <!--<el-button type="danger" icon="el-icon-delete" plain @click.prevent="clickDelete(row.id)"></el-button>-->
                                    </td>
                                </template>
                                <template v-else>
                                    <td></td>
                                    <td>
                                        <div class="form-group mb-0" :class="{'has-danger': row.errors.date_of_payment}">
                                            <el-date-picker v-model="row.date_of_payment"
                                                            :disabled="row.loading"
                                                            @change="changePaymentDate(row)"
                                                            type="date"
                                                            :clearable="false"
                                                            format="dd/MM/yyyy"
                                                            value-format="yyyy-MM-dd"></el-date-picker>
                                            <small class="form-control-feedback" v-if="row.errors.date_of_payment" v-text="row.errors.date_of_payment[0]"></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-group mb-0" :class="{'has-danger': row.errors.payment_method_type_id}">
                                            <el-select v-model="row.payment_method_type_id" :disabled="row.loading" @change="changePaymentMethod(row)">
                                                <el-option v-for="option in payment_method_types" v-show="['cash', 'account'].includes(option.collection_kind)" :key="option.id" :value="option.id" :label="option.description"></el-option>
                                            </el-select>
                                            <small class="form-control-feedback" v-if="row.errors.payment_method_type_id" v-text="row.errors.payment_method_type_id[0]"></small>
                                            <label v-if="row.currency_type_id && (row.currency_type_id !== document.currency_type_id || !document.exchange_rate_sale)" class="d-block mt-1">
                                                Tasa VES por USD
                                                <el-input v-model="row.exchange_rate" :disabled="row.loading" @input="changePaymentRate(row)"></el-input>
                                            </label>
                                            <small class="d-block text-danger" v-if="row.errors.exchange_rate">{{ row.errors.exchange_rate[0] }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-group mb-0" :class="{'has-danger': row.errors.payment_destination_id}">
                                            <el-select v-model="row.payment_destination_id" filterable :disabled="row.loading || classifiedPaymentDestinationDisabled(payment_destinations, row)">
                                                <el-option v-for="option in classifiedPaymentDestinations(payment_destinations, row)" :key="option.id" :value="option.id" :label="option.description"></el-option>
                                            </el-select>
                                            <small class="form-control-feedback" v-if="row.errors.payment_destination_id" v-text="row.errors.payment_destination_id[0]"></small>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="form-group mb-0 payment-tender" :class="{'has-danger': draftError}">
                                            <el-popover placement="top" trigger="hover" :disabled="!tenderDetail">
                                                <div v-if="tenderDetail" class="text-start" data-testid="document-tender-detail">
                                                    <small class="d-block">{{ row.currency_type_id }}</small>
                                                    <small v-if="row.currency_type_id === 'USD'" class="d-block">Tasa de cambio: Bs. {{ tenderDetail.rate }} por USD</small>
                                                    <small class="d-block">Principal recibido: {{ draftQuote.principal }} {{ row.currency_type_id }}</small>
                                                    <small class="d-block">IGTF: {{ draftQuote.tax }} {{ row.currency_type_id }}</small>
                                                    <small class="d-block"><strong>Total cobrado: {{ tenderDetail.total }} {{ row.currency_type_id }} ⇄ {{ tenderDetail.equivalent }}</strong></small>
                                                </div>
                                                <el-input slot="reference" :value="row.tendered_amount" :disabled="row.loading || !row.currency_type_id"
                                                          aria-label="Total entregado" @input="changeTenderedAmount(row, $event)" @focus="$event.target.select()">
                                                    <span slot="suffix">{{ row.currency_type_id === 'USD' ? '$' : (row.currency_type_id === 'VES' ? 'Bs.' : '') }}</span>
                                                </el-input>
                                            </el-popover>
                                            <div class="payment-progress" aria-live="polite">
                                                <span>Pagado <strong>{{ paymentProgress.paid }}</strong></span>
                                                <span aria-hidden="true"> · </span>
                                                <span class="text-danger">Falta <strong>{{ paymentProgress.missing }}</strong></span>
                                            </div>
                                            <small class="d-block text-muted" v-if="contextLoading">Cargando contexto fiscal…</small>
                                            <button type="button" class="btn btn-link btn-sm p-0" v-if="contextError" @click="retryPaymentContext(row)">Reintentar contexto fiscal</button>
                                            <small class="d-block text-danger" v-if="draftError" role="alert">{{ draftError }}</small>
                                        </div>
                                    </td>

                                    <!-- <td>
                                        <div class="form-group mb-0" :class="{'has-danger': row.errors.reference}">
                                            <el-input v-model="row.reference"></el-input>
                                            <small class="form-control-feedback" v-if="row.errors.reference" v-text="row.errors.reference[0]"></small>
                                        </div>
                                    </td> -->
                                    <td class="row no-gutters px-0">

                                        <div class="col-md-7">
                                            <div class="row no-gutters">
                                                <div class="col-md-3">
                                                    <el-radio class="mb-3 pt-2" v-model="row.payment_received" :disabled="row.loading || row.uploading" label="1">SI</el-radio>
                                                    <el-radio v-model="row.payment_received" :disabled="row.loading || row.uploading" label="0">NO</el-radio>
                                                </div>
                                                <div class="col-md-9">
                                                    <el-upload
                                                        :data="{index: row.draftId}"
                                                        :headers="headers"
                                                        :multiple="false"
                                                        :on-remove="() => handleRemove(row)"
                                                        :action="`/finances/payment-file/upload`"
                                                        :show-file-list="true"
                                                        :file-list="fileList"
                                                        :on-success="(response, file, files) => onSuccess(row, response, files)"
                                                        :on-error="error => onUploadError(row, error)"
                                                        :before-upload="() => beginVoucherUpload(row)"
                                                        :limit="1"
                                                        :disabled="row.payment_received == '0' || row.loading || row.uploading"
                                                        class="pb-1"
                                                        >

                                                        <template v-if="row.payment_received == '0'">
                                                            <el-button type="info" class="btn btn-sm">
                                                                <i class="fas fa-fw fa-upload"></i>
                                                                Cargar voucher
                                                            </el-button>
                                                        </template>
                                                        <template v-else>
                                                            <button  type="button" class="btn btn-sm btn-primary"  slot="trigger">
                                                                <i class="fas fa-fw fa-upload"></i>
                                                                Cargar voucher
                                                            </button>
                                                        </template>
                                                    </el-upload>
                                                    <template v-if="row.payment_received == '1'">
                                                        <el-button type="info" class="btn btn-sm">
                                                            <i class="fas fa-fw fa-link"></i>
                                                            Link de pago
                                                        </el-button>
                                                    </template>
                                                    <template v-else>
                                                        <button  type="button" class="btn btn-sm btn-primary" @click="showDialogLinkPayment(row)">
                                                            <i class="fas fa-fw fa-link"></i>
                                                            Link de pago
                                                        </button>
                                                    </template>
                                                </div>
                                            </div>

                                        </div>
                                        <div class="col-md-5">
                                            <div class="form-group mb-0" :class="{'has-danger': row.errors.reference}">
                                                <el-input v-model="row.reference" placeholder="Referencia y/o N° Operación" :disabled="row.payment_received == '0' || row.loading" @focus="$event.target.select()"></el-input>
                                                <small class="form-control-feedback" v-if="row.errors.reference" v-text="row.errors.reference[0]"></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="series-table-actions text-right px-0">
                                        <button type="button" class="btn waves-effect waves-light btn-sm btn-info" :disabled="row.loading || row.uploading || contextLoading || !!contextError" @click.prevent="clickSubmit(index)">
                                            <i class="fa fa-check d-block"></i>
                                        </button>

                                        <button type="button" class="btn waves-effect waves-light btn-sm btn-danger" :disabled="row.loading" @click.prevent="clickCancel(index)">
                                            <i class="fa fa-trash d-block"></i>
                                        </button>
                                    </td>
                                </template>
                            </tr>
                            </tbody>
                            <tfoot>
                            <tr>
                                <td colspan="6" class="text-right">TOTAL PAGADO</td>
                                <td class="text-right">{{ document.total_paid }}</td>
                            </tr>
                            <tr v-if="document.credit_notes_total">
                                <td colspan="6" class="text-right">TOTAL NOTA CRÉDITO</td>
                                <td class="text-right">{{ document.credit_notes_total }}</td>
                            </tr>
                            <tr>
                                <td colspan="6" class="text-right">TOTAL A PAGAR</td>
                                <td class="text-right">{{ document.total }}</td>
                            </tr>
                            <tr>
                                <td colspan="6" class="text-right">PENDIENTE DE PAGO</td>
                                <td class="text-right">{{ document.total_difference }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="col-md-12 text-center pt-2" v-if="showAddButton && hasOutstandingBalance()">
                    <template v-if="permissions.create_payment">
                        <el-button :loading="loadingFetch" type="primary" icon="el-icon-plus" @click="clickAddRow">Nuevo</el-button>
                    </template>
                </div>
            </div>
        </div>

        <dialog-link-payment
            :documentPaymentId="documentPayment.id"
            :currencyTypeId="document.currency_type_id"
            :exchangeRateSale="document.exchange_rate_sale"
            :payment="documentPayment.payment"
            :showDialog.sync="showDialogLink"
            :documentPayment="documentPayment"
            >
        </dialog-link-payment>

        <document-options
            :recordId="this.documentId"
            :showDialogOptions.sync="showDialogOptions"
            :showClose="showDialogClose"
            :type="this.type"
            :configuration="this.configuration"
        ></document-options>
    </el-dialog>

</template>

<script>
import {exactValue} from '../../../../helpers/exchange-rate-math'
import {loadCalculationContext} from '../../../../helpers/calculation-context'
import {documentPaymentProgress, documentTenderDetail, quoteDocumentTender, suggestDocumentTender} from '../../../../helpers/document-payment-draft'
import {convertExact} from '../../../../helpers/local-payment-quote'

    import {deletable} from '../../../../mixins/deletable'
    import DialogLinkPayment from './dialog_link_payment.vue'
    import DocumentOptions from '../../../../../../modules/Finance/Resources/assets/js/views/unpaid/partials/options.vue'
    export default {
        props: ['showDialog', 'documentId', 'external','configuration'],
        mixins: [deletable],
        components: {
            DialogLinkPayment,
            DocumentOptions
        },
        data() {
            return {
                title: null,
                resource: 'document_payments',
                records: [],
                payment_destinations:  [],
                headers: headers_token,
                fileList: [],
                payment_method_types: [],
                showAddButton: true,
                loadingFetch: false, 
                document: {},
                permissions: {},
                nextDraftId: 0,
                documentPayment: {},
                showDialogLink: false,
                showDialogOptions: false,
                showDialogClose:false,
                type:'document',
                paymentContext: null,
                contextLoading: false,
                contextError: '',
                contextSequence: 0,
                dataSequence: 0,
                refreshBalanceBeforeContext: false,
            }
        },
        created() {
            this.$eventHub.$on('reloadDataPayments', this.reloadAfterPaymentLink)
        },
        beforeDestroy() {
            this.$eventHub.$off('reloadDataPayments', this.reloadAfterPaymentLink)
            this.dataSequence++
            this.contextSequence++
        },
        watch: {
            documentId() {
                if (this.showDialog) this.getData(true)
            },
        },
        computed: {
            draft() { return this.records.find(row => !row.id) },
            draftEvaluation() {
                const row = this.draft
                if (!row?.payment_method_type_id || !this.paymentContext?.dates[row.date_of_payment] ||
                    !row.tendered_amount.trim() || row.tendered_amount.endsWith('.')) return {}
                try {
                    return {quote: quoteDocumentTender(row, this.document, this.paymentContext, this.payment_method_types)}
                } catch (error) {
                    return {error: error.message}
                }
            },
            draftQuote() { return this.draftEvaluation.quote },
            tenderDetail() {
                return this.draftQuote ? documentTenderDetail(this.draftQuote, this.draft.currency_type_id) : null
            },
            draftError() {
                const fields = ['quoted_balance', 'quoted_igtf_rate', 'igtf_status', 'quoted_tax_amount', 'tendered_amount',
                    'payment', 'original_amount', 'currency_type_id', 'financial_intermediation', 'document', 'operation_key', 'series']
                return this.contextError || fields.map(field => this.draft?.errors[field]?.[0]).find(Boolean) || this.draftEvaluation.error
            },
            paymentProgress() {
                try {
                    const {paid, missing} = documentPaymentProgress(this.document, this.draftQuote)
                    return {paid: `Bs. ${paid}`, missing: `Bs. ${missing}`}
                } catch (_) {
                    return {paid: '—', missing: '—'}
                }
            },
        },
        methods: {
            // ######## INICIO CALCULOS EXACTOS SAB ########
            hasOutstandingBalance() { return exactValue(this.document, 'total_difference').compare(0) > 0; },
            hasPaymentAdjustment(row) { return exactValue(row, 'payment_adjustment').compare(0) !== 0; },
            paymentAdjustmentText(row) {
                const value = exactValue(row, 'payment_adjustment');
                const text = value.decimal();
                if (text !== '0' || value.compare(0) === 0) return text;
                const fraction = value.fraction();
                return `${fraction.n}/${fraction.d}`;
            },
            // ######## FIN CALCULOS EXACTOS SAB ########
            todayPaymentDate() {
                return new Date().toLocaleDateString('sv-SE', {timeZone: 'America/Caracas'})
            },
            async loadPaymentContext(date, force = false) {
                const sequence = ++this.contextSequence
                const documentId = this.documentId
                this.contextLoading = true
                this.contextError = ''
                this.paymentContext = null
                const isCurrent = () => sequence === this.contextSequence && this.showDialog && this.documentId === documentId
                try {
                    if (this.refreshBalanceBeforeContext) {
                        const {data} = await this.$http.get(`/${this.resource}/document/${documentId}`)
                        if (!isCurrent()) return
                        this.document = data
                        this.refreshBalanceBeforeContext = false
                    }
                    const context = await loadCalculationContext(this.$http, {document_id: documentId, dates: [date]}, force)
                    if (!isCurrent()) return
                    this.payment_method_types = context.methods.filter(method => ['cash', 'account'].includes(method.collection_kind))
                    this.payment_destinations = context.destinations
                    this.paymentContext = context
                    if (this.draft) {
                        this.syncPaymentMethod(this.draft)
                        this.suggestTenderedAmount(this.draft)
                    }
                } catch (_) {
                    if (isCurrent()) this.contextError = 'No se pudo cargar el contexto fiscal. Reintente para evaluar el cobro.'
                } finally {
                    if (isCurrent()) this.contextLoading = false
                }
            },
            retryPaymentContext(row) {
                return this.loadPaymentContext(row.date_of_payment, true)
            },
            changePaymentDate(row) {
                row.errors = {}
                return this.loadPaymentContext(row.date_of_payment)
            },
            syncPaymentMethod(row) {
                const method = this.payment_method_types.find(item => String(item.id) === String(row.payment_method_type_id))
                row.currency_type_id = method?.currency_type_id || null
                this.clearIncompatiblePaymentDestinations([row])
            },
            changePaymentMethod(row) {
                const previousCurrency = row.currency_type_id
                row.errors = {}
                this.syncPaymentMethod(row)
                if (row.tenderedEdited && previousCurrency && previousCurrency !== row.currency_type_id && row.tendered_amount) {
                    try {
                        row.tendered_amount = convertExact(row.tendered_amount, previousCurrency, row.currency_type_id, row.exchange_rate).final(2)
                    } catch (_) {
                        row.tendered_amount = ''
                    }
                }
                this.suggestTenderedAmount(row)
            },
            changePaymentRate(row) {
                row.errors = {}
                this.suggestTenderedAmount(row)
            },
            changeTenderedAmount(row, value) {
                row.errors = {}
                row.tendered_amount = value
                row.tenderedEdited = true
            },
            suggestTenderedAmount(row) {
                if (row.tenderedEdited || !row.currency_type_id) return
                try {
                    row.tendered_amount = suggestDocumentTender(row, this.document, this.paymentContext, this.payment_method_types)
                } catch (_) {
                    row.tendered_amount = ''
                }
            },
            validateDraft(row) {
                if (!row.payment_method_type_id) {
                    this.$set(row.errors, 'payment_method_type_id', ['Seleccione un método de pago.'])
                    return false
                }
                if (!row.payment_destination_id) {
                    this.$set(row.errors, 'payment_destination_id', ['Seleccione un destino de pago.'])
                    return false
                }
                if (!this.draftQuote) {
                    this.$set(row.errors, 'tendered_amount', [this.draftError || 'Indique un total entregado válido.'])
                    return false
                }
                return true
            },
            showDialogLinkPayment(row) {
                if (row.loading || (!row.id && !this.validateDraft(row))) return
                this.documentPayment = {
                    ...row,
                    document_id: this.documentId,
                    payment: row.id ? row.payment : this.draftQuote.applied.decimal(),
                }
                this.showDialogLink = true
            },
            reloadAfterPaymentLink(documentId, paymentId) {
                if (!this.showDialog || !this.showDialogLink || this.documentId !== documentId || !paymentId) return
                return this.getData(true)
            },
            clickDownloadFile(filename) {
                window.open(
                    `/finances/payment-file/download-file/${filename}/documents`,
                    "_blank"
                );
            },
            beginVoucherUpload(row) {
                if (row.loading || row.uploading) return false
                row.uploading = true
                return true
            },
            onSuccess(row, response, fileList) {
                if (!this.showDialog || !this.records.includes(row)) return
                row.uploading = false
                if (response.success) {
                    if (response.data.index !== row.draftId) return
                    this.fileList = fileList
                    row.filename = response.data.filename
                    row.temp_path = response.data.temp_path
                } else {
                    this.handleRemove(row)
                    this.$message.error(response.message || 'No se pudo cargar el archivo.')
                }
            },
            onUploadError(row, error) {
                if (!this.showDialog || !this.records.includes(row)) return
                this.handleRemove(row)
                this.$message.error(this.getRequestErrorMessage(error))
            },
            getRequestErrorMessage(error) {
                const data = error && error.response ? error.response.data : null

                if (typeof data === 'string' && data.trim()) {
                    return data
                }

                if (data && data.message) {
                    return data.message
                }

                if (error && error.message) {
                    return error.message
                }

                return 'Ocurrió un error al procesar la solicitud.'
            },
            handleRemove(row) {
                if (!this.records.includes(row)) return
                row.uploading = false
                row.filename = null
                row.temp_path = null
                this.fileList = []
            },
            initForm() {
                this.records = [];
                this.fileList = [];
                this.showAddButton = true;
                this.document = {};
                this.title = null;
            },
            async getData(reset = false) {
                if (!this.showDialog || (!reset && this.draft)) return
                const sequence = ++this.dataSequence
                const documentId = this.documentId
                const isCurrent = () => sequence === this.dataSequence && this.showDialog && this.documentId === documentId
                this.loadingFetch = true
                this.contextSequence++
                this.paymentContext = null
                this.contextError = ''
                this.contextLoading = false
                this.refreshBalanceBeforeContext = false
                this.initForm()
                try {
                    const [document, records, tables] = await Promise.all([
                        this.$http.get(`/${this.resource}/document/${documentId}`),
                        this.$http.get(`/${this.resource}/records/${documentId}`),
                        this.$http.get(`/${this.resource}/tables`),
                    ])
                    if (!isCurrent()) return
                    this.document = document.data
                    this.title = 'Pagos del comprobante: ' + this.document.number_full
                    this.records = records.data.data
                    this.payment_method_types = tables.data.payment_method_types
                    this.payment_destinations = tables.data.payment_destinations
                    this.permissions = tables.data.permissions
                    if (this.permissions.create_payment && this.hasOutstandingBalance()) {
                        await this.loadPaymentContext(this.todayPaymentDate(), true)
                    }
                } catch (error) {
                    if (isCurrent()) this.$message.error(this.getRequestErrorMessage(error))
                } finally {
                    if (isCurrent()) this.loadingFetch = false
                }
            },
            clickAddRow() {
                if (this.draft || this.loadingFetch) return
                const date = this.todayPaymentDate()
                const row = {
                    id: null,
                    draftId: ++this.nextDraftId,
                    date_of_payment: date,
                    payment_method_type_id: null,
                    payment_destination_id: null,
                    currency_type_id: null,
                    exchange_rate: this.document.exchange_rate_sale,
                    reference: null,
                    filename: null,
                    temp_path: null,
                    operation_key: window.crypto?.randomUUID?.(),
                    tendered_amount: '',
                    tenderedEdited: false,
                    errors: {},
                    loading: false,
                    uploading: false,
                    payment_received: '1',
                }
                this.records.push(row)
                this.showAddButton = false
                if (!this.paymentContext?.dates[date]) this.loadPaymentContext(date)
            },
            clickCancel(index) {
                if (this.records[index]?.loading) return
                this.records.splice(index, 1)
                this.fileList = []
                this.showAddButton = true
            },
            async clickSubmit(index) {
                const row = this.records[index]
                const documentId = this.documentId
                if (!row || row.loading || row.uploading || !this.validateDraft(row)) return
                const quote = this.draftQuote
                const form = {
                    document_id: documentId,
                    date_of_payment: row.date_of_payment,
                    payment_method_type_id: row.payment_method_type_id,
                    payment_destination_id: row.payment_destination_id,
                    reference: row.reference,
                    filename: row.filename,
                    temp_path: row.temp_path,
                    payment_received: row.payment_received,
                    currency_type_id: row.currency_type_id,
                    tendered_amount: quote.tendered,
                    exchange_rate: quote.rate,
                    operation_key: row.operation_key,
                    igtf_status: quote.status,
                    quoted_tax_amount: quote.tax,
                    quoted_igtf_rate: this.paymentContext.rate ?? '0',
                    quoted_balance: exactValue(this.document, 'total_difference').fraction(),
                }

                row.loading = true
                const isCurrent = () => this.showDialog && this.documentId === documentId && this.records.includes(row)
                try {
                    const {data} = await this.$http.post(`/${this.resource}`, form)
                    if (!isCurrent()) return
                    if (data.success) {
                        this.$message.success(data.message)
                        this.getData(true)
                        this.$eventHub.$emit('reloadData')
                    } else {
                        this.$message.error(data.message)
                    }
                } catch (error) {
                    if (!isCurrent()) return
                    const data = error.response?.status === 422 ? error.response.data : null
                    const errors = data?.errors || (data && !data.message ? data : null)
                    if (errors && typeof errors === 'object' && Object.keys(errors).length) {
                        row.errors = errors
                        if (['quoted_balance', 'quoted_igtf_rate', 'quoted_tax_amount', 'igtf_status',
                            'payment_method_type_id', 'currency_type_id', 'payment_destination_id'].some(field => errors[field])) {
                            row.tenderedEdited = true
                            this.refreshBalanceBeforeContext = true
                            await this.retryPaymentContext(row)
                        }
                    } else {
                        this.$message.error(this.getRequestErrorMessage(error))
                    }
                } finally {
                    row.loading = false
                }
            },
            // filterDocumentType(row){
            //
            //     if(row.contingency){
            //         this.document_types = _.filter(this.all_document_types, item => (item.id == '01' || item.id =='03'))
            //         row.document_type_id = (this.document_types.length > 0)?this.document_types[0].id:null
            //     }else{
            //         row.document_type_id = null
            //         this.document_types = this.all_document_types
            //     }
            // },
            // initDocumentTypes(){
            //     this.document_types = (this.all_document_types.length > 0) ? this.all_document_types : []
            // },
            close() {
                this.dataSequence++
                this.contextSequence++
                this.showDialogLink = false
                this.$emit('update:showDialog', false)
            },
            clickDelete(id) {
                this.destroy(`/${this.resource}/${id}`).then(() =>{
                        this.getData()
                        this.$eventHub.$emit('reloadData')
                        // this.initDocumentTypes()
                    }
                )
            },
            clickDownloadReport(id)
            {
                window.open(`/${this.resource}/report/${this.documentId}`, '_blank');
            },
            clickPrint(external_id) {
                 window.open(`/finances/unpaid/print/${external_id}/document`, '_blank');
            },
            clickOptionsPrint() {
                this.showDialogOptions = true
                this.showDialogClose=true
            },
        }
    }
</script>

<style scoped>
.payment-tender {
    width: 15rem;
}

.payment-progress {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 4px;
    white-space: nowrap;
}

.payment-progress strong {
    font-variant-numeric: tabular-nums;
}
</style>
