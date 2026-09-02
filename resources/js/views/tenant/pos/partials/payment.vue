<template>
    <div v-loading="loading_submit"
         class="pos-payment pos-checkout row col-lg-12 m-0 p-0">
        <Keypress :key-code="113"
                  key-event="keyup"
                  @success="handleFn113"/>

        <!-- ============ Resumen de la venta ============ -->
        <aside class="col-lg-4 col-md-6 pos-checkout__summary">

            <header class="pos-checkout__summary-header">
                <span class="pos-checkout__summary-title">Resumen de la venta</span>
                <span class="pos-checkout__summary-count">
                    {{ form.items.length }} {{ form.items.length === 1 ? 'producto' : 'productos' }}
                </span>
            </header>

            <div class="pos-checkout__customer">
                <span class="pos-checkout__label">Cliente</span>
                <b class="pos-checkout__customer-name">{{ customer.description }}</b>

                <!-- sistema por puntos -->
                <div v-if="enabledPointSystem" class="pos-checkout__points">
                    <p class="pos-checkout__point-row">
                        Puntos acumulados: <span>{{ customer_accumulated_points }}</span>
                        <template v-if="total_exchange_points > 0">
                            - <b class="text-danger">{{ total_exchange_points }}</b>
                            = <b>{{ calculate_customer_accumulated_points }}</b>
                        </template>
                    </p>
                    <p class="pos-checkout__point-row pos-checkout__point-row--success">
                        Puntos por la compra: <span>{{ total_points_by_sale }}</span>
                    </p>
                </div>
                <!-- sistema por puntos -->
            </div>

            <div class="pos-checkout__items">
                <div v-for="(item, index) in form.items"
                     :key="index"
                     class="pos-checkout__item">

                    <span class="pos-checkout__item-qty">{{ item.quantity }}</span>

                    <div class="pos-checkout__item-body">
                        <p class="pos-checkout__item-name">{{ item.item.description }}</p>

                        <!-- sistema por puntos -->
                        <el-checkbox v-if="isAvailablePointSystem(item)"
                                     v-model="item.item.exchanged_for_points"
                                     class="pos-checkout__item-flag"
                                     @change="changeRowExchangePoints(item, index)">
                            <b>{{ getExchangePointDescription(item) }}</b>
                        </el-checkbox>
                        <!-- sistema por puntos -->

                        <!-- restriccion venta productos -->
                        <span v-if="isRestrictedForSale(item.item)"
                              class="pos-checkout__item-alert">
                            Restringido para venta en CPE
                        </span>
                        <!-- restriccion venta productos -->
                    </div>

                    <span class="pos-checkout__item-total">
                        {{ currencyTypeActive.symbol }} {{ money(item.total) }}
                    </span>
                </div>
            </div>

            <footer class="pos-checkout__summary-footer">
                <div class="pos-checkout__totals">
                    <div class="pos-checkout__total-row">
                        <span>Subtotal</span>
                        <span>{{ currencyTypeActive.symbol }} {{ money(form.total_taxed) }}</span>
                    </div>
                    <div class="pos-checkout__total-row" v-if="!isNrus">
                        <!-- ########## INICIO CAMBIO IGV A IVA -->
                        <span>IVA</span>
                        <!-- ######### FIN CAMBIO IGV A IVA -->
                        <span>{{ currencyTypeActive.symbol }} {{ money(form.total_igv) }}</span>
                    </div>
                    <!-- ########## INICIO SIN DETRACCIONES E ISC -->
                    <!-- ISC e impuesto a bolsas se conservan en datos históricos, sin presentación activa. -->
                    <!-- ######### FIN SIN DETRACCIONES E ISC -->
                    <div class="pos-checkout__total-row" v-if="form.total_discount > 0">
                        <span>Descuento</span>
                        <span>- {{ currencyTypeActive.symbol }} {{ money(form.total_discount) }}</span>
                    </div>

                    <template v-if="showRetentionSummary">
                        <div class="pos-checkout__total-row">
                            <span>Importe total</span>
                            <span>{{ currencyTypeActive.symbol }} {{ money(form.total) }}</span>
                        </div>
                        <div class="pos-checkout__total-row">
                            <span>M. retención</span>
                            <span>{{ currencyTypeActive.symbol }} {{ money(form.retention.amount) }}</span>
                        </div>
                    </template>
                </div>

                <div class="pos-checkout__grand-total">
                    <span>{{ showRetentionSummary ? 'TOTAL A PAGAR' : 'TOTAL' }}</span>
                    <span>{{ currencyTypeActive.symbol }} {{ money(getTotal()) }}</span>
                </div>

                <button :disabled="button_payment && payment_method_type_id != '09'"
                        class="pos-checkout__confirm"
                        type="button"
                        @click="clickPayment">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 8v-3a1 1 0 0 0 -1 -1h-10a2 2 0 0 0 0 4h12a1 1 0 0 1 1 1v3m0 4v3a1 1 0 0 1 -1 1h-12a2 2 0 0 1 -2 -2v-12" /><path d="M20 12v4h-4a2 2 0 0 1 0 -4h4" /></svg>
                    <span>CONFIRMAR PAGO</span>
                </button>

                <p v-if="button_payment && payment_method_type_id != '09'"
                   class="pos-checkout__confirm-hint">
                    Falta cubrir {{ currencyTypeActive.symbol }} {{ differenceText }} para completar el pago.
                </p>

                <button class="pos-checkout__cancel"
                        type="button"
                        @click="clickCancel">Cancelar compra
                </button>
            </footer>
        </aside>

        <!-- ============ Formulario de cobro ============ -->
        <div class="col-lg-8 col-md-6 pos-checkout__main">
            <div class="pos-checkout__form">

                <!-- Comprobante -->
                <section class="pos-card pos-card--voucher">
                    <button class="pos-checkout__back"
                            type="button"
                            @click="back">
                        <i class="fas fa-angle-left"></i> Volver al carrito
                    </button>

                    <div class="pos-card__voucher-fields">
                        <div class="pos-field">
                            <el-radio-group v-model="form.document_type_id"
                                            class="pos-doctype"
                                            size="small"
                                            @change="filterSeries">
                                <el-radio-button v-if="!isNrus" label="01">FACTURA</el-radio-button>
                                <!-- ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA -->
                                <el-radio-button label="80">N. VENTA</el-radio-button>
                                <!-- ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA -->
                            </el-radio-group>
                        </div>

                        <div class="pos-field pos-field--series">
                            <el-select v-model="form.series_id" placeholder="Serie">
                                <el-option v-for="option in series"
                                           :key="option.id"
                                           :label="option.number"
                                           :value="option.id">
                                </el-option>
                            </el-select>
                        </div>
                    </div>
                </section>

                <!-- Monto a cobrar -->
                <section class="pos-card pos-card--amount">
                    <div class="pos-amount">
                        <span class="pos-amount__label">Monto a cobrar</span>
                        <span class="pos-amount__value">
                            {{ currencyTypeActive.symbol }} {{ money(getTotal()) }}
                        </span>
                    </div>

                    <div class="pos-amount__grid">
                        <div class="pos-field">
                            <label class="pos-field__label">Con cuánto paga el cliente</label>
                            <div class="pos-money-input">
                                <span class="pos-money-input__symbol">{{ currencyTypeActive.symbol }}</span>
                                <el-input ref="enter_amount"
                                          inputmode="decimal"
                                          v-model="enter_amount"
                                          @input="enterAmount()"
                                          @focus="valueInputSelect"
                                          @click.native="valueInputSelect"
                                          @keyup.enter.native="keyupEnterAmount()">
                                </el-input>
                            </div>

                            <div v-if="form_payment.payment_method_type_id=='01'"
                                 class="pos-quick-cash">
                                <button type="button"
                                        class="pos-quick-cash__btn pos-quick-cash__btn--exact"
                                        @click="setExactAmount()">Importe exacto
                                </button>
                                <!-- ########## INICIO CAMBIO QUITAR SELECCIÓN DE BILLETES -->
                                <!-- El importe se captura manualmente; no se muestran denominaciones rápidas. -->
                                <!-- ######### FIN CAMBIO QUITAR SELECCIÓN DE BILLETES -->
                            </div>
                        </div>

                        <div class="pos-change"
                             :class="isMissingAmount ? 'is-missing' : 'is-change'">
                            <span class="pos-change__label"
                                  v-text="isMissingAmount ? 'Faltante' : 'Vuelto'"></span>
                            <span class="pos-change__value">
                                {{ currencyTypeActive.symbol }} {{ differenceText }}
                            </span>
                        </div>
                    </div>
                </section>

                <!-- Descuento y propina -->
                <div class="pos-card-row"
                     v-if="(!disabledDiscountForSeller && enableGlobalDiscount) || enabledTipsPos">

                    <!-- Descuento -->
                    <section class="pos-card pos-card--compact" v-if="!disabledDiscountForSeller && enableGlobalDiscount">
                        <div class="pos-card__head">
                            <h5 class="pos-card__title">Descuento</h5>
                            <el-switch v-model="enabled_discount"
                                       @change="changeEnabledDiscount"></el-switch>
                        </div>

                        <div v-if="enabled_discount" class="pos-card__body">
                            <div class="pos-money-input pos-money-input--sm">
                                <span class="pos-money-input__symbol">
                                    {{ is_discount_amount ? currencyTypeActive.symbol : '%' }}
                                </span>
                                <el-input v-model="discount_amount"
                                          inputmode="decimal"
                                          :disabled="!enabled_discount"
                                          @focus="valueInputSelect"
                                          @click.native="valueInputSelect"
                                          @change="inputDiscountAmount()">
                                </el-input>
                            </div>

                            <div class="pos-card__foot">
                                <el-checkbox v-model="is_discount_amount"
                                             @change="changeTypeDiscount">
                                    Aplicar como monto
                                </el-checkbox>
                                <el-tooltip class="item"
                                            v-if="global_discount_type && global_discount_type.description"
                                            :content="global_discount_type.description"
                                            effect="dark"
                                            placement="top">
                                    <i class="fa fa-info-circle"></i>
                                </el-tooltip>
                            </div>
                        </div>
                        <p v-else class="pos-card__hint">Descuento global sobre el total.</p>
                    </section>

                    <!-- Propinas -->
                    <section class="pos-card pos-card--compact" v-if="enabledTipsPos">
                        <div class="pos-card__head">
                            <h5 class="pos-card__title">
                                Propina
                                <el-tooltip class="item"
                                            content="No se incluye en el comprobante ni en el importe a cobrar: sólo queda registrada para el reporte de propinas del empleado. Debe indicar el empleado y un monto mayor a 0."
                                            effect="dark"
                                            placement="top">
                                    <i class="fa fa-info-circle"></i>
                                </el-tooltip>
                            </h5>
                        </div>

                        <div class="pos-card__body pos-tip">
                            <el-input v-model="form.worker_full_name_tips"
                                      placeholder="Empleado"></el-input>
                            <div class="pos-money-input pos-money-input--sm pos-tip__amount">
                                <span class="pos-money-input__symbol">{{ currencyTypeActive.symbol }}</span>
                                <el-input v-model="form.total_tips"
                                          inputmode="decimal"
                                          @focus="valueInputSelect"
                                          @click.native="valueInputSelect"
                                          @input="sanitizeTipAmount"></el-input>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Pagos agregados -->
                <section class="pos-card">
                    <div class="pos-card__head">
                        <div>
                            <h5 class="pos-card__title">Formas de pago</h5>
                            <p class="pos-card__hint">Divide el cobro en uno o varios métodos de pago.</p>
                        </div>
                        <button type="button"
                                class="pos-btn-outline"
                                @click="clickAddPayment()">
                            <i class="fas fa-plus"></i> Agregar
                        </button>
                    </div>

                    <ul class="pos-payments">
                        <li v-for="(pay, index) in form.payments"
                            :key="index"
                            class="pos-payments__row">
                            <span class="pos-payments__idx">{{ index + 1 }}</span>
                            <span class="pos-payments__method">
                                {{ getDescriptionPaymentMethodType(pay.payment_method_type_id) }}
                            </span>
                            <span class="pos-payments__amount">
                                {{ currencyTypeActive.symbol }} {{ money(pay.payment) }}
                            </span>
                        </li>
                        <li v-if="form.payments.length === 0" class="pos-payments__empty">
                            Aún no se ha registrado ninguna forma de pago.
                        </li>
                    </ul>
                </section>

                <!-- Datos adicionales -->
                <section class="pos-card">
                    <div class="pos-card__head">
                        <div>
                            <h5 class="pos-card__title">Datos adicionales</h5>
                            <p class="pos-card__hint">Información opcional que se imprime en el comprobante.</p>
                        </div>
                    </div>

                    <div class="pos-card__body">
                        <div class="pos-field mb-2" v-if="configuration.enabled_sales_agents">
                            <search-agent @changeAgent="changeAgent"></search-agent>
                        </div>

                        <div :class="{ 'pos-card__body--grid': businessTurns.active }">
                            <div class="pos-field">
                                <label class="pos-field__label">Datos de referencia</label>
                                <el-input v-model="form.reference_data" type="textarea"></el-input>
                            </div>

                            <div class="pos-field pos-field--narrow" v-if="businessTurns.active">
                                <label class="pos-field__label">N° Placa</label>
                                <el-input v-model="form.plate_number" type="text"></el-input>
                            </div>
                        </div>
                    </div>
                </section>

            </div>
        </div>

        <options-form
            :recordId="documentNewId"
            :resource="resource_options"
            :showDialog.sync="showDialogOptions"
            :statusDocument="statusDocument"
            :fromPos="true"
            :isPrint="isPrint"
        ></options-form>

        <multiple-payment-form
            :payments="payments"
            :showDialog.sync="showDialogMultiplePayment"
            :total="getTotal()"
            @add="addRow"
            @setPaymentMethod="setPaymentMethod"

        ></multiple-payment-form>

        <card-brands-form :external="true"
                          :recordId="null"
                          :showDialog.sync="showDialogNewCardBrand"></card-brands-form>

        <discount-permission-form
                    :showDialog.sync="showDialogDiscountPermission"
                    :totalDiscountPercentage ="totalDiscountPercentage"
                    :sellers-discount-limit="configuration.sellers_discount_limit"
                    @tokenValidated="tokenValidated"></discount-permission-form>
    </div>
