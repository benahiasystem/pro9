// ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
export function classifiedDestinations(destinations, methods, row, currency, expense = false) {
    const method = methods.find(method => String(method.id) === String(expense ? row.expense_method_type_id : row.payment_method_type_id))
    const kind = expense ? (String(row.expense_method_type_id) === '1' ? 'cash' : 'account') : method?.collection_kind
    if (!['cash', 'account'].includes(kind)) return []
    const received = row.currency_type_id || currency || method?.cash_currency_type_id
    return destinations.filter(destination => kind === 'cash'
        ? destination.id === 'cash' && (!method?.cash_currency_type_id || method.cash_currency_type_id === received)
        : destination.id !== 'cash' && (!received || destination.currency_type_id === received))
}

export default {
    methods: {
        classifiedPaymentDestinationDisabled(destinations, row) {
            return false
        },
        classifiedPaymentDestinations(destinations, row) {
            const expense = Object.prototype.hasOwnProperty.call(row, 'expense_method_type_id')
            const methods = (expense ? this.expense_method_types : this.payment_method_types) || this.$store?.state?.payment_method_types || []
            const currency = row.currency_type_id || this.document?.currency_type_id || this.form?.currency_type_id || this.expense?.currency_type_id || this.income?.currency_type_id || this.record?.currency_type_id || this.paymentCurrency || this.currencyTypeId || this.currency_type_id
            return classifiedDestinations(destinations, methods, row, currency, expense)
        },
        refreshClassifiedPaymentDestinations() {
            [this.form?.payments,this.payments,this.records,[this.form_payment || {}],[this.form?.rent_payment || {}]].forEach(rows => this.clearIncompatiblePaymentDestinations(rows))
        },
        clearIncompatiblePaymentDestinations(rows) {
            if (!Array.isArray(rows) || !Array.isArray(this.payment_destinations)) return
            rows.forEach(row => {
                if (row.id) return
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
        payments: {deep: true, handler(rows) { this.clearIncompatiblePaymentDestinations(rows) }},
        records: {deep: true, handler(rows) { this.clearIncompatiblePaymentDestinations(rows) }},
        'form_payment.payment_method_type_id'() { if (this.form_payment) this.clearIncompatiblePaymentDestinations([this.form_payment]) },
        'form.payment_method_type_id'() { if (this.form) this.clearIncompatiblePaymentDestinations([this.form]) },
        'form.expense_method_type_id'() { if (this.form) this.clearIncompatiblePaymentDestinations([this.form]) },
        'form.rent_payment': {deep: true, handler(row) { if (row) this.clearIncompatiblePaymentDestinations([row]) }},
    },
}
// ######## FIN CLASIFICACION DE COBROS Y CAJA ########
