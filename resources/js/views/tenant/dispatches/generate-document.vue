<!-- ######## INICIO MIGRACIÓN MONEDA VENEZUELA ######## -->
<template>
<!-- ########## INICIO CAMBIO IGV A IVA -->
    <div>
        <el-dialog
            :close-on-click-modal="false"
            :close-on-press-escape="false"
            :show-close="false"
            :title="titleDialog"
            :visible="showDialog"
            width="60%"
            @open="create"
        >
            <div v-show="!showGenerate"
                 class="row">
                <div class="col-lg-4 col-md-4 col-sm-4 text-center font-weight-bold">
                    <p>Imprimir A4</p>
                    <button
                        class="btn btn-lg btn-info waves-effect waves-light"
                        type="button"
                        @click="clickToPrint('a4')"
                    >
                        <i class="fa fa-file-alt"></i>
                    </button>
                </div>
                <div class="col-lg-4 col-md-4 col-sm-4 text-center font-weight-bold">
                    <p>Imprimir A5</p>
                    <button
                        class="btn btn-lg btn-info waves-effect waves-light"
                        type="button"
                        @click="clickToPrint('a5')"
                    >
                        <i class="fa fa-file-alt"></i>
                    </button>
                </div>
                <div class="col-lg-4 col-md-4 col-sm-4 text-center font-weight-bold">
                    <p>Imprimir Ticket</p>
                    <button
                        class="btn btn-lg btn-info waves-effect waves-light"
                        type="button"
                        @click="clickToPrint('ticket')"
                    >
                        <i class="fa fa-receipt"></i>
                    </button>
                </div>
            </div>
            <br/>
            <div v-show="!showGenerate"
                 class="row">
                <div class="col-md-12">
                    <el-input v-model="customer_email">
                        <el-button
                            slot="append"
                            :loading="loading"
                            icon="el-icon-message"
                            @click="clickSendEmail"
                        >Enviar
                        </el-button
                        >
                    </el-input>
                </div>
            </div>
            <br/>
            <div v-if="typeUser == 'admin'"
                 class="row">
                <div v-show="!showGenerate"
                     class="col-md-9">
                    <div class="form-group">
                        <el-checkbox v-model="generate"
                        >Generar comprobante electrónico
                        </el-checkbox
                        >
                    </div>
                </div>
            </div>
            <div v-if="generate"
                 class="row">
                <div class="col-lg-12 pb-2">
                    <div class="form-group">
                        <label class="control-label font-weight-bold text-info">
                            Cliente
                        </label>
                        <el-select
                            v-model="document.customer_id"
                            :loading="loading_search"
                            :remote-method="searchRemoteCustomers"
                            class="border-left rounded-left border-info"
                            filterable
                            placeholder="Escriba el nombre o número de documento del cliente"
                            popper-class="el-select-customers"
                            remote
                            @change="changeCustomer"
                        >
                            <el-option
                                v-for="option in customers"
                                :key="option.id"
                                :label="option.description"
                                :value="option.id"
                            ></el-option>
                        </el-select>
                        <small
                            v-if="errors.customer_id"
                            class="form-control-feedback"
                            v-text="errors.customer_id[0]"
                        ></small>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div
                        :class="{ 'has-danger': errors.document_type_id }"
                        class="form-group"
                    >
                        <label class="control-label">Tipo comprobante</label>
                        <el-select
                            v-model="document.document_type_id"
                            class="border-left rounded-left border-info"
                            dusk="document_type_id"
                            popper-class="el-select-document_type"
                            @change="changeDocumentType"
                        >
                            <el-option
                                v-for="option in document_types"
                                :key="option.id"
                                :label="option.description"
                                :value="option.id"
                            ></el-option>
                        </el-select>
                        <small
                            v-if="errors.document_type_id"
                            class="form-control-feedback"
                            v-text="errors.document_type_id[0]"
                        ></small>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div :class="{ 'has-danger': errors.series_id }"
                         class="form-group">
                        <label class="control-label">Serie</label>
                        <el-select v-model="document.series_id">
                            <el-option
                                v-for="option in series"
                                :key="option.id"
                                :label="option.number || '\u00a0'"
                                :value="option.id"
                            ></el-option>
                        </el-select>
                        <small
                            v-if="errors.series_id"
                            class="form-control-feedback"
                            v-text="errors.series_id[0]"
                        ></small>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div
                        :class="{ 'has-danger': errors.date_of_issue }"
                        class="form-group"
                    >
                        <label class="control-label">Fecha de emisión</label>
                        <el-date-picker
                            v-model="document.date_of_issue"
                            :clearable="false"
                            readonly
                            type="date"
                            value-format="yyyy-MM-dd"
                            @change="changeDateOfIssue"
                        ></el-date-picker>
                        <small
                            v-if="errors.date_of_issue"
                            class="form-control-feedback"
                            v-text="errors.date_of_issue[0]"
                        ></small>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="form-group">
                        <label class="control-label">Tasa documental (VES por USD)</label>
                        <el-input :value="document.exchange_rate_sale || ''" disabled></el-input>
                        <small v-if="rateError" class="text-danger">{{ rateError }}</small>
                    </div>
                </div>
                <div v-if="document.items && document.items.length" class="col-12">
                    <table class="table table-sm">
                        <thead><tr><th>Descripción</th><th>Cantidad</th><th>Precio unitario</th><th>Total</th></tr></thead>
                        <tbody>
                        <tr v-for="(item, index) in document.items" :key="index">
                            <td>{{ item.item.description }}</td><td>{{ item.quantity }}</td>
                            <td>{{ (currency_type || {}).symbol || document.currency_type_id }} {{ item.unit_price }}</td>
                            <td>{{ (currency_type || {}).symbol || document.currency_type_id }} {{ item.total }}</td>
                        </tr>
                        </tbody>
                    </table>
                    <p>Total del comprobante: {{ (currency_type || {}).symbol || document.currency_type_id }} {{ document.total }}</p>
                </div>

                <div class="col-lg-6">
                    <div
                        :class="{ 'has-danger': errors.date_of_issue }"
                        class="form-group"
                    >
                        <!--<label class="control-label">Fecha de emisión</label>-->
                        <label class="control-label">Fecha de vencimiento</label>
                        <el-date-picker
                            v-model="document.date_of_due"
                            :clearable="false"
                            type="date"
                            value-format="yyyy-MM-dd"
                        ></el-date-picker>
                        <small
                            v-if="errors.date_of_due"
                            class="form-control-feedback"
                            v-text="errors.date_of_due[0]"
                        ></small>
                    </div>
                </div>
                <div class="col-lg-6 col-md-6">
                    <div
                        :class="{ 'has-danger': errors.purchase_order }"
                        class="form-group"
                    >
                        <label class="control-label">Orden de compra</label>
                        <el-input
                            v-model="document.purchase_order"
                        ></el-input>
                        <small
                            v-if="errors.purchase_order"
                            class="form-control-feedback"
                            v-text="errors.purchase_order[0]"
                        ></small>
                    </div>
                </div>

                <div class="col-lg-4" v-if="document.document_type_id == '03'">
                    <div
                         class="form-group">
                        <el-checkbox
                            v-model="document.is_receivable"
                            class="font-weight-bold"
                        >¿Es venta por cobrar?
                        </el-checkbox
                        >
                    </div>
                </div>

                <div class="col-lg-6 col-md-6 px-0"
                     v-if="show_has_retention">
                    <div class="row no-gutters">
                        <div class="col-10">¿Tiene retención de IVA?</div>
                        <div class="col-2">
                            <el-switch v-model="document.has_retention"
                                        @change="changeRetention"></el-switch>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 col-md-6">
                    <div class="form-group" :class="{'has-danger': errors.payment_condition_id}">
                        <label class="control-label">Condición de pago</label>
                        <el-select v-model="document.payment_condition_id" @change="changePaymentCondition">
                            <el-option
                                v-for="option in payment_conditions"
                                :key="option.id"
                                :label="option.name"
                                :value="option.id"
                            ></el-option>
                        </el-select>
                        <small
                            class="form-control-feedback"
                            v-if="errors.date_of_due"
                            v-text="errors.date_of_due[0]"
                        ></small>
                    </div>
                </div>

                <template>
                    <div class="col-lg-12">
                        <table>
                            <thead>
                            <tr width="100%">
                                <th v-if="document.payments.length > 0">M. Pago</th>
                                <th v-if="document.payments.length > 0">Destino</th>
                                <th v-if="document.payments.length > 0">Referencia</th>
                                <th v-if="document.payments.length > 0">Monto</th>
                                <th width="15%">
                                    <a
                                        class="text-center font-weight-bold text-info"
                                        href="#"
                                        @click.prevent="clickAddPayment"
                                    >[+ Agregar]</a
                                    >
                                </th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="(row, index) in document.payments"
                                :key="index">
                                <td>
                                    <div class="form-group mb-2 mr-2">
                                        <el-select v-model="row.payment_method_type_id" @change="markDefaultReceiptEdited(row)">
                                            <el-option
                                                v-for="option in payment_method_types"
                                                :key="option.id"
                                                :label="option.description"
                                                :value="option.id"
                                            ></el-option>
                                        </el-select>
                                        <small v-if="!row.payment_method_type_id" class="text-muted">No hay efectivo activo para la moneda del documento; seleccione un método.</small>
                                        <igtf-settings-status v-if="fiscalPaymentsApplicable" />
                                        <igtf-payment-fields v-if="fiscalPaymentsApplicable && !row.id"
                                            :payment="row" :payment-methods="payment_method_types"
                                            :document-currency="document.currency_type_id" :document-rate="document.exchange_rate_sale"
                                            :customer-id="document.customer_id" :igtf-enabled="igtfEnabled" :settings-version="igtfSettings.revision" />
                                        <receipt-currency-fields v-else-if="!row.id" :row="row" :methods="payment_method_types"
                                            :document-currency="document.currency_type_id" :document-rate="document.exchange_rate_sale" />
                                    </div>
                                </td>
                                <td>
                                    <div class="form-group mb-2 mr-2">
                                        <el-select
                                            v-model="row.payment_destination_id"
                                            @change="markDefaultReceiptEdited(row)"
                                            :disabled="classifiedPaymentDestinationDisabled(payment_destinations, row)"
                                            filterable
                                        >
                                            <el-option
                                                v-for="option in classifiedPaymentDestinations(payment_destinations, row)"
                                                :key="option.id"
                                                :label="option.description"
                                                :value="option.id"
                                            ></el-option>
                                        </el-select>
                                    </div>
                                </td>
                                <td>
                                    <div class="form-group mb-2 mr-2">
                                        <el-input v-model="row.reference" @input="markDefaultReceiptEdited(row)"></el-input>
                                    </div>
                                </td>
                                <td>
                                    <div class="form-group mb-2 mr-2">
                                        <el-input v-model="row.payment" @input="markDefaultReceiptEdited(row)"></el-input>
                                    </div>
                                </td>
                                <td class="series-table-actions text-center">
                                    <button
                                        class="btn waves-effect waves-light btn-xs btn-danger"
                                        type="button"
                                        @click.prevent="clickCancel(index)"
                                    >
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                                <br/>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
                <template v-if="document.payment_condition_id === '02'">
                    <div class="col-lg-12">
                        <table v-if="document.fee.length>0" width="100%">
                            <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Monto</th>
                                <th style="width: 30px">
                                    <a
                                        style="font-size:18px"
                                        href="#"
                                        @click.prevent="clickAddFee()"
                                        class="text-center font-weight-bold text-center text-info"
                                    >[+]</a>
                                </th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="(row, index) in document.fee" :key="index">
                                <td v-if="document.fee.length > 0">
                                    <div class="form-group mb-2 mr-2">
                                        <el-date-picker v-model="row.date" type="date"
                                                        value-format="yyyy-MM-dd"
                                                        format="dd/MM/yyyy"
                                                        :clearable="false"></el-date-picker>
                                    </div>
                                </td>
                                <td v-if="document.fee.length>0">
                                    <div class="form-group mb-2 mr-2">
                                        <el-input v-model="row.amount"></el-input>
                                    </div>
                                </td>
                                <td class="series-table-actions text-center">
                                    <button
                                        v-if="index > 0"
                                        type="button"
                                        class="btn waves-effect waves-light btn-xs btn-danger"
                                        @click.prevent="clickRemoveFee(index)"
                                    >
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            </tbody>
                        </table>

                    </div>
                </template>
            </div>

            <span slot="footer"
                  class="dialog-footer">
                <template v-if="showClose">
                <el-button @click="clickClose">Cerrar</el-button>
                <el-button
                    v-if="generate"
                    :loading="loading_submit"
                    class="submit"
                    type="primary"
                    @click="submit"
                >Generar</el-button
                >
                </template>
                <template v-else>
                <el-button
                    v-if="generate"
                    :loading="loading_submit"
                    class="submit"
                    plain
                    type="primary"
                    @click="submit"
                >Generar comprobante</el-button
                >
                <el-button v-else
                            @click="clickFinalize">Ir al listado</el-button>
                <el-button type="primary"
                            @click="clickNewOrderNote"
                >Nuevo pedido</el-button
                >
                </template>
            </span>
        </el-dialog>

        <document-options
            :isContingency="false"
            :recordId="documentNewId"
            :showClose="true"
            :showDialog.sync="showDialogDocumentOptions"
        ></document-options>

        <sale-note-options
            :recordId="documentNewId"
            :showClose="true"
            :showDialog.sync="showDialogSaleNoteOptions"
        ></sale-note-options>
    </div>
