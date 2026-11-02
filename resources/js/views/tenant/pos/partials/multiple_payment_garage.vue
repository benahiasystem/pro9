<template>
    <el-dialog :title="titleDialog"   :visible="showDialog"  @open="create"  :close-on-click-modal="false" :close-on-press-escape="false" :show-close="false">

        <div class="form-body">
            <div class="row" >
                <div class="col-lg-12">

                    <table>
                    <thead>
                        <tr width="100%">
                            <th v-if="draftPayments.length>0">Método de pago</th>
                            <th v-if="draftPayments.length>0">Destino</th>
                            <th v-if="draftPayments.length>0">Referencia</th>
                            <th v-if="draftPayments.length>0">Monto</th>
                            <th width="15%"><a href="#" @click.prevent="clickAddPayment()" class="text-center font-weight-bold text-info">[+ Agregar]</a></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in draftPayments" :key="index">
                            <td>
                                <div class="form-group mb-2 mr-2">
                                    <span v-if="isRecordedReceipt(row)">{{ (row.payment_method_snapshot || {}).description || (row.payment_method_type || {}).description || row.payment_method_type_id }}</span>
                                    <el-select v-else v-model="row.payment_method_type_id">
                                        <el-option v-for="option in payment_method_types" :key="option.id" :value="option.id" :label="option.description"></el-option>
                                    </el-select>
                                    <small v-if="!row.payment_method_type_id" class="text-muted">No hay efectivo activo para la moneda del documento; seleccione un método.</small>
                                </div>
                            </td>
                            <td>
                                <div class="form-group mb-2 mr-2">
                                    <span v-if="isRecordedReceipt(row)">{{ row.payment_destination_description || 'Destino registrado' }}</span>
                                    <el-select v-else v-model="row.payment_destination_id" filterable :disabled="classifiedPaymentDestinationDisabled(payment_destinations, row)">
                                        <el-option v-for="option in classifiedPaymentDestinations(payment_destinations, row)" :key="option.id" :value="option.id" :label="option.description"></el-option>
                                    </el-select>
                                </div>
                            </td>
                            <td>
                                <div class="form-group mb-2 mr-2"  >
                                    <el-input v-model="row.reference" :disabled="isRecordedReceipt(row)"
                                              @focus="valueInputSelect"
                                              @click.native="valueInputSelect"></el-input>
                                </div>
                            </td>
                            <td>
                                <div class="form-group mb-2 mr-2" >
                                    <el-input v-model="row.payment" :disabled="isRecordedReceipt(row)"
                                              @focus="valueInputSelect"
                                              @click.native="valueInputSelect"></el-input>
                                </div>
                            </td>
                            <td><igtf-settings-status v-if="fiscalPaymentsApplicable" />
                            <igtf-payment-fields :payment-methods="payment_method_types" v-if="fiscalPaymentsApplicable && !isRecordedReceipt(row)" :igtf-enabled="igtfEnabled" :settings-version="igtfSettings.revision"
                                :payment="row" :document-currency="currencyTypeId || 'VES'" :document-rate="exchangeRateSale" :customer-id="customerId" />
                            <receipt-currency-fields v-if="documentTypeId === '80' && !isRecordedReceipt(row)" :row="row" :methods="payment_method_types" :document-currency="currencyTypeId" :document-rate="exchangeRateSale" /></td>
                            <td class="series-table-actions text-center">
                                <button v-if="!isRecordedReceipt(row)" type="button" class="btn waves-effect waves-light btn-xs btn-danger" @click.prevent="clickCancel(index)">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </td>
                            <br>
                        </tr>
                    </tbody>
                </table>


                </div>

            </div>
        </div>

        <span slot="footer" class="dialog-footer pos-payments-footer">
            <el-button @click.prevent="close()">Cerrar</el-button>
            <el-button type="primary" @click.prevent="accept()">Aceptar</el-button>
        </span>
    </el-dialog>
</template>

<style scoped>
.pos-payments-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    width: 100%;
}
</style>

<script>
import fiscalPaymentSettings from "@mixins/fiscal-payment-settings"
import {suggestRemainingPayment} from "@helpers/document-creation"
import {defaultCashForCurrency, isRecordedReceipt} from "@helpers/payment-classification"

