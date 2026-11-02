import {exactAmount} from './exchange-rate-math'

// Una fila propuesta al seleccionar contado no es un abono confirmado.
const automaticPayments = new WeakMap()
const paymentFields = ['date_of_payment', 'payment_method_type_id', 'payment_destination_id', 'reference', 'payment']
export function markAutomaticPurchasePayment(row) {
    automaticPayments.set(row, paymentFields.map(field => String(row[field] ?? '')))
}
export function isUntouchedAutomaticPurchasePayment(row) {
    const original = automaticPayments.get(row)
    return row.id == null && !!original && paymentFields.every((field, index) => String(row[field] ?? '') === original[index])
}

// La validación del formulario compara importes documentales sin redondeos binarios.
export function purchasePaymentValidation(payments, total) {
    let sum = exactAmount('0')
    let errors = 0
    for (const row of payments || []) {
        try {
            const amount = exactAmount(row.payment)
            if (amount.compare(0) <= 0) errors++
            else sum = sum.plus(amount)
        } catch (_) {
            errors++
        }
    }
    let exceedsTotal = true
    try {
        exceedsTotal = sum.compare(exactAmount(total)) > 0
    } catch (_) {
        errors++
    }
    return {error_by_item: errors, acum_total: sum.decimal(), exceeds_total: exceedsTotal}
}

export function showPurchaseValidationError(error, view) {
    const response = error?.response
    const data = response?.data || {}
    if (response?.status === 422) {
        const errors = data.errors || (data.message && typeof data.message === 'object' ? data.message : data)
        view.errors = errors
        const messages = Object.values(errors || {}).flatMap(value => Array.isArray(value) ? value : [value])
        const detail = messages.find(value => typeof value === 'string' && value.trim() && value !== data.message)
        view.$message.error(detail || (typeof data.message === 'string' ? data.message : null) || 'Revise los datos de la compra.')
        return
    }
    view.$message.error(response ? 'No se pudo guardar la compra. Inténtelo nuevamente.' : (error?.message || 'No se pudo guardar la compra.'))
}
