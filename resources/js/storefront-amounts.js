import {exactAmount, exactInput, exactSum} from './helpers/exchange-rate-math'
import {cartQuantity, cartLineAmount} from './helpers/storefront-cart-amounts'

// The classic catalog scripts and Vue checkout use the same rational engine.
// Persist decimal strings/fractions, never ExactAmount instances containing BigInt.
window.Pro9StorefrontAmounts = Object.freeze({
    amount: exactAmount, input: exactInput, sum: exactSum, quantity: cartQuantity, line: cartLineAmount,
    money: value => exactAmount(value).final(2),
    stock(item) {
        let value = item.stock_max ?? item.stock
        if (value == null || value === '') {
            if (!Array.isArray(item.warehouses) || !item.warehouses.length) return null
            value = exactSum(item.warehouses.map(row => exactInput(String(row.stock)))).decimal()
        }
        const amount = exactAmount(exactInput(String(value)))
        return amount.compare('0') < 0 ? '0' : amount.decimal()
    },
    total(records) {
        // Header totals retain the currencies instead of adding VES and USD.
        const totals = new Map()
        for (const row of records) {
            const currency = row.currency_type_id || 'VES'
            if (!['VES', 'USD'].includes(currency)) throw new Error('El producto requiere una moneda vigente.')
            const total = cartLineAmount(row, {convert: false}).total
            totals.set(currency, (totals.get(currency) || exactAmount('0')).plus(total.fraction()))
        }
        return [...totals].map(([currency, total]) => (currency === 'USD' ? 'USD ' : 'Bs. ') + total.final(2)).join(' + ') || 'Bs. 0.00'
    },
})
