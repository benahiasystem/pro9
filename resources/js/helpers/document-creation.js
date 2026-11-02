import {exactAmount, exactSum, exactValue, allocateExact} from './exchange-rate-math'
import {isRecordedReceipt} from './payment-classification'

// UI creation flows never carry received retentions, including restored drafts.
export function withoutDraftRetentions(form) {
    const result = {...form}
    for (const key of ['iva_retention_prevision', 'islr_retention_prevision', 'received_retentions']) delete result[key]
    return result
}

// ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
export function paymentConditionPayload(form) {
    const condition = form.payment_condition_id
    return {...form, payment_condition_id: condition === '03' ? '02' : condition,
        fee: condition === '01' ? [] : form.fee}
}
// ######## FIN CLASIFICACION DE COBROS Y CAJA ########

export function collectionTarget(form) {
    const amount = exactAmount(form.total_payable ?? form.total ?? '0')
    return amount.compare(0) < 0 ? '0.00' : amount.final(2)
}


// Suggested amounts belong to the open form, never to persisted receipts or payloads.
const suggestedPayments = new WeakMap()
const editedPayments = new WeakSet()
const suggestedInstallments = new WeakMap()

// Only replace amounts still owned by the suggestion; entered and saved
// installments remain untouched when receipts or document totals change.
export function suggestInstallments(form, target = null) {
    try {
        target = target ?? collectionTarget(form)
        const rows = form.fee || []
        const available = []
        const fixed = []
        for (const row of rows) {
            const previous = suggestedInstallments.get(row)
            const unchanged = previous !== undefined && String(row.amount) === previous
            if (row.id == null && (unchanged || (previous === undefined && exactValue(row, 'amount').compare(0) === 0))) available.push(row)
            else fixed.push(row)
        }
        if (!available.length) return
        const balance = exactAmount(target).minus(exactSum((form.payments || []).map(row => exactValue(row, 'payment'))))
            .minus(exactSum(fixed.map(row => exactValue(row, 'amount'))))
        const amounts = allocateExact(balance.compare(0) > 0 ? balance : '0', available.map(() => '1'), 2)
        available.forEach((row, index) => {
            row.amount = amounts[index]
            row.allocation_weight = '1'
            suggestedInstallments.set(row, String(row.amount))
        })
    } catch (error) {
        // While typing an incomplete decimal, keep the last valid suggestions.
    }
}

export function markPaymentEdited(payment) {
    suggestedPayments.delete(payment)
    editedPayments.add(payment)
}

export function suggestRemainingPayment(form, payments = form.payments || [], {refreshSuggested = false} = {}) {
    const last = payments[payments.length - 1]
    if (!last || isRecordedReceipt(last)) return
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