import IgtfPaymentFields from "../../documents/partials/igtf_fields.vue"
import ReceiptCurrencyFields from '@components/ReceiptCurrencyFields.vue'
    export default {
        mixins: [fiscalPaymentSettings],
        components: {IgtfPaymentFields, ReceiptCurrencyFields},
        props: ['showDialog', 'payments', 'total', 'currencyTypeId', 'exchangeRateSale', 'customerId', 'paymentConditionId', 'documentTypeId'],
        data() {
            return {
                titleDialog: 'Pagos',
                loading: false,
                errors: {},
                form: {},
                company: {},
                configuration: {},
                activeName: 'first',
                payment_method_types:[],
                payment_destinations: [],
                draftPayments: [],
                paymentsSnapshot: [],
            }
        },
        async created() {

            await this.$http.get(`/pos/payment_tables`)
                .then(response => {
                    this.payment_method_types = response.data.payment_method_types
                    this.payment_destinations = response.data.payment_destinations
                    this.getFormPosLocalStorage()
                })

            this.events()
        },
        methods: {
            clonePayments(list) {
                return JSON.parse(JSON.stringify(Array.isArray(list) ? list : []))
            },
            getFormPosLocalStorage(){
                let form_pos = localStorage.getItem('form_pos_garage');
                form_pos = JSON.parse(form_pos)
                if (form_pos) {
                    if (form_pos.payment_condition_id === '02') return;

                    if(form_pos.payments.length == 0){

                        this.clickAddPayment(this.total)

                    }else{
                        this.$eventHub.$emit('localSPaymentsGarage', (form_pos.payments))
                        this.$emit('add', form_pos.payments);
                    }
                }

            },
            create(){
                this.paymentsSnapshot = this.clonePayments(this.payments)
                this.draftPayments = this.clonePayments(this.payments)

                if (this.draftPayments.length === 0 && this.paymentConditionId !== '02') {
                    this.pushDraftPayment(this.total)
                }
            },
            valueInputSelect(event) {
                const target = event && event.target
                if (!target) return
                const input = (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA')
                    ? target
                    : (target.querySelector && target.querySelector('input'))
                if (!input || typeof input.select !== 'function') return
                this.$nextTick(() => input.select())
            },
            buildPaymentRow(total = 0) {
                const method = defaultCashForCurrency(this.payment_method_types, this.currencyTypeId)
                return {
                    id: null,
                    document_id: null,
                    sale_note_id: null,
                    date_of_payment:  moment().format('YYYY-MM-DD'),
                    payment_method_type_id: method?.id || null,
                    payment_destination_id: method?.collection_kind === 'cash' && this.payment_destinations.some(destination => destination.id === 'cash') ? 'cash' : null,
                    reference: null,
                    payment: total,
                }
            },
            pushDraftPayment(total = 0) {
                this.draftPayments.push(this.buildPaymentRow(total))
                this.calculatePayments(this.draftPayments)
            },
            clickAddPayment(total = 0) {
                if (this.showDialog) {
                    this.pushDraftPayment(total)
                    return
                }

                this.payments.push(this.buildPaymentRow(total))
                this.calculatePayments(this.payments)
                this.$emit('add', this.payments)
            },
            // ######## INICIO CALCULOS EXACTOS PRO9 ########
            calculatePayments(list = null) {
                if (this.paymentConditionId === '02') return;
                const payments = list || (this.showDialog ? this.draftPayments : this.payments);
                suggestRemainingPayment({total: this.total || '0', currency_type_id: this.currencyTypeId}, payments);
            },
            accept() {
                const payments = this.clonePayments(this.draftPayments)
                this.payments.splice(0, this.payments.length, ...payments)
                this.$emit('add', payments)
                this.$emit('update:showDialog', false)
            },
            close() {
                const snapshot = this.clonePayments(this.paymentsSnapshot)
                this.payments.splice(0, this.payments.length, ...snapshot)
                this.draftPayments = snapshot
                this.$emit('update:showDialog', false)
            },
            clickCancel(index) {
                if (isRecordedReceipt(this.draftPayments[index])) return
                this.draftPayments.splice(index, 1);
                this.calculatePayments(this.draftPayments);
            },
            async events() {
                // await this.$eventHub.$on("cancelSaleGarage", () => {
                //     console.info('multiplepayment');
                //     this.getFormPosLocalStorage()
                // });
            },
        }
    }
</script>
