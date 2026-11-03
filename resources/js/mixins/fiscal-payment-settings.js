import {igtfSettings, loadIgtfSettings, fiscalPaymentProblem} from '../helpers/igtf-settings'
import IgtfSettingsStatus from '../components/IgtfSettingsStatus.vue'
import IgtfQuoteSummary from '../components/IgtfQuoteSummary.vue'
import {exactAmount} from '../helpers/exchange-rate-math'
import {isRecordedReceipt} from '../helpers/payment-classification'

// ######## INICIO CONFIGURACION COMPARTIDA IGTF ########
export default {
    components: {IgtfSettingsStatus, IgtfQuoteSummary},
    data: () => ({igtfSettings, igtfPreparingSubmission: false, igtfQuote: null, igtfQuoteLoading: false,
        igtfQuoteError: '', igtfQuoteKey: null, igtfQuoteSequence: 0}),
    computed: {
        fiscalPaymentsApplicable() {
            const type = this.documentTypeId || (this.form && this.form.document_type_id) || (this.document && this.document.document_type_id)
            return ['01', '08'].includes(type)
        },
        igtfEnabled() { return this.igtfSettings.loaded && this.igtfSettings.enabled },
        fiscalQuoteRows() {
            if (Array.isArray(this.form?.payments)) return this.form.payments
            if (Array.isArray(this.draftPayments)) return this.draftPayments
            return (this.records || []).filter(row => !row.id)
        },
        fiscalQuoteCurrency() { return this.form?.currency_type_id || this.currencyTypeId || this.document?.currency_type_id || '' },
        fiscalQuoteKey() {
            if (!this.fiscalPaymentsApplicable || this.showDialog === false || this.is_payment === false) return ''
            const request = this.fiscalQuoteRequest(this.fiscalQuoteRows)
            return request ? JSON.stringify([request, this.igtfSettings.enabled, this.igtfSettings.rate]) : ''
        },
    },
    created() {
        if (this.fiscalPaymentsApplicable) {
            this.refreshIgtfSettings()
            if (typeof this.refreshFiscalQuote === 'function') this.refreshFiscalQuote()
        }
    },
    watch: {
        fiscalPaymentsApplicable(value) { if (value) this.refreshIgtfSettings() },
        showDialog(value) { if (value && this.fiscalPaymentsApplicable) this.refreshIgtfSettings() },
        is_payment(value) { if (value && this.fiscalPaymentsApplicable) this.refreshIgtfSettings() },
        documentId() { if (this.fiscalPaymentsApplicable) this.refreshIgtfSettings() },
        fiscalQuoteKey() { this.refreshFiscalQuote() },
    },
    beforeDestroy() { this.igtfQuoteSequence++ },
    methods: {
        refreshIgtfSettings() { return loadIgtfSettings(this.$http) },
        fiscalQuoteRequest(payments) {
            const rows = (payments || []).filter(row => {
                try { return exactAmount(row.payment || '0').compare(0) > 0 } catch (_) { return false }
            })
            if (!rows.length) return null
            if (!rows.some(row => !isRecordedReceipt(row))) return null
            const documentId = Array.isArray(this.records) ? this.documentId : null
            const currency = this.form?.currency_type_id || this.currencyTypeId || this.document?.currency_type_id
            const rate = this.form?.exchange_rate_sale ?? this.exchangeRateSale ?? this.document?.exchange_rate_sale
            const due = this.collectionTarget ?? this.form?.total_payable ?? this.form?.total ?? this.total ?? this.document?.amount_payable ?? this.document?.total_difference
            if (!documentId && (!currency || !rate || due === undefined || due === null)) return null
            const payloadRows = rows.map(row => {
                const money = row.currency_type_id || currency
                return {
                    id: row.id, document_id: row.document_id, sale_note_id: row.sale_note_id, quotation_id: row.quotation_id,
                    technical_service_id: row.technical_service_id, receipt_parent_id: row.receipt_parent_id,
                    receipt_origin: row.receipt_origin, payment_method_snapshot: row.payment_method_snapshot,
                    source_sale_note_payment_id: row.source_sale_note_payment_id, source_quotation_payment_id: row.source_quotation_payment_id,
                    source_technical_service_payment_id: row.source_technical_service_payment_id, _reused_receipt: row._reused_receipt,
                    date_of_payment: row.date_of_payment,
                    payment_method_type_id: row.payment_method_type_id, payment: row.payment,
                    currency_type_id: money, original_amount: row.original_amount ?? (money === currency ? row.payment : null),
                    exchange_rate: row.exchange_rate ?? (money === currency ? rate : null), financial_intermediation: row.financial_intermediation,
                    igtf_status: row.igtf_status === 'exempt' ? 'exempt' : undefined,
                    exemption_reason: row.exemption_reason, exception_basis: row.exception_basis,
                }
            })
            return {document_id: documentId || undefined, customer_id: this.form?.customer_id || this.customerId || undefined,
                currency_type_id: currency, exchange_rate_sale: rate, principal_due: due, payments: payloadRows}
        },
        fiscalQuotePresentation(quote) {
            return JSON.stringify([quote?.payments?.map(row => [row.igtf_status, row.tax_amount, row.reason, row.change]),
                quote?.igtf_enabled, quote?.igtf_rate, quote?.total_with_tax, quote?.received_by_currency, quote?.tendered_by_currency])
        },
        async refreshFiscalQuote(payments = this.fiscalQuoteRows) {
            const request = this.fiscalQuoteRequest(payments)
            const key = request ? JSON.stringify([request, this.igtfSettings.enabled, this.igtfSettings.rate]) : ''
            const sequence = ++this.igtfQuoteSequence
            const source = this.form?.payments === payments ? 'form' : (this.draftPayments === payments ? 'draft' : null)
            const isCurrent = () => {
                const current = this.fiscalQuoteRequest(payments)
                return sequence === this.igtfQuoteSequence && this.fiscalPaymentsApplicable
                    && this.showDialog !== false && this.is_payment !== false
                    && (source !== 'form' || this.form?.payments === payments)
                    && (source !== 'draft' || this.draftPayments === payments)
                    && (!Array.isArray(this.records) || payments.every(row => this.records.includes(row)))
                    && current && JSON.stringify([current, this.igtfSettings.enabled, this.igtfSettings.rate]) === key
            }
            this.igtfQuote = null; this.igtfQuoteKey = null; this.igtfQuoteError = ''
            if (!key || !this.fiscalPaymentsApplicable || this.showDialog === false || this.is_payment === false) {
                this.igtfQuoteLoading = false
                return null
            }
            this.igtfQuoteLoading = true
            try {
                const response = await this.$http.post('/documents/payment-quote', request)
                if (!isCurrent()) return null
                const quote = response.data.data
                const rows = payments.filter(row => { try { return exactAmount(row.payment || '0').compare(0) > 0 } catch (_) { return false } })
                rows.forEach((row, index) => {
                    if (isRecordedReceipt(row)) return
                    this.$set(row, 'igtf_status', quote.payments[index].igtf_status)
                    this.$set(row, 'quoted_tax_amount', quote.payments[index].tax_amount)
                })
                this.igtfQuote = quote; this.igtfQuoteKey = key
                return quote
            } catch (error) {
                if (isCurrent()) {
                    const data = error.response?.data || {}
                    const first = Object.values(data.errors || {}).flat().find(value => typeof value === 'string')
                    this.igtfQuoteError = first || 'No se pudo evaluar el cobro. Revise los datos y reintente.'
                }
                return null
            } finally {
                if (sequence === this.igtfQuoteSequence) this.igtfQuoteLoading = false
            }
        },
        async ensureFiscalPaymentsReady(payments) {
            if (!this.fiscalPaymentsApplicable) return true
            if (this.igtfPreparingSubmission || this.loading_submit) return false
            this.igtfPreparingSubmission = true
            const form = this.form
            const documentId = this.documentId
            const formPayments = Array.isArray(form?.payments) ? form.payments : null
            try {
                await this.refreshIgtfSettings()
                if (this.form !== form || (formPayments && this.form.payments !== formPayments) || this.documentId !== documentId || this.showDialog === false || this.is_payment === false) return false
                const problem = fiscalPaymentProblem(payments)
                if (problem) { this.$message.error(problem); return false }
                const request = this.fiscalQuoteRequest(payments)
                if (!request) {
                    const needsQuote = (payments || []).some(row => {
                        if (isRecordedReceipt(row) || row.payment === null || row.payment === undefined || row.payment === '') return false
                        try { return exactAmount(row.payment).compare(0) !== 0 } catch (_) { return true }
                    })
                    if (!needsQuote) return true
                    this.$message.error('Complete los datos del cobro para evaluar el IGTF.'); return false
                }
                const previous = this.igtfQuote
                const previousKey = this.igtfQuoteKey
                const currentKey = JSON.stringify([request, this.igtfSettings.enabled, this.igtfSettings.rate])
                const previousPresentation = this.fiscalQuotePresentation(previous)
                const quote = await this.refreshFiscalQuote(payments)
                if (this.form !== form || (formPayments && this.form.payments !== formPayments) || this.documentId !== documentId || this.showDialog === false || this.is_payment === false) return false
                if (!quote) { this.$message.error(this.igtfQuoteError || 'La evaluación del cobro cambió. Revise e intente nuevamente.'); return false }
                if (previousKey !== currentKey || previousPresentation !== this.fiscalQuotePresentation(quote)) {
                    this.$message.error('Revise el IGTF actualizado y confirme nuevamente el cobro.'); return false
                }
                return true
            } finally {
                this.igtfPreparingSubmission = false
            }
        },
    },
}
// ######## FIN CONFIGURACION COMPARTIDA IGTF ########