<!-- ######### FIN CAMBIO IGV A IVA -->
</template>

<script>
import { documentNumberFull } from "../../../helpers/document-number";

import DocumentOptions from "@views/documents/partials/options.vue";
import SaleNoteOptions from "@views/sale_notes/partials/options.vue";
import {calculateRowItem} from "../../../helpers/functions";
import {exchangeRate, functions} from "../../../mixins/functions";
import moment from "moment";
import {mapActions, mapState} from "vuex/dist/vuex.mjs";
import fiscalPaymentSettings from "@mixins/fiscal-payment-settings";
import IgtfPaymentFields from "@views/documents/partials/igtf_fields.vue";
import ReceiptCurrencyFields from "@components/ReceiptCurrencyFields.vue";
import {paymentConditionPayload, suggestInstallments, suggestRemainingPayment} from "../../../helpers/document-creation";
import {commercialTotals} from "../../../helpers/exact-commercial";
import {exactAmount, exactSum, normalizeExchangeRate} from "../../../helpers/exchange-rate-math";
import {defaultCashForCurrency, paymentValidationMessage} from "../../../helpers/payment-classification";

export default {
    components: {
        DocumentOptions,
        SaleNoteOptions,
        IgtfPaymentFields,
        ReceiptCurrencyFields
    },
    mixins: [
        exchangeRate, functions, fiscalPaymentSettings
    ],
    props: [
        "showDialog",
        "recordId",
        "showClose",
        "showGenerate",
        "type",
        "typeUser",
        "configuration",
    ],
    data() {
        return {
            customer_email: "",
            titleDialog: null,
            loading: false,
            resource: "dispatches",
            resource_documents: "documents",
            errors: {},
            form: {},
            document: {},
            document_types: [],
            all_document_types: [],
            all_series: [],
            series: [],
            customers: [],
            generate: false,
            loading_submit: false,
            showDialogDocumentOptions: false,
            showDialogSaleNoteOptions: false,
            documentNewId: null,
            is_document_type_invoice: true,
            loading_search: false,
            payment_destinations: [],
            payment_conditions: [],
            form_cash_document: {},
            payment_method_types: [],
            automaticReceipt: null,
            items: [],
            affectation_igv_types: [],
            affectation_igv_type: null,
            currencyTypeIdActive: "VES",
            exchangeRateSale: null,
            rateError: '',
            show_has_retention: true,
            currency_type: {},
        };
    },
    created() {
        this.initForm();
        this.initDocument();
    },
    watch: {
        'document.payments': {
            deep: true,
            handler() {
                if (this.document.payment_condition_id === '02') suggestInstallments(this.document);
            },
        },
    },
    methods: {
        documentNumberFull,
        clickRemoveFee(index) {
            this.document.fee.splice(index, 1)
            this.calculateFee()
        },
        clickAddFee() {

            this.document.fee.push({
                id: null,
                document_id: null,
                date: moment().format('YYYY-MM-DD'),
                currency_type_id: this.document.currency_type_id,
                amount: 0,
            });

            this.calculateFee()

        },
        calculateFee() {
            suggestInstallments(this.document)
        },
        calculatePayments() {
            suggestRemainingPayment(this.document, this.document.payments, {refreshSuggested: true});
            if (this.document.payment_condition_id === '02') this.calculateFee();
        },
        changePaymentCondition() {
            if(this.document.payment_condition_id === '01' && !this.document.payments.length) {
                this.clickAddPayment(true)
            }
            if (this.document.payment_condition_id === '02' && this.automaticReceipt) {
                const {row, payment, method, destination} = this.automaticReceipt;
                const sameAmount = (left, right) => {
                    try { return exactAmount(left).compare(right) === 0; } catch (_) { return false; }
                };
                const unchanged = String(row.payment) === payment && row.payment_method_type_id === method
                    && row.payment_destination_id === destination && !row.reference
                    && (!row.currency_type_id || row.currency_type_id === this.document.currency_type_id)
                    && (row.original_amount == null || sameAmount(row.original_amount, payment))
                    && !row.financial_intermediation && !['subject', 'exempt'].includes(row.igtf_status)
                    && (!row.tax_amount || sameAmount(row.tax_amount, 0))
                    && (!row.exchange_rate || sameAmount(row.exchange_rate, this.document.exchange_rate_sale));
                if (unchanged) this.document.payments = this.document.payments.filter(paymentRow => paymentRow !== row);
                this.automaticReceipt = null;
            }
            if(this.document.payment_condition_id === '02' && !this.document.fee.length) {
                this.clickAddFee()
            }
        },
        markDefaultReceiptEdited(row) {
            if (this.automaticReceipt && this.automaticReceipt.row === row) this.automaticReceipt = null;
        },
        clickCancel(index) {
            this.markDefaultReceiptEdited(this.document.payments[index]);
            this.document.payments.splice(index, 1);
            if (this.document.payment_condition_id === '02') this.calculateFee();
        },
        getPaymentDestinationId() {
            if (
                this.configuration.destination_sale &&
                this.payment_destinations.length > 0
            ) {
                let cash = _.find(this.payment_destinations, { id: "cash" });

                return cash ? cash.id : this.payment_destinations[0].id;
            }

            return null;
        },
        async clickAddPayment(automatic = false) {

            const method = defaultCashForCurrency(this.payment_method_types, this.document.currency_type_id);
            await this.document.payments.push({
                id: null,
                document_id: null,
                date_of_payment: moment().format("YYYY-MM-DD"),
                payment_method_type_id: method?.id || null,
                payment_destination_id: method && this.payment_destinations.some(destination => destination.id === 'cash') ? 'cash' : null,
                reference: null,
                payment: 0,
            });

            this.calculatePayments()
            if (automatic === true) {
                const row = this.document.payments[this.document.payments.length - 1];
                this.automaticReceipt = {row, payment: String(row.payment), method: row.payment_method_type_id, destination: row.payment_destination_id};
            }

        },
        initForm() {
            this.generate = this.showGenerate ? true : false;
            this.errors = {};
            this.form = {
                id: null,
                external_id: null,
                identifier: null,
                date_of_issue: null,
            };

            this.form_cash_document = {
                document_id: null,
                sale_note_id: null,
            };
        },
        async getCustomer() {
            await this.$http
                .get(
                    `/${this.resource_documents}/search/customer/${this.form.dispatch.customer_id}`
                )
                .then((response) => {
                    this.customers = response.data.customers;
                    this.document.customer_id = this.form.dispatch.customer_id;
                    this.changeCustomer();
                });
        },
        async changeCustomer() {
            await this.validateIdentityDocumentType();
            // retencion para clientes con ruc
            this.validateCustomerRetention(this.form.dispatch.customer.identity_document_type_id)
        },
        validateCustomerRetention(identity_document_type_id) {

            if (identity_document_type_id != '6') {

                if (this.document.has_retention) {
                    this.document.has_retention = false
                    this.changeRetention()
                }

                this.show_has_retention = false

            } else {
                this.show_has_retention = true
            }

        },
        changeRetention() {

            if (this.document.has_retention) {

                let base = this.document.total
                let percentage = _.round(parseFloat(this.configuration.igv_retention_percentage) / 100, 5)
                let amount = _.round(base * percentage, 2)

                this.document.retention = {
                    base: base,
                    // ########## INICIO CAMBIO IGV A IVA
                    code: '62', //Código de Retención del IVA
                    // ######### FIN CAMBIO IGV A IVA
                    amount: amount,
                    percentage: percentage
                }

                this.setTotalPendingAmountRetention(amount)

            } else {

                this.document.retention = {}
                this.document.total_pending_payment = 0
                this.calculateAmountToPayments()
            }

        },
        calculateAmountToPayments() {
            this.calculatePayments()
            this.calculateFee()
        },
        setTotalPendingAmountRetention(amount) {

            //monto neto pendiente aplica si la condicion de pago es credito
            this.form.total_pending_payment = ['02', '03'].includes(this.form.payment_condition_id) ? this.form.total - amount : 0
            this.calculateAmountToPayments()

        },
        searchRemoteCustomers(input) {
            if (input.length > 0) {
                this.loading_search = true;
                let parameters = `input=${input}&document_type_id=${this.form.document_type_id}&operation_type_id=${this.form.operation_type_id}`;

                this.$http
                    .get(`/${this.resource}/search/customers?${parameters}`)
                    .then((response) => {
                        this.customers = response.data.customers;
                        this.loading_search = false;
                    });
            }
        },
        initDocument() {
            this.automaticReceipt = null;
            this.document = {
                document_type_id: null,
                series_id: null,
                establishment_id: null,
                number: "#",
                date_of_issue: moment().format("YYYY-MM-DD"),
                time_of_issue: null,
                customer_id: null,
                currency_type_id: "VES",
                purchase_order: null,
                exchange_rate_sale: 0,
                total_prepayment: 0,
                total_charge: 0,
                total_discount: 0,
                total_exportation: 0,
                total_free: 0,
                total_taxed: 0,
                total_unaffected: 0,
                total_exonerated: 0,
                total_igv: 0,


                total_base_other_taxes: 0,
                total_other_taxes: 0,
                total_taxes: 0,
                total_value: 0,
                total: 0,
                operation_type_id: null,
                date_of_due: moment().format("YYYY-MM-DD"),
                delivery_date: moment().format("YYYY-MM-DD"),
                items: [],
                charges: [],
                discounts: [],
                attributes: [],
                guides: [],
                additional_information: null,
                actions: {
                    format_pdf: "a4",
                },
                dispatch_id: null,
                dispatch: null,
                is_receivable: false,
                payments: [],
                hotel: {},
                fee: [],
                payment_condition_id: '01',
                has_retention: false,
                retention: {},
            };

            this.prepareDataRetention()
            this.currency_type = _.find(this.configuration.currency_types, {'id': this.document.currency_type_id})
        },
        prepareDataRetention() {

            this.form.has_retention = !_.isEmpty(this.form.retention)

            if (this.form.has_retention) {
                this.setTotalPendingAmountRetention(this.form.retention.amount)
            }

        },
        changeDateOfIssue() {
            this.document.date_of_due = this.document.date_of_issue;
        },
        resetDocument() {
            this.generate = this.showGenerate ? true : false;
            this.initDocument();
            this.document.document_type_id =
                this.document_types.length > 0 ? this.document_types[0].id : null;
            this.changeDocumentType();
        },
        async validatePaymentDestination() {
            let error_by_item = 0;

            await this.document.payments.forEach((item) => {
                if (item.payment_destination_id == null) error_by_item++;
            });

            return {
                error_by_item: error_by_item,
            };
        },
        onCalculateTotals() {
            Object.assign(this.document, commercialTotals(this.document));
            if (this.document.payment_condition_id === '02') suggestInstallments(this.document);
        },
        setTotalDefaultPayment() {
            if (this.document.payments.length > 0) {
                this.document.payments[0].payment = this.document.total;
            }
        },
        async submit() {
            try {
                this.document.exchange_rate_sale = normalizeExchangeRate(this.document.exchange_rate_sale);
            } catch (_) {
                return this.$message.error('No hay una tasa documental válida. Cierre el diálogo y vuelva a intentarlo.');
            }
            this.assignDocument();
            this.onCalculateTotals();

            let validate_payment_destination =
                await this.validatePaymentDestination();

            if (validate_payment_destination.error_by_item > 0) {
                return this.$message.error("El destino del pago es obligatorio");
            }

            if (!await this.ensureFiscalPaymentsReady(this.document.payments)) return;

            this.loading_submit = true;
            if (this.document.document_type_id === "80") {
                this.document.prefix = "NV";
                this.resource_documents = "sale-notes";
            } else {
                this.document.prefix = null;
                this.resource_documents = "documents";
            }

            this.$http
                .post(`/${this.resource_documents}`, paymentConditionPayload(this.document))
                .then((response) => {
                    if (response.data.success) {
                        this.documentNewId = response.data.data.id;
                        if (this.document.document_type_id === "80") {
                            this.form_cash_document.sale_note_id = response.data.data.id;
                            this.showDialogSaleNoteOptions = true;
                        } else {
                            this.form_cash_document.document_id = response.data.data.id;
                            this.showDialogDocumentOptions = true;
                        }
                        this.saveCashDocument();

                        this.$eventHub.$emit("reloadData");
                        // this.onUpdateDispatchWithDocumentId(response.data.data.id);
                        this.resetDocument();
                        this.document.customer_id = this.form.dispatch.customer_id;
                        this.changeCustomer();
                        this.clickClose();

                    } else {
                        this.$message.error(response.data.message);
                    }
                })
                .catch((error) => {
                    if (error.response && error.response.status === 422) {
                        this.errors = error.response.data.errors || error.response.data;
                        const message = paymentValidationMessage(error.response.data)
                            || Object.values(this.errors).flat().find(value => typeof value === 'string' && value.trim())
                            || 'Revise los datos del comprobante.';
                        this.$message.error(message);
                    } else {
                        this.$message.error(error.response?.data?.message || "No se pudo guardar el comprobante.");
                    }
                })
                .then(() => {
                    this.loading_submit = false;
                });
        },
        onUpdateDispatchWithDocumentId(documentId) {
            this.loading_submit = true;
            const payload = {
                document_id: documentId,
            };
            this.$http
                .post(`dispatches/record/${this.recordId}/set-document-id`, payload)
                .finally(() => (this.loading_submit = true));
        },
        saveCashDocument() {
            this.$http
                .post(`/cash/cash_document`, this.form_cash_document)
                .then((response) => {
                    if (!response.data.success) {
                        this.$message.error(response.data.message);
                    }
                })
                .catch((error) => {
                    this.axiosError(error);
                });
        },
        onGetItems(item) {
            const affectationId = item.sale_affectation_igv_type_id || '10';
            const affectation = (this.affectation_igv_types || []).find(row => row.id === affectationId) || this.affectation_igv_type;
            const it = {
                IdLoteSelected: null,
                affectation_igv_type: affectation,
                affectation_igv_type_id: affectationId,
                attributes: [],
                charges: [],
                discounts: [],
                document_item_id: null,
                has_igv: item.has_igv,


                input_unit_price_value: item.sale_unit_price,
                price_input: {amount: String(item.sale_unit_price), currency_type_id: item.currency_type_id, includes_iva: !!item.has_igv},
                item: item,
                item_id: item.id,
                item_unit_type_id: null,
                item_unit_types: [],
                lots_group: [],

                quantity: item.quantity,
                suggested_price: 0,

                unit_price: item.sale_unit_price,
                unit_price_value: item.sale_unit_price,
                warehouse_id: null,
            };
            return calculateRowItem(
                it,
                this.currencyTypeIdActive,
                this.exchangeRateSale,
                this.percentage_igv
            );
        },
        assignDocument() {
            let q = this.form.dispatch;
            this.document.establishment_id = q.establishment_id;
            this.document.time_of_issue = moment().format("HH:mm:ss");
            // this.document.purchase_order = null;
            this.document.total_prepayment = q.total_prepayment;
            this.document.total_charge = q.total_charge;
            this.document.total_discount = q.total_discount;
            this.document.total_exportation = q.total_exportation;
            this.document.total_free = q.total_free;
            this.document.total_taxed = q.total_taxed;
            this.document.total_unaffected = q.total_unaffected;
            this.document.total_exonerated = q.total_exonerated;
            this.document.total_igv = q.total_igv;


            this.document.total_base_other_taxes = q.total_base_other_taxes;
            this.document.total_other_taxes = q.total_other_taxes;
            this.document.total_taxes = q.total_taxes;
            this.document.total_value = q.total_value;
            this.document.total = q.total;
            this.document.operation_type_id = "0101";
            this.document.items = q.items;
            this.document.charges = q.charges;
            this.document.discounts = q.discounts;
            this.document.attributes = [];
            this.document.guides = q.guides;
            this.document.additional_information = null;
            this.document.actions = {
                format_pdf: "a4",
            };
            this.document.dispatch_id = this.form.dispatch.id;
            this.document.items = this.items;
        },
        async create() {
            const response = await this.$http.get(
                `/${this.resource}/record/${this.recordId}/tables`
            );
            const data = response.data;
            this.all_document_types = await data.document_types_invoice;
            this.all_series = await data.series;
            this.payment_destinations = await data.payment_destinations;
            this.payment_method_types = await data.payment_method_types;
            this.affectation_igv_types = await data.affectation_igv_types;
            this.affectation_igv_type = await data.affectation_igv_types
                .filter((a) => a.id == "10")
                .reduce((a) => a);
            this.form.dispatch = await data.dispatch;
            this.form.establishment_id = this.form.dispatch.establishment_id;
            this.form.date_of_issue = this.form.dispatch.date_of_issue;

            await this.getPercentageIgv();
            this.payment_conditions = await data.payment_conditions;

            const items = await data.items.map((i) => {

                const quantity = exactSum(this.form.dispatch.items.filter((ite)=>ite.item_id==i.id).map(line=>line.quantity)).decimal();

                i.quantity = quantity;
                i.unit_price = i.sale_unit_price;
                // i.unit_price = i.sale_unit_price;

                return i;
            });

            await this.getCustomer();
            await this.validateIdentityDocumentType();
            this.items = [];
            this.document.items = [];
            this.rateError = '';
            try {
                const rate = normalizeExchangeRate(await this.searchExchangeRateByDate(this.document.date_of_issue));
                this.document.exchange_rate_sale = rate;
                this.exchangeRateSale = rate;
            } catch (_) {
                this.document.exchange_rate_sale = null;
                this.exchangeRateSale = null;
                this.rateError = 'No se pudo obtener una tasa documental válida. Cierre el diálogo y vuelva a intentarlo.';
                this.$message.error(this.rateError);
                return;
            }
            this.items = items.map((item) => this.onGetItems(item));
            this.document.items = this.items;
            this.titleDialog = `Orden de entrega ${documentNumberFull(this.form.dispatch.series, this.form.dispatch.number)}: Crear comprobante`;

            await this.onCalculateTotals();

            await this.clickAddPayment(true);
        },
        changeDocumentType() {
            // this.filterSeries()
            this.document.is_receivable = false;
            this.series = [];
            if (this.document.document_type_id !== "nv") {
                this.filterSeries();
                this.is_document_type_invoice = true;
            } else {
                this.is_document_type_invoice = false;
            }
        },
        async validateIdentityDocumentType() {
            let customer = _.find(this.customers, {id: this.document.customer_id});
            if (!customer) {
                return;
            }
            // ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
            this.document_types = _.filter(this.all_document_types, {id: "01"});
            // ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA

            this.document.document_type_id =
                this.document_types.length > 0 ? this.document_types[0].id : null;
            await this.changeDocumentType();
        },
        filterSeries() {
            this.document.series_id = null;
            this.series = _.filter(this.all_series, {
                document_type_id: this.document.document_type_id,
            });
            this.document.series_id =
                this.series.length > 0 ? this.series[0].id : null;
        },
        clickFinalize() {
            location.href = `/${this.resource}`;
        },
        clickNewOrderNote() {
            this.clickClose();
        },
        clickClose() {
            this.$emit("update:showDialog", false);
            this.initForm();
            this.resetDocument();
        },
        clickToPrint(format) {
            window.open(
                `/${this.resource}/print/${this.form.external_id}/${format}`,
                "_blank"
            );
        },
        clickSendEmail() {
            this.loading = true;
            this.$http
                .post(`/${this.resource}/email`, {
                    customer_email: this.customer_email,
                    id: this.form.id,
                    customer_id: this.form.dispatch.customer_id,
                })
                .then((response) => {
                    if (response.data.success) {
                        this.$message.success("El correo fue enviado satisfactoriamente");
                    } else {
                        this.$message.error("Error al enviar el correo");
                    }
                })
                .catch((error) => {
                    this.$message.error("Error al enviar el correo");
                })
                .then(() => {
                    this.loading = false;
                });
        },
    },
};
</script>

<!-- ######## FIN MIGRACIÓN MONEDA VENEZUELA ######## -->
