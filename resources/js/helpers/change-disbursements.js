// ######## INICIO REPARTO DE VUELTO ########
import {exactAmount, normalizeExchangeRate} from './exchange-rate-math'
import {classifiedDestinations} from './payment-classification'

export function eligibleChangeMethods(methods = [], destinations = [], establishmentId = null) {
    return methods.filter(method => method && [true, 1, '1'].includes(method.is_active)
        && (method.collection_kind === 'cash' || [true, 1, '1'].includes(method.available_for_change))
        && ['cash', 'account'].includes(method.collection_kind)
        && ['VES', 'USD'].includes(method.currency_type_id)
        && classifiedDestinations(destinations, methods, {payment_method_type_id:method.id, currency_type_id:method.currency_type_id}, method.currency_type_id)
            .some(destination => destination.id === 'cash' || (establishmentId != null && String(destination.change_establishment_id ?? destination.establishment_id) === String(establishmentId))))
}

export function defaultChangeDisbursements(receipt, methods = [], destinations = [], establishmentId = null) {
    try {
        if (!receipt || receipt.reused || exactAmount(receipt.change || '0').compare(0) <= 0) return null
        const eligible = eligibleChangeMethods(methods, destinations, establishmentId)
        const cash = eligible.find(method => method.collection_kind === 'cash' && method.currency_type_id === receipt.currency_type_id)
            || eligible.find(method => method.collection_kind === 'cash')
        if (!cash) return null
        const amount = exactAmount(receipt.change), rate = normalizeExchangeRate(receipt.exchange_rate)
        const converted = cash.currency_type_id === receipt.currency_type_id ? amount
            : cash.currency_type_id === 'USD' ? amount.dividedBy(rate) : amount.times(rate)
        const lines = [{payment_method_type_id:cash.id, amount:converted.final(2), reference:''}]
        return validateChangeDisbursements(lines, receipt, methods, destinations, establishmentId).valid ? lines : null
    } catch (_) { return null }
}

export function validateChangeDisbursements(lines, receipt, methods = [], destinations = [], establishmentId = null) {
    try {
        if (lines == null && (!receipt || receipt.reused || exactAmount(receipt.change || '0').compare(0) === 0)) return {valid:true}
        if (lines == null) throw new Error('Seleccione los métodos para dar el vuelto.')
        if (!Array.isArray(lines) || !lines.length || lines.length > 20 || !receipt || receipt.reused) throw new Error('Indique los métodos y montos del vuelto.')
        const target = exactAmount(receipt.change), rate = normalizeExchangeRate(receipt.exchange_rate)
        if (target.compare(0) <= 0) throw new Error('El cobro ya no tiene vuelto. Revise su distribución.')
        const eligible = eligibleChangeMethods(methods, destinations, establishmentId)
        let sum = exactAmount('0')
        for (const line of lines) {
            const method = eligible.find(item => String(item.id) === String(line.payment_method_type_id))
            if (!method) throw new Error('Seleccione un método disponible para cada desembolso.')
            const amount = exactAmount(line.amount)
            if (amount.compare(0) <= 0 || amount.compare(amount.final(2)) !== 0) throw new Error('Indique importes positivos con hasta dos decimales.')
            if (line.reference != null && (typeof line.reference !== 'string' || line.reference.length > 255)) throw new Error('La referencia del desembolso no es válida.')
            const converted = method.currency_type_id === receipt.currency_type_id ? amount
                : method.currency_type_id === 'USD' ? amount.times(rate) : amount.dividedBy(rate)
            sum = sum.plus(converted)
        }
        if (exactAmount(sum.final(2)).compare(target) !== 0) throw new Error('Los montos seleccionados deben cubrir el vuelto.')
        return {valid:true}
    } catch (error) { return {valid:false, message:error.message || 'Revise los métodos de vuelto.'} }
}
// ######## FIN REPARTO DE VUELTO ########
