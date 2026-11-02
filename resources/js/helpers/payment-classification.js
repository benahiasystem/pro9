// ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
// Currency is a property of the method; expense methods have their own catalog.
export function isRecordedReceipt(row) {
    return Boolean(row && (row.id || row.receipt_parent_id || row.receipt_origin || row.payment_method_snapshot?.receipt_origin ||
        row.source_sale_note_payment_id || row.source_quotation_payment_id || row.source_technical_service_payment_id || row._reused_receipt))
}

export function paymentMethodCurrency(methods, methodId) {
    const method = methods.find(item => String(item.id) === String(methodId))
    return method?.currency_type_id || null
}

export function defaultCashForCurrency(methods = [], currency) {
    if (!currency) return null
    // Orden de puntos de código Unicode: coincide con el orden binario UTF-8 del servidor.
    const compareIds = (left, right) => {
        const a = Array.from(String(left.id), char => char.codePointAt(0))
        const b = Array.from(String(right.id), char => char.codePointAt(0))
        for (let i = 0; i < Math.min(a.length, b.length); i++) if (a[i] !== b[i]) return a[i] - b[i]
        return a.length - b.length
    }
    return methods.filter(method => method && [true, 1, '1'].includes(method.is_active)
        && method.collection_kind === 'cash' && method.currency_type_id === currency)
        .sort(compareIds)[0] || null
}

// Only propose a destination for a new, unfilled cash payment. Existing
// receipts carry their destination from the original financial movement.
export function proposeCashDestination(destinations = [], methods = [], row, currency) {
    if (!row || isRecordedReceipt(row) || row.payment_destination_id != null) return
    const method = methods.find(item => String(item.id) === String(row.payment_method_type_id))
    if (!method || method.collection_kind !== 'cash' || ![true, 1, '1'].includes(method.is_active)) return
    if (classifiedDestinations(destinations, methods, row, currency).some(destination => destination.id === 'cash')) {
        row.payment_destination_id = 'cash'
    }
}

// Rows created before the catalog response are resolved only if still unselected.
export function restoreDeferredInvoiceCash(vm) {
    const pending = vm.pendingDefaultPaymentRows || []
    const method = defaultCashForCurrency(vm.payment_method_types || [], vm.form.currency_type_id)
    for (const row of pending) {
        if (!vm.form.payments.includes(row) || isRecordedReceipt(row) || row.payment_method_type_id != null || !method) continue
        row.payment_method_type_id = method.id
        if (vm.config?.destination_sale) proposeCashDestination(vm.payment_destinations || [], vm.payment_method_types || [], row, vm.form.currency_type_id)
    }
    vm.pendingDefaultPaymentRows = []
}

// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
export function paymentValidationMessage(data) {
    const errors = data?.errors || (data?.message && typeof data.message === 'object' ? data.message : data)
    if (!errors || typeof errors !== 'object' || Array.isArray(errors)) return null
    const fields = new Set(['payments', 'payment', 'payment_method_type_id', 'payment_destination_id',
        'currency_type_id', 'original_amount', 'exchange_rate', 'financial_intermediation', 'igtf_status', 'exception_basis',
        'source_sale_note_payment_id', 'source_quotation_payment_id', 'source_technical_service_payment_id', 'total'])
    for (const [key, value] of Object.entries(errors)) {
        if (!fields.has(key.split('.').pop())) continue
        const messages = Array.isArray(value) ? value : [value]
        const message = messages.find(item => typeof item === 'string' && item.trim())
        if (message) return message
    }
    return null
}

