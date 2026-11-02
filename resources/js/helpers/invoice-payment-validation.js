import {exactAmount} from './exchange-rate-math'
import {isRecordedReceipt} from './payment-classification'

// Validate applied amounts without changing a recorded receipt or losing decimal precision.
export function validateInvoicePayments(rows, total, methods) {
    let sum = exactAmount('0')
    let availableCash = exactAmount('0')
    const inputs = []
    try {
        const target = exactAmount(total)
        if (target.compare(0) < 0) throw new Error('Total inválido')
        for (const row of rows) {
            const amount = exactAmount(row.payment)
            const recorded = isRecordedReceipt(row)
            if (amount.compare(0) === 0 && !recorded) continue
            if (amount.compare(0) <= 0) throw new Error('Cobro inválido')
            inputs.push(row)
            sum = sum.plus(amount)
            const method = (methods || []).find(item => String(item.id) === String(row.payment_method_type_id))
            if (!recorded && method?.collection_kind === 'cash') availableCash = availableCash.plus(amount)
        }
        return {inputs, error_by_item: sum.minus(target).compare(availableCash) > 0 ? 1 : 0, acum_total: sum}
    } catch (error) {
        return {inputs: rows, error_by_item: 1, acum_total: sum}
    }
}
