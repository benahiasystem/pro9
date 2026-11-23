import {calculateRowItem} from './functions'
import {documentTotals} from './exact-document'
import {exactAmount, exactValue} from './exchange-rate-math'
import {convertExact} from './local-payment-quote'
import {isRecordedReceipt} from './payment-classification'

// Prepare the entire new draft before the caller changes the live form.
export function preparePosCurrencyConversion(form, target, percentageIgv, forceReprice = false) {
    if (!['VES', 'USD'].includes(target) || !['VES', 'USD'].includes(form.currency_type_id)) throw new Error('Seleccione una moneda válida.')
    if (!Array.isArray(form.items) || (form.payments != null && !Array.isArray(form.payments))) throw new Error('El borrador contiene partidas o cobros inválidos.')
    const previous = form.currency_type_id
    const rate = form.exchange_rate_sale
    const needsConversion = forceReprice || target !== previous || form.items.some(row => row.currency_type_id !== target)
    if (forceReprice && target === previous) {
        for (const receipt of form.payments || []) {
            if (receipt.currency_type_id && receipt.currency_type_id !== target) {
                if (receipt.original_amount == null || receipt.exchange_rate == null) throw new Error('El cobro no conserva el importe y la tasa de recepción.')
                const expected = convertExact(receipt.original_amount, receipt.currency_type_id, target, receipt.exchange_rate).final(2)
                if (exactAmount(receipt.payment).compare(expected) !== 0 || exactValue(receipt, 'payment').compare(expected) !== 0) throw new Error('El cobro no coincide con su importe y tasa de recepción.')
            }
        }
    }
    if (needsConversion && form.calculation_snapshot != null &&
        (form.calculation_snapshot.version !== 1 || form.calculation_snapshot.values?.total_prepayment == null)) {
        throw new Error('El anticipo no conserva su valor exacto.')
    }
    if (form.items.some(row => {
        const input = row.price_input ?? row.calculation_snapshot?.inputs?.price_input
        if (forceReprice && row.calculation_snapshot && input == null) return true
        const source = input != null ? input.currency_type_id
            : row.calculation_snapshot ? row.currency_type_id : (row.item?.currency_type_id || row.currency_type_id)
        return !['VES', 'USD'].includes(source)
    })) throw new Error('Una partida no conserva la moneda de su precio original.')
    if (needsConversion && (
        (form.discounts || []).length || (form.charges || []).length ||
        (form.prepayments && Object.keys(form.prepayments).length) ||
        exactAmount(form.total_prepayment || '0').compare('0') !== 0 || exactValue(form, 'total_prepayment').compare('0') !== 0 ||
        form.items.some(row => [...(row.discounts || []), ...(row.charges || [])]
            .some(adjustment => adjustment.is_amount || adjustment.from_global_distribution))
    )) throw new Error('Retire los ajustes o anticipos antes de cambiar la moneda del documento.')
    const items = form.items.map(row => ({
        ...calculateRowItem(row, target, rate, percentageIgv), unit_type_id: row.unit_type_id,
    }))
    const payments = target === previous ? form.payments : (form.payments || []).map(row => {
        if (isRecordedReceipt(row)) throw new Error('El cobro registrado conserva la moneda del comprobante.')
        if (row.calculation_snapshot != null) throw new Error('El cobro conserva un cálculo previo y no puede cambiarse automáticamente de moneda.')
        const receivedCurrency = row.currency_type_id || previous
        if (receivedCurrency !== previous) {
            if (row.original_amount == null || row.exchange_rate == null) throw new Error('El cobro no conserva el importe y la tasa de recepción.')
            const applied = convertExact(row.original_amount, receivedCurrency, previous, row.exchange_rate).final(2)
            if (exactAmount(row.payment).compare(applied) !== 0) throw new Error('El cobro no coincide con su importe y tasa de recepción.')
        }
        const receiptRate = row.exchange_rate ?? rate
        const original = row.original_amount ?? row.tendered_amount ?? row.payment ?? '0'
        if (receivedCurrency === previous && exactAmount(original).compare(row.payment) !== 0) throw new Error('El importe recibido y el aplicado no coinciden; revise el cobro antes de cambiar la moneda.')
        return {...row, currency_type_id: receivedCurrency, exchange_rate: receiptRate,
            original_amount: original, payment: convertExact(original, receivedCurrency, target, receiptRate).final(2)}
    })
    const totals = documentTotals(items, form.discounts, form.charges, form.total_prepayment, form.prepayments)
    return {...totals, currency_type_id: target, items, payments}
}