</template>

<script>
import Keypress from 'vue-keypress'

import CardBrandsForm from '../../card_brands/form.vue'
import SaleNotesOptions from '../../sale_notes/partials/options.vue'
import OptionsForm from './options.vue'
import MultiplePaymentForm from './multiple_payment.vue'
import {pointSystemFunctions} from '@mixins/functions'
import { buhoprinter } from '@mixins/buhoprinter'
import {calculateRowItem} from "@helpers/functions"
import DiscountPermissionForm from './discount_permission.vue'
import SearchAgent from '@components/SearchAgent.vue'


export default {
    components: {OptionsForm, CardBrandsForm, SaleNotesOptions, MultiplePaymentForm, Keypress, DiscountPermissionForm, SearchAgent},
    mixins: [pointSystemFunctions, buhoprinter],

    props: [
        'form',
        'customer',
        'currencyTypeActive',
        'exchangeRateSale',
        'is_payment',
        'soapCompany',
        'businessTurns',
        'isPrint',
        'globalDiscountTypeId',
        'enabledTipsPos',
        'hidePdfViewDocuments',
        'enabledPointSystem',
        'affectationIgvTypes',
        'percentageIgv',
        'configuration',
        'typeUser',
        'authUser',
        'customer_email',
        'config'
    ],

    data() {
        return {
            enabled_discount: false,
            discount_amount: 0,
            loading_submit: false,
            showDialogOptions: false,
            showDialogMultiplePayment: false,
            showDialogSaleNote: false,
            showDialogNewCardBrand: false,
            documentNewId: null,
            saleNotesNewId: null,
            resource_options: null,
            has_card: false,
            resource: 'pos',
            resource_documents: 'documents',
            resource_payments: 'document_payments',
            amount: 0,
            enter_amount: 0,
            difference: 0,
            button_payment: false,
            input_item: '',
            form_payment: {},
            responseForm: {},
            series: [],
            all_series: [],
            cards_brand: [],
            cancel: false,
            form_cash_document: {},
            statusDocument: {},
            payment_method_types: [],
            payments: [],
            locked_submit: false,
            global_discount_types: [],
            global_discount_type: {},
            error_global_discount: false,
            is_discount_amount: false,
            payment_method_type_id: null,
            showDialogDiscountPermission: false,
            totalDiscountPercentage: 0,
        }
    },
    async created() {

        await this.initLStoPayment()
        await this.getTables()
        this.initFormPayment()
        this.inputAmount()
        this.form.payments = []
        this.$eventHub.$on('reloadDataCardBrands', (card_brand_id) => {
            this.reloadDataCardBrands(card_brand_id)
        })

        this.$eventHub.$on('localSPayments', (payments) => {
            this.payments = payments

        })

        await this.setInitialAmount()

        // La conexión directa con BuhoPrinter ya no es necesaria desde el frontend.
        // La impresión se centraliza vía PrintOrder → Redis → BuhoPrinter agent.
        // if (!this.isBuhoActive && this.isPrint) {
        //     this.startConnectionBuho();
        // }

        if(this.enabledPointSystem)
        {
            await this.setCustomerAccumulatedPoints(this.form.customer_id, true)
            this.setTotalExchangePoints()
            this.checkUsedPointsByItem()
        }
        await this.getFormPosLocalStorage()


        this.setTotalPointsBySale(this.configuration)


    },
    mounted() {
        // console.log(this.currencyTypeActive)
    },
    computed: {
        isNrus: function () {
            return !!(this.config && this.config.is_nrus)
        },
        isGlobalDiscountBase: function () {
            return (this.globalDiscountTypeId === '02')
        },
        isInvoiceDocument()
        {
            return ['01', '03'].includes(this.form.document_type_id)
        },
        applyRestrictSaleItemsCpe()
        {
            if (this.configuration) return this.configuration.restrict_sale_items_cpe

            return false
        },
        // ########### INICIO CAMBIO TURNOS POS VENEZUELA
        isBusinessTurnActive()
        {
            return Boolean(this.businessTurns && this.businessTurns.active)
        },
        // ########### FIN CAMBIO TURNOS POS VENEZUELA
        disabledDiscountForSeller()
        {
            return this.configuration.restrict_seller_discount && this.typeUser === 'seller';
        },
        enableGlobalDiscount()
        {
            return !!(this.configuration && this.configuration.enable_global_discount);
        },
        /**
         * El resumen muestra el bloque de retención (importe total + retención).
         */
        showRetentionSummary()
        {
            return !!(this.form.has_retention && this.form.total > 700 && this.form.retention)
        },
        /**
         * true cuando lo entregado por el cliente no cubre el total.
         */
        isMissingAmount()
        {
            const difference = parseFloat(this.difference)
            return !isNaN(difference) && difference < 0
        },
        /**
         * Vuelto/faltante siempre en positivo y con 2 decimales: la etiqueta
         * indica el signo.
         */
        differenceText()
        {
            const difference = parseFloat(this.difference)
            if (isNaN(difference)) return '0.00'

            return Math.abs(difference).toFixed(2)
        },
    },
    methods:
    {
        setDefaultDocumentType(from_function) {
            this.default_series_type = this.authUser.serie;
            this.default_document_type = this.authUser.document_id;
            // if (this.default_document_type === undefined) this.default_document_type = null;
            // if (this.default_series_type === undefined) this.default_series_type = null;

            if (this.default_document_type !== null) {
                this.form.document_type_id = this.default_document_type;
                // ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
                if (this.isNrus && this.form.document_type_id === '01') {
                    this.form.document_type_id = '80';
                }
                // ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
                this.filterSeries()
                let alt = _.find(this.all_series, { id: this.default_series_type });

                if (this.default_series_type !== null && alt !== undefined) {
                    this.form.series_id = this.default_series_type;
                }
            }
        },
        isRestrictedForSale(item)
        {
            return this.applyRestrictSaleItemsCpe && this.isInvoiceDocument && (item != undefined && item.restrict_sale_cpe)
        },
        changeAgent(agent_id)
        {
            this.form.agent_id = agent_id
        },
        // ########### INICIO CAMBIO DOCUMENTOS POS VENEZUELA
        selectDocumentType(documentTypeId)
        {
            this.form.document_type_id = documentTypeId
            this.filterSeries()
        },
        // ########### FIN CAMBIO DOCUMENTOS POS VENEZUELA
        checkUsedPointsByItem()
        {
            this.form.items.forEach(row => {
                this.recalculateUsedPointsForExchange(row)
            })
        },
        isAvailablePointSystem(row)
        {
            return (this.enabledPointSystem && this.customer_accumulated_points > 0 && row.item.exchange_points)
        },
        changeRowExchangePoints(row, index)
        {
            row.item.used_points_for_exchange = row.item.exchanged_for_points ? this.getUsedPoints(row) : null
            this.setTotalExchangePoints()
            this.changeRowFreeAffectationIgv(row, index)
        },
        async changeRowFreeAffectationIgv(row, index)
        {
            this.form.items[index].affectation_igv_type_id = (row.item.exchanged_for_points) ? '15' : this.form.items[index].item.original_affectation_igv_type_id
            this.form.items[index].affectation_igv_type = await _.find(this.affectationIgvTypes, {id: this.form.items[index].affectation_igv_type_id})

            let new_row = await calculateRowItem(row, this.form.currency_type_id, this.form.exchange_rate_sale, this.percentageIgv)
            new_row['unit_type_id'] = row.unit_type_id

            this.form.items[index] = new_row
            await this.reCalculateTotal()
        },
        handleFn113() {
            const code = this.form.document_type_id
            // ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
            if (this.isNrus) {
                this.form.document_type_id = '80'
                this.filterSeries()
                return
            }
            this.form.document_type_id = code == '01' ? '80' : '01'
            // ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA

            this.filterSeries()
        },
        keyupEnterAmount() {

            if (this.button_payment) {
                return this.$message.warning("El monto a pagar es menor al total")
            }

            if (this.locked_submit) return;

            this.clickPayment()

        },
        /**
         * Formatea un importe para mostrarlo siempre con 2 decimales.
         */
        money(value) {
            let amount = parseFloat(value)
            if (isNaN(amount)) amount = 0

            return amount.toFixed(2)
        },
        /**
         * La propina sólo admite un importe numérico.
         */
        sanitizeTipAmount() {
            const raw = (this.form.total_tips === null || this.form.total_tips === undefined)
                ? ''
                : String(this.form.total_tips)

            let clean = raw.replace(/[^\d.]/g, '')
            const parts = clean.split('.')

            if (parts.length > 2) {
                clean = parts.shift() + '.' + parts.join('')
            }

            if (clean !== raw) this.form.total_tips = clean
        },
        /**
         * Coloca en el campo de cobro el importe exacto de la venta.
         */
        setExactAmount() {
            this.enter_amount = this.getTotal()
            this.enterAmount()
        },
        async setInitialAmount() {
            this.enter_amount = this.getTotal()
            // this.form.payments = this.payments
            // this.$eventHub.$emit('eventSetFormPosLocalStorage', this.form)
            await this.$refs.enter_amount.$el.getElementsByTagName('input')[0].focus()
            await this.$refs.enter_amount.$el.getElementsByTagName('input')[0].select()
            // console.log(this.$refs.enter_amount.$el.getElementsByTagName('input')[0])
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
        changeEnabledDiscount() {

            if (!this.enabled_discount) {

                this.discount_amount = 0
                this.deleteDiscountGlobal()
                this.reCalculateTotal()

            }

        },
        changeTypeDiscount() {
            this.inputDiscountAmount()
        },
        inputDiscountAmount() {

            if (this.enabled_discount) {

                if (this.discount_amount && !isNaN(this.discount_amount) && parseFloat(this.discount_amount) > 0) {

                    if(this.is_discount_amount)
                    {
                        if (this.discount_amount >= this.form.total)
                            return this.$message.error("El monto de descuento debe ser menor al total de venta")
                    }

                    this.deleteDiscountGlobal()
                    this.reCalculateTotal()

                } else {

                    // this.discount_amount = 0
                    // this.deleteDiscountGlobal()
                    this.reCalculateTotal()

                }

                // console.log(this.discount_amount)
            }
        },
        isExonerated() {

            let not_exonerated = this.form.items.find((item) => {
                return item.affectation_igv_type_id != '20'
            })

            return (not_exonerated) ? false : true
        },
        setConfigGlobalDiscountType()
        {
            this.global_discount_type = _.find(this.global_discount_types, { id : this.globalDiscountTypeId})
        },
        setGlobalDiscount(factor, amount, base, amount_without_rounded)
        {
            let discount_text = '';
            if(this.global_discount_type && this.global_discount_type.description){
                discount_text = this.global_discount_type.description
            }
            this.form.discounts.push({
                discount_type_id: this.global_discount_type.id,
                description: discount_text,
                factor: factor,
                amount: _.round(amount, 2),
                base: base,
                amount_without_rounded: amount_without_rounded
            })
        },
        async discountGlobal(ctx) {

            // let percentage_igv = 18
            // let amount = parseFloat(this.discount_amount)

            let input_global_discount = parseFloat(this.discount_amount);
            if(this.is_discount_amount) {
                if ( (this.configuration.global_discount_type_id === "02") && this.configuration.exact_discount) {
                    input_global_discount = parseFloat(this.discount_amount / (1 + this.percentageIgv)) //input se usa para monto y porcentaje
                }
            }

            // let base = (this.globalDiscountTypeId === '02') ? parseFloat(this.form.total_taxed) : parseFloat(this.form.total)
            // let factor = _.round(amount / base, 5)

            let discount = _.find(this.form.discounts, {'discount_type_id': this.globalDiscountTypeId})
            let total = this.form.total

            if (input_global_discount > 0 && !discount)
            {
                const percentage_igv = this.percentageIgv * 100
                let base = (this.isGlobalDiscountBase && ctx.total_taxed)
                    ? parseFloat(ctx.total_taxed)
                    : parseFloat(ctx.total || this.form.total)
                let amount = 0
                let factor = 0

                if (this.is_discount_amount)
                {
                    amount = input_global_discount
                    factor = _.round(amount / base, 5)
                }
                else
                {
                    factor = _.round(input_global_discount / 100, 5)
                    amount = factor * base
                }


                // descuentos que afectan la bi
                if(this.isGlobalDiscountBase)
                {
                    let total_taxed = base - amount;
                    let total_igv = total_taxed * (percentage_igv / 100);
                    let total_taxes = total_igv + ctx.total_isc + ctx.total_plastic_bag_taxes;
                    let total = total_taxed + total_taxes;

                    this.form.total_taxed = _.round(parseFloat(total_taxed.toFixed(3)), 2)
                    this.form.total_value = this.form.total_taxed
                    this.form.total_igv = _.round(total_taxed * (percentage_igv / 100), 2)

                    //impuestos (isc + igv + icbper)
                    this.form.total_taxes = _.round(parseFloat(total_taxes.toFixed(3)), 2);
                    this.form.total = _.round(total, 2)
                    this.form.subtotal = this.form.total

                    if (this.form.total <= 0) this.$message.error("El total debe ser mayor a 0, verifique el tipo de descuento asignado (Configuración/Avanzado/Contable)")

                }
                // descuentos que no afectan la bi
                else
                {
                    // this.form.total_discount = _.round(amount, 2)
                    this.form.total = _.round(this.form.total - amount, 2)
                }

                this.form.total_discount = _.round(amount, 2)
                this.setGlobalDiscount(factor, _.round(amount,2), _.round(base,2), amount)
                let discount_inner = this.is_discount_amount ? this.discount_amount :  (total * this.discount_amount / 100)
                this.enter_amount = _.round(total - discount_inner,2)

            } else {

                //Se restablece el valor
                this.enter_amount = total
                this.deleteDiscountGlobal()
            }


            this.difference = _.round(this.enter_amount - this.form.total, 2)

            // this.difference = this.enter_amount - this.form.total_payable_amount
            // console.log(this.form.discounts)
        },
        reCalculateTotal() {

            let total_discount = 0
            let total_charge = 0
            let total_exportation = 0
            let total_taxed = 0
            let total_exonerated = 0
            let total_unaffected = 0
            let total_free = 0
            let total_igv = 0
            let total_value = 0
            let total = 0
            let total_plastic_bag_taxes = 0
            let total_base_isc = 0
            let total_isc = 0
            let total_igv_free = 0


            this.form.items.forEach((row) => {
                total_discount += parseFloat(row.total_discount)
                total_charge += parseFloat(row.total_charge)

                if (row.affectation_igv_type_id === '10') {
                    total_taxed += (row.total_value_without_rounding) ? parseFloat(row.total_value_without_rounding) : parseFloat(row.total_value)
                }

                if (row.affectation_igv_type_id === '20') {
                    total_exonerated += (row.total_value_without_rounding) ? parseFloat(row.total_value_without_rounding) : parseFloat(row.total_value)
                }

                if (row.affectation_igv_type_id === '30') {
                    total_unaffected += parseFloat(row.total_value)
                }

                if (row.affectation_igv_type_id === '40') {
                    total_exportation += parseFloat(row.total_value)
                }

                if (['10', '20', '30', '40'].indexOf(row.affectation_igv_type_id) < 0) {
                    total_free += parseFloat(row.total_value)
                }

                // if (['10', '20', '30', '40'].indexOf(row.affectation_igv_type_id) > -1) {
                if (['10', '20', '30', '40', '21'].indexOf(row.affectation_igv_type_id) > -1)
                {
                    total_igv += (row.total_igv_without_rounding) ? parseFloat(row.total_igv_without_rounding) : parseFloat(row.total_igv)
                    total += (row.total_without_rounding) ? parseFloat(row.total_without_rounding) : parseFloat(row.total)
                }

                if(!['21', '37'].includes(row.affectation_igv_type_id))
                {
                    total_value += (row.total_value_without_rounding) ? parseFloat(row.total_value_without_rounding) : parseFloat(row.total_value)
                }

                total_plastic_bag_taxes += parseFloat(row.total_plastic_bag_taxes)


                if (['11', '12', '13', '14', '15', '16'].includes(row.affectation_igv_type_id)) {

                    let unit_value = row.total_value / row.quantity
                    let total_value_partial = unit_value * row.quantity
                    row.total_taxes = row.total_value - total_value_partial + parseFloat(row.total_plastic_bag_taxes) //sumar icbper al total tributos

                    row.total_igv = total_value_partial * (row.percentage_igv / 100)
                    row.total_base_igv = total_value_partial
                    total_value -= row.total_value

                    total_igv_free += row.total_igv
                    total += parseFloat(row.total) //se agrega suma al total para considerar el icbper

                }

                // isc
                total_isc += parseFloat(row.total_isc)
                total_base_isc += parseFloat(row.total_base_isc)

            });
            let total_taxes = total_igv + total_isc + total_plastic_bag_taxes;
            let total_all = total - this.total_discount_no_base

            let totals_without_rounding = {
                total_discount,
                total_charge,
                total_exportation,
                total_taxed,
                total_exonerated,
                total_unaffected,
                total_free,
                total_igv,
                total_value,
                 total: total_all,
                total_plastic_bag_taxes,
                 total_igv_free,
                 total_base_isc,
                 total_isc,
                 total_taxes
            }


            // isc
            this.form.total_base_isc = _.round(total_base_isc, 2)
            this.form.total_isc = _.round(total_isc, 2)

            this.form.total_igv_free = _.round(total_igv_free, 2)

            this.form.total_exportation = _.round(total_exportation, 2)
            this.form.total_taxed = _.round(total_taxed, 2)
            this.form.total_exonerated = _.round(total_exonerated, 2)
            this.form.total_unaffected = _.round(total_unaffected, 2)
            this.form.total_free = _.round(total_free, 2)
            this.form.total_igv = _.round(total_igv, 2)
            this.form.total_value = _.round(total_value, 2)
            // this.form.total_taxes = _.round(total_igv, 2)

            //impuestos (isc + igv + icbper)
            this.form.total_taxes = _.round(total_igv + total_isc + total_plastic_bag_taxes, 2);
            // this.form.total_taxes = _.round(total_igv + total_isc, 2);

            this.form.total_plastic_bag_taxes = _.round(total_plastic_bag_taxes, 2)

            this.form.total = _.round(total, 2)
            this.form.subtotal = this.form.total

            // this.form.total = _.round(total + this.form.total_plastic_bag_taxes, 2)
            // this.form.subtotal = _.round(total + this.form.total_plastic_bag_taxes, 2)

            this.discountGlobal(totals_without_rounding)

            this.calculatePayments()
            this.setTotalPointsBySale(this.configuration)


        },
        calculatePayments() {
            let payment_count = this.form.payments.length;
            // let total = this.form.total;
            let total = this.getTotal()

            let payment = 0;
            let amount = _.round(total / payment_count, 2);

            _.forEach(this.form.payments, row => {
                payment += amount;
                if (total - payment < 0) {
                    amount = _.round(total - payment + amount, 2);
                }
                row.payment = amount;
                this.$set(row, 'payment', amount)
                // console.error(row.payment)
            })
        },
        calculateAmountToPayments() {
            // if(this.form.payments.length > 0){
            //     // this.form.payments[0].payment = this.form.total_pending_payment
            // }
            this.calculatePayments();
            // this.calculateFee();
        },
        getTotal() {
            let total_pay = this.form.total;
            // if (this.form.has_retention && this.form.total > 700) {
            //     total_pay -= this.form.retention.amount;
            // }

            if (
                !_.isEmpty(this.form.retention) &&
                this.form.total_pending_payment > 0
            ) {
                return this.form.total_pending_payment;
            }

            // console.log('2');
            return _.round(total_pay, 2)
        },
        deleteDiscountGlobal(kkj) {

            this.form.discounts = []
            this.form.total_discount = 0

            // let discount = _.find(this.form.discounts, {'discount_type_id': '03'})
            // let index = this.form.discounts.indexOf(discount)
            // // let is_exonerated = this.isExonerated()

            // if (index > -1) {
            //     this.form.discounts.splice(index, 1)
            //     this.form.total_discount = 0
            //     // this.setDiscountByItem(0, is_exonerated)
            // }

        },
        back() {
            this.$emit('update:is_payment', false)
        },
        async initLStoPayment() {

            this.amount = await this.getLocalStoragePayment('amount', 0)
            this.enter_amount = await this.getLocalStoragePayment('enter_amount', 0)
            this.difference = await this.getLocalStoragePayment('difference', 0)
        },
        getFormPosLocalStorage() {

            let form_pos = localStorage.getItem('form_pos');
            form_pos = JSON.parse(form_pos)
            if (form_pos) {
                this.form.payments = form_pos.payments
            }

        },
        clickAddPayment() {
            this.payments = JSON.parse(JSON.stringify(this.form.payments || []))
            this.showDialogMultiplePayment = true
        },
        reloadDataCardBrands(card_brand_id) {
            this.$http.get(`/${this.resource}/table/card_brands`).then((response) => {
                this.cards_brand = response.data
                this.form_payment.card_brand_id = card_brand_id
                this.changePaymentMethodType()
            })
        },
        getDescriptionPaymentMethodType(id) {
            let payment_method_type = _.find(this.payment_method_types, {'id': id})
            return (payment_method_type) ? payment_method_type.description : ''

        },
        changePaymentMethodType() {
            let payment_method_type = _.find(this.payment_method_types, {'id': this.form_payment.payment_method_type_id})
            this.has_card = payment_method_type.has_card
            this.form_payment.card_brand_id = (payment_method_type.has_card) ? this.form_payment.card_brand_id : null
        },
        addRow(payments) {

            this.form.payments = payments
            let acum_payment = 0

            this.form.payments.forEach((item) => {
                acum_payment += parseFloat(item.payment)
            })

            // this.amount = acum_payment
            this.setAmount(acum_payment)

            // console.log(this.form.payments)
        },
        setPaymentMethod(id){
            this.payment_method_type_id = id;
        },
        setAmount(amount) {
            // this.amount = parseFloat(this.amount) + parseFloat(amount)
            this.amount = parseFloat(amount) //+ parseFloat(amount)
            this.enter_amount = parseFloat(amount) //+ parseFloat(amount)
            this.inputAmount()
        },
        setAmountCash(amount) {
            let row = _.last(this.payments, {'payment_method_type_id': '01'})
            if (!row) return

            const current = parseFloat(row.payment)
            row.payment = (isNaN(current) ? 0 : current) + parseFloat(amount)
            // console.log(row.payment)

            this.form.payments = this.payments
            let acum_payment = 0

            this.form.payments.forEach((item) => {
                const payment = parseFloat(item.payment)
                acum_payment += isNaN(payment) ? 0 : payment
            })

            this.setAmount(acum_payment)

        },
        /**
         * Deja en el campo de cobro sólo caracteres válidos de un importe y
         * devuelve su valor numérico (0 si aún no hay un número escrito).
         */
        sanitizeEnterAmount() {
            const raw = (this.enter_amount === null || this.enter_amount === undefined)
                ? ''
                : String(this.enter_amount)

            let clean = raw.replace(/[^\d.]/g, '')
            const parts = clean.split('.')

            if (parts.length > 2) {
                clean = parts.shift() + '.' + parts.join('')
            }

            if (clean !== raw) this.enter_amount = clean

            const amount = parseFloat(clean)

            return isNaN(amount) ? 0 : amount
        },
        async enterAmount() {

            const entered = this.sanitizeEnterAmount()

            let r_item = await _.last(this.payments, {'payment_method_type_id': '01'})
            if (r_item) r_item.payment = entered

            let ind = this.form.payments.length - 1
            if (ind >= 0) this.form.payments[ind].payment = entered

            let acum_payment = 0

            await this.form.payments.forEach((item) => {
                const payment = parseFloat(item.payment)
                acum_payment += isNaN(payment) ? 0 : payment
            })
            // console.log(this.form.payments)

            this.amount = acum_payment
            this.setDifference(this.amount - this.form.total)

            this.$eventHub.$emit('eventSetFormPosLocalStorage', this.form)

            await this.lStoPayment()

        },
        /**
         * Actualiza el vuelto/faltante y habilita el botón de cobro sólo si el
         * importe entregado cubre el total.
         */
        setDifference(difference) {

            if (isNaN(difference)) {
                this.button_payment = true
                this.difference = 0
                return
            }

            this.difference = _.round(difference, 2)

            if (this.payment_method_type_id == '09') {
                this.button_payment = false
                return
            }

            this.button_payment = (this.difference < 0)
        },
        getLocalStoragePayment(key, re_default = null) {

            let ls_obj = localStorage.getItem(key);
            ls_obj = JSON.parse(ls_obj)

            if (ls_obj) {
                return ls_obj
            }

            return re_default
        },
        setLocalStoragePayment(key, obj) {
            localStorage.setItem(key, JSON.stringify(obj));
        },
        inputAmount() {

            const amount = parseFloat(this.amount)
            this.setDifference((isNaN(amount) ? 0 : amount) - this.getTotal())

            this.$eventHub.$emit('eventSetFormPosLocalStorage', this.form)
            this.lStoPayment()

        },
        lStoPayment() {

            this.setLocalStoragePayment('enter_amount', this.enter_amount)
            this.setLocalStoragePayment('amount', this.amount)
            // console.log(this.amount)
            this.setLocalStoragePayment('difference', this.difference)

        },
        initFormPayment() {

            this.difference = -this.form.total

            this.form_payment = {
                id: null,
                date_of_payment: moment().format('YYYY-MM-DD'),
                payment_method_type_id: '01',
                reference: null,
                card_brand_id: null,
                document_id: null,
                sale_note_id: null,
                payment: this.getTotal(),
            }

            this.form_cash_document = {
                document_id: null,
                sale_note_id: null
            }

            console.log(this.form_payment);

            this.is_discount_amount = true

        },

        filterSeries() {
            this.form.series_id = null
            this.series = _.filter(this.all_series, {'document_type_id': this.form.document_type_id});
            this.form.series_id = (this.series.length > 0) ? this.series[0].id : null

            if (!this.form.series_id) {
                return this.$message.warning('El sucursal no tiene series disponibles para el comprobante');
            }
        },
        async clickCancel() {

            this.loading_submit = true
            await this.sleep(800);
            this.loading_submit = false
            this.cleanLocalStoragePayment()
            this.$eventHub.$emit('cancelSale')

        },
        cleanLocalStoragePayment() {

            this.setLocalStoragePayment('amount', null)
            this.setLocalStoragePayment('enter_amount', null)
            this.setLocalStoragePayment('difference', null)
        },
        sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        },
        async asignPlateNumberToItems() {
            if (this.form.plate_number) {

                await this.form.items.forEach(item => {

                    let at = _.find(item.attributes, {'attribute_type_id': '5010'})

                    if (!at) {
                        item.attributes.push({
                            attribute_type_id: '7000',
                            description: "Gastos Art. 37 Renta:  Número de Placa",
                            value: this.form.plate_number,
                            start_date: null,
                            end_date: null,
                            duration: null,
                        })
                    }
                });
            }
        },
        getDiscountPercentages()
        {
            if(this.form.discounts)
            {
                return _.sumBy(this.form.discounts, (discount)=>{
                    return discount.factor * 100
                })
            }

            return 0
        },
        tokenValidated()
        {
            this.form.token_validated_for_discount = true
        },
        validateRestrictSellerDiscount()
        {
            if(this.configuration.restrict_seller_discount && this.typeUser !== 'admin')
            {
                const all_percentages = this.getDiscountPercentages()

                if(all_percentages > parseFloat(this.configuration.sellers_discount_limit) && !this.form.token_validated_for_discount)
                {
                    this.totalDiscountPercentage = _.round(all_percentages, 2)
                    this.showDialogDiscountPermission = true

                    return {
                        success: false,
                    }
                }
            }

            return {
                success: true
            }
        },
        validateRestrictSaleItemsCpe()
        {
            if(this.applyRestrictSaleItemsCpe)
            {
                let errors_restricted = 0

                this.form.items.forEach(row => {
                    if(this.isRestrictedForSale(row.item)) errors_restricted++
                })

                if(errors_restricted > 0) return this.getObjectResponse(false, 'No puede generar el comprobante, tiene productos restringidos.')
            }


            return this.getObjectResponse()
        },
        getObjectResponse(success = true, message = null)
        {
            return {
                success: success,
                message: message,
            }
        },
        async autoSendPdfMail() {
            if (!this.config.auto_send_pdf_email) return;

            if (!this.customer_email) {
                this.$message.warning('El cliente no tiene correo registrado.');
                return;
            }

            this.$http.post(`/${this.resource_documents}/email`, {
                customer_email: this.customer_email,
                id: this.documentNewId
            }).then(response => {
                if (response.data.success) {
                    this.$message.success('El correo fue enviado satisfactoriamente');
                } else {
                    this.$message.error('Error al enviar el correo');
                }
            }).catch(() => {
                this.$message.error('Error al enviar el correo');
            });
        },
        async clickPayment()
        {
            // validacion restriccion de productos
            const validate_restrict_sale_items_cpe = this.validateRestrictSaleItemsCpe()
            if(!validate_restrict_sale_items_cpe.success) return this.$message.error(validate_restrict_sale_items_cpe.message)

            // validacion restriccion de descuento
            const validate_restrict_seller_discount = this.validateRestrictSellerDiscount()
            if(!validate_restrict_seller_discount.success) return

            if (this.form.payments == 0){
                this.form.payment_condition_id = "02";
            }

            // validacion sistema por puntos
            if(this.enabledPointSystem)
            {
                const validate_exchange_points = this.validateExchangePoints()
                if(!validate_exchange_points.success) return this.$message.error(validate_exchange_points.message)
            }
            else
            {
                if(this.form.total <= 0) return this.$message.error('El total debe ser mayor a 0')
            }


            if (!moment(moment().format("YYYY-MM-DD")).isSame(this.form.date_of_issue)) {
                return this.$message.error('La fecha de emisión no coincide con la del día actual');
            }

            if (!this.form.series_id) {
                return this.$message.warning('El sucursal no tiene series disponibles para el comprobante');
            }

            this.form.created_from_pos = true;
            this.form.show_terms_condition = true;
            const cfg = this.config || this.configuration || {};
            if (cfg.terms_condition_sale) {
                this.form.terms_condition = cfg.terms_condition_sale;
            }

            if (this.form.document_type_id === "80") {
                this.form.prefix = "NV";
                this.form.paid = 1;
                this.resource_documents = "sale-notes";
                this.resource_payments = "sale_note_payments";
                this.resource_options = this.resource_documents;
            } else {
                this.form.prefix = null;
                this.resource_documents = "documents";
                this.resource_payments = "document_payments";
                this.resource_options = this.resource_documents;
                await this.asignPlateNumberToItems()
            }

            if (this.form.has_retention && this.form.total > 700) {
                this.setTotalPendingAmountRetention(this.form.retention.amount);
            }

            this.loading_submit = true
            this.locked_submit = true

            await this.$http.post(`/${this.resource_documents}`, this.form).then(async (response) => {
                if (response.data.success) {
                    let response_sent = null
                    this.responseForm = response.data

                    if (this.form.document_type_id === "80") {
                        // this.form_payment.sale_note_id = response.data.data.id;
                        this.form_cash_document.sale_note_id = response.data.data.id;

                    } else {
                        // ########## INICIO CAMBIO SIN XML CDR SUNAT
                        // El alta ya termina registrada localmente; no se ejecuta un segundo envío fiscal.
                        // ######### FIN CAMBIO SIN XML CDR SUNAT
                        // this.form_payment.document_id = response.data.data.id;
                        this.form_cash_document.document_id = response.data.data.id;

                    }


                    this.documentNewId = response.data.data.id;
                    // this.showDialogOptions = true;
                    this.autoSendPdfMail();
                    this.showOptionsDialog(response_sent)

                    // this.savePaymentMethod();
                    this.saveCashDocument();

                    // this.initFormPayment() ;
                    this.cleanLocalStoragePayment()
                    // if(this.isPrint){
                    //     this.gethtml();
                    // migrado a options
                    // }
                    this.$eventHub.$emit('saleSuccess');
                } else {
                    this.$message.error(response.data.message);
                }
            }).catch(error => {
                console.log(error);

                if (error.response.status === 422) {
                    this.errors = error.response.data;
                } else {
                    this.$message.error(error.response.data.message);
                }
            }).then(() => {
                this.loading_submit = false;
                this.locked_submit = false
            });
        },

        showOptionsDialog(response){

            if(this.hidePdfViewDocuments)
            {

                if(this.form.document_type_id === '80')
                {
                    this.$message.success(`Nota de venta registrada: ${this.responseForm.data.number_full}`)
                }
                else
                {
                    if (response) {
                        const response_data = response.data
                        this.$message.success(response_data.message)
                    }
                    else
                    {
                        this.$message.success(`Comprobante registrado: ${this.responseForm.data.number_full}`)
                    }
                }




                if (this.isPrint) {
                    this.clickCancel()
                    this.autoPrint();
                } else {
                    this.clickCancel()
                }

            }
            else
            {
                this.showDialogOptions = true
            }

        },
        async autoPrint() {
            if (!this.isPrint) return;
            if (!this.responseForm || !this.responseForm.links ) return;

            try {
                // Centraliza la impresión vía backend → Redis → BuhoPrinter agent
                await this.printDocument(this.responseForm.links.print_ticket, this.configuration?.printer_name_documents);
            } catch (e) {
                console.error('payment autoPrint error', e);
            }
        },

        // ########## INICIO CAMBIO SIN XML CDR SUNAT
        // No se expone un método de envío fiscal en el pago local.
        // ######### FIN CAMBIO SIN XML CDR SUNAT
        gethtml(){
            this.form.datahtml="";
            var doc='salenote';
            var route = `/printticket/document/${this.documentNewId}/ticket`;
            if(this.resource_documents!=='documents'){
                route = `/sale-notes/ticket/${this.documentNewId}/ticket`;
            }

            // console.log(route);

            this.$http.get(route)
            .then(response => {
                if (response.data.length>0) {
                    this.form.datahtml=response.data;
                    this.printticket();
                }

            })
            .catch(error => {
                console.log(error);
            })
        },
        async printticket(){
            await this.sleep(400);
            const configg = this.getUpdatedConfig();
            if (!this.form.datahtml) return;
            // Reutiliza el print_ticket PDF del último comprobante guardado
            const url = this.responseForm?.links?.print_ticket;
            if (url) {
                // Centraliza la impresión vía backend → Redis → BuhoPrinter agent
                await this.printDocument(url, this.configuration?.printer_name_documents);
            } else {
                console.warn('[BuhoPrinter] print_ticket URL no disponible.');
            }
        },
        saveCashDocument() {
            this.$http.post(`/cash/cash_document`, this.form_cash_document)
                .then(response => {
                    if (response.data.success) {
                        // console.log(response)
                    } else {
                        this.$message.error(response.data.message);
                    }
                })
                .catch(error => {
                    console.log(error);
                })
        },
        savePaymentMethod() {
            this.$http.post(`/${this.resource_payments}`, this.form_payment)
                .then(response => {
                    if (response.data.success) {
                        // console.log(response)
                    } else {
                        this.$message.error(response.data.message);
                    }
                })
                .catch(error => {
                    if (error.response.status === 422) {
                        this.records[index].errors = error.response.data;
                    } else {
                        console.log(error);
                    }
                })
        },
        async getTables() {
            this.$http.get(`/${this.resource}/payment_tables`)
                .then(response => {
                    this.all_series = response.data.series
                    this.payment_method_types = response.data.payment_method_types
                    this.cards_brand = response.data.cards_brand
                    this.global_discount_types = response.data.global_discount_types
                    this.filterSeries()
                    this.setConfigGlobalDiscountType()
                    this.setDefaultDocumentType()
                })

        },
        setTotalPendingAmountRetention(amount) {
            //monto neto pendiente aplica si la condicion de pago es credito
            this.form.total_pending_payment = ["02", "03"].includes(
                this.form.payments.length == 0 ? '02' : '01'
            )
                ? this.form.total - amount
                : 0;

            // this.calculateAmountToPayments();
        },
    }
}
</script>
