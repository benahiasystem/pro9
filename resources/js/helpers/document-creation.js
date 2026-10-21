import {exactAmount, exactSum, exactValue} from './exchange-rate-math'

// UI creation flows never carry received retentions, including restored drafts.
export function withoutDraftRetentions(form) {
    const result = {...form}
    for (const key of ['iva_retention_prevision', 'islr_retention_prevision', 'received_retentions']) delete result[key]
    return result
}

export function collectionTarget(form) {
    const amount = exactAmount(form.total_payable ?? form.total ?? '0')
    return amount.compare(0) < 0 ? '0.00' : amount.final(2)
}

// Suggested amounts belong to the open form, never to persisted receipts or payloads.
const suggestedPayments = new WeakMap()
const editedPayments = new WeakSet()

export function markPaymentEdited(payment) {
    suggestedPayments.delete(payment)
    editedPayments.add(payment)
}

export function suggestRemainingPayment(form, payments = form.payments || [], {refreshSuggested = false} = {}) {
    const last = payments[payments.length - 1]
    if (!last) return
    const previous = refreshSuggested && suggestedPayments.get(last)
    if (refreshSuggested && (editedPayments.has(last) || last.id != null || last.document_id != null)) return
    // A nonzero amount is editable automatically only while it is still our suggestion.
    if (previous && (last.payment !== previous.amount || form.currency_type_id !== previous.documentCurrency)) {
        markPaymentEdited(last)
        return
    }
    if (!previous && exactValue(last, 'payment').compare(0) !== 0) return
    // A payment in another currency requires an explicit amount in that currency.
    if (last.currency_type_id && last.currency_type_id !== form.currency_type_id) return
    const entered = exactSum(payments.slice(0, -1).map(row => exactValue(row, 'payment')))
    const remaining = exactAmount(collectionTarget(form)).minus(entered)
    last.payment = remaining.compare(0) > 0 ? remaining.final(2) : '0.00'
    if (refreshSuggested) {
        suggestedPayments.set(last, {amount: last.payment, documentCurrency: form.currency_type_id})
        if (last.currency_type_id === form.currency_type_id && last.original_amount != null) last.original_amount = last.payment
    }
    if (last.calculation_snapshot?.values?.payment) {
        last.calculation_snapshot = {...last.calculation_snapshot, values: {...last.calculation_snapshot.values, payment: exactAmount(last.payment).fraction()}}
    }
}