export function handleHotelPaymentError(error, view) {
    const response = error?.response
    if (!response) {
        view.$message.error(error?.message || 'No se pudo registrar la operación.')
        return
    }
    if (response.status === 422) {
        const data = response.data || {}
        view.errors = data.errors || (data.message && typeof data.message === 'object' ? data.message : data)
        const message = paymentValidationMessage(data) || (data.errors && typeof data.message === 'string' ? data.message : null)
        if (message) view.$message.error(message)
        return
    }
    view.axiosError(error)
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########

export function classifiedDestinations(destinations, methods, row, currency, expense = false, documentCurrencyOnly = false) {
    const method = methods.find(method => String(method.id) === String(expense ? row.expense_method_type_id : row.payment_method_type_id))
    const kind = expense ? (String(row.expense_method_type_id) === '1' ? 'cash' : 'account') : method?.collection_kind
    if (!['cash', 'account'].includes(kind)) return []
    const configured = expense || documentCurrencyOnly ? null : paymentMethodCurrency(methods, row.payment_method_type_id)
    if (configured && row.currency_type_id && row.currency_type_id !== configured) return []
    const received = configured || row.currency_type_id || currency || method?.cash_currency_type_id
    return destinations.filter(destination => kind === 'cash'
        ? destination.id === 'cash' && (!method?.cash_currency_type_id || method.cash_currency_type_id === received)
        : destination.id !== 'cash' && (!received || destination.currency_type_id === received))
}

export default {
    methods: {
        isRecordedReceipt,
        classifiedPaymentDestinationDisabled(destinations, row) {
            return false
        },
        classifiedPaymentDestinations(destinations, row) {
            const expense = Object.prototype.hasOwnProperty.call(row, 'expense_method_type_id')
            const methods = (expense ? this.expense_method_types : this.payment_method_types) || this.$store?.state?.payment_method_types || []
            const currency = row.currency_type_id || this.document?.currency_type_id || this.form?.currency_type_id || this.expense?.currency_type_id || this.purchase?.currency_type_id || this.income?.currency_type_id || this.record?.currency_type_id || this.paymentCurrency || this.currencyTypeId || this.currency_type_id
            return classifiedDestinations(destinations, methods, row, currency, expense, this.$options?.paymentCurrencyPolicy === 'document')
        },
        refreshClassifiedPaymentDestinations() {
            [this.form?.payments,this.form?.prepayments,this.document?.payments,this.payments,this.records,[this.form_payment || {}],[this.form?.rent_payment || {}]].forEach(rows => this.clearIncompatiblePaymentDestinations(rows))
        },
        clearIncompatiblePaymentDestinations(rows) {
            if (!Array.isArray(rows) || !Array.isArray(this.payment_destinations)) return
            rows.forEach(row => {
                if (isRecordedReceipt(row)) return
                const method=(this.payment_method_types || this.$store?.state?.payment_method_types || []).find(method => String(method.id) === String(row.payment_method_type_id))
                if (method?.collection_kind && row.collection_kind !== method.collection_kind) this.$set(row,'collection_kind',method.collection_kind)
                if (row.payment_destination_id == null) return
                const available = this.classifiedPaymentDestinations(this.payment_destinations, row)
                if (!available.some(destination => destination.id === row.payment_destination_id)) this.$set(row, 'payment_destination_id', null)
            })
        },
    },
    watch: {
        'form.currency_type_id': 'refreshClassifiedPaymentDestinations',
        'document.currency_type_id': 'refreshClassifiedPaymentDestinations',
        'expense.currency_type_id': 'refreshClassifiedPaymentDestinations',
        currencyTypeId: 'refreshClassifiedPaymentDestinations',
        currency_type_id: 'refreshClassifiedPaymentDestinations',
        'form.payments': {deep: true, handler(rows) { this.clearIncompatiblePaymentDestinations(rows) }},
        'form.prepayments': {deep: true, handler(rows) { this.clearIncompatiblePaymentDestinations(rows) }},
        'document.payments': {deep: true, handler(rows) { this.clearIncompatiblePaymentDestinations(rows) }},
        payments: {deep: true, handler(rows) { this.clearIncompatiblePaymentDestinations(rows) }},
        records: {deep: true, handler(rows) { this.clearIncompatiblePaymentDestinations(rows) }},
        'form_payment.payment_method_type_id'() { if (this.form_payment) this.clearIncompatiblePaymentDestinations([this.form_payment]) },
        'form.payment_method_type_id'() { if (this.form) this.clearIncompatiblePaymentDestinations([this.form]) },
        'form.expense_method_type_id'() { if (this.form) this.clearIncompatiblePaymentDestinations([this.form]) },
        'form.rent_payment': {deep: true, handler(row) { if (row) this.clearIncompatiblePaymentDestinations([row]) }},
    },
}
// ######## FIN CLASIFICACION DE COBROS Y CAJA ########
