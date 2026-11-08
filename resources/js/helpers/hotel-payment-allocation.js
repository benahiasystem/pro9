import {appliedReceiptAmount, exactAmount, exactSum, exactValue} from './exchange-rate-math'
import {paymentMethodCurrency} from './payment-classification'

// Preserve foreign tender and distribute only the remaining domestic cents.
export function allocateHotelPayments(items, methods, currency = 'VES') {
    if (!items.length) return false
    const currencies=items.map(row => paymentMethodCurrency(methods, row.rent_payment?.payment_method_type_id))
    if (currencies.some(value => !value)) return false
    const weights=items.map(row => exactValue(row, 'total'))
    if (weights.some(value => value.compare(0) <= 0)) throw new Error('Cada partida pagada debe tener un importe positivo.')
    const target=exactAmount(exactSum(weights).final(2))
    const domestic=[]; const foreign=[]
    const bounds=value => {
        const scaled=value.times('100'), floor=scaled.n / scaled.d
        return {floor, remainder:scaled.minus(floor.toString())}
    }
    items.forEach((row,index) => {
        const payment=row.rent_payment
        const bound=bounds(weights[index])
        if (currencies[index]===currency) { domestic.push({index,...bound}); return }
        if (payment.currency_type_id && payment.currency_type_id!==currencies[index]) throw new Error('Revise la moneda recibida del cobro.')
        const applied=appliedReceiptAmount({...payment,currency_type_id:currencies[index]},currency)
        if (exactAmount(payment.payment).compare(applied)!==0) throw new Error('Revise el importe recibido y su tasa antes de guardar.')
        const cents=applied.times('100')
        const ceiling=bound.floor+(bound.remainder.compare(0)>0 ? BigInt(1) : BigInt(0))
        if (cents.n % cents.d !== BigInt(0) || cents.n / cents.d < bound.floor || cents.n / cents.d > ceiling) {
            throw new Error('El importe recibido en moneda extranjera no cubre la partida con el redondeo permitido.')
        }
        foreign.push(applied)
    })
    const remaining=target.minus(exactSum(foreign))
    const scaled=remaining.times('100')
    if (scaled.n % scaled.d !== BigInt(0)) throw new Error('No se puede repartir el saldo entre los cobros.')
    let extra=scaled.n / scaled.d-domestic.reduce((sum,row)=>sum+row.floor,BigInt(0))
    const order=[...domestic].filter(row=>row.remainder.compare(0)>0)
        .sort((a,b)=>b.remainder.compare(a.remainder) || a.index-b.index)
    if (extra < BigInt(0) || extra > BigInt(order.length)) throw new Error('Ajuste los importes recibidos para cubrir exactamente el total.')
    for (const row of order) { if (extra===BigInt(0)) break; row.floor+=BigInt(1); extra-=BigInt(1) }
    const amounts=domestic.map(row=>exactAmount(row.floor.toString()).dividedBy('100').final(2))
    if (amounts.some(amount=>exactAmount(amount).compare(0)<=0)) throw new Error('Cada cobro debe ser de al menos un centavo.')
    domestic.forEach((row,index) => {
        const payment=items[row.index].rent_payment
        payment.payment=amounts[index]; payment.original_amount=amounts[index]; payment.currency_type_id=currency
    })
    return true
}
