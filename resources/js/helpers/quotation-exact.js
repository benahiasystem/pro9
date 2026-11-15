// ######## INICIO CALCULOS EXACTOS COTIZACIONES ########
import {exactAmount, exactValue} from './exchange-rate-math'
import {isRecordedReceipt} from './payment-classification'

export function normalizeQuotationPrices(row, item) {
    const valid = value => {
        if (value === null || value === undefined || value === '') return false
        try { exactAmount(value); return true } catch (_) { return false }
    }
    const positive = value => valid(value) && exactAmount(value).compare(0) > 0
    const fallback = [item.unit_price, item.sale_unit_price, item.suggested_unit_price, row.unit_price].find(positive)
    item.unit_price = String(fallback ?? (valid(item.unit_price) ? item.unit_price : '0'))
    if (!valid(row.input_unit_price_value)) {
        const preferred = item.has_igv !== false ? row.unit_price : row.unit_value
        const price = [preferred, row.unit_price, item.unit_price].find(valid)
        row.input_unit_price_value = String(price ?? '0')
    }
}

export function validateQuotationPayments(payments, form) {
    let sum = exactAmount('0'), errors = 0
    for (let index = payments.length - 1; index >= 0; index--) {
        const row = payments[index]
        try {
            const amount = isRecordedReceipt(row) ? exactValue(row, 'payment') : exactAmount(row.payment)
            if (amount.compare(0) === 0 && !isRecordedReceipt(row)) payments.splice(index, 1)
            else if (amount.compare(0) <= 0) errors++
            else sum = sum.plus(amount)
        } catch (_) { errors++ }
    }
    let exceeds = true
    try {
        if (form.total == null && !form.calculation_snapshot?.values?.total) throw new Error('Total ausente')
        exceeds = sum.compare(exactValue(form, 'total')) > 0
    } catch (_) { errors++ }
    return {error_by_item: errors, acum_total: sum, exceeds_total: exceeds}
}
// ######## FIN CALCULOS EXACTOS COTIZACIONES ########
