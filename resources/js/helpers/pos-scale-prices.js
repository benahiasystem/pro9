import {exactAmount} from './exchange-rate-math'
import {calculateRowItem} from './functions'

export function posScalePrice(prices, documentType) {
    return prices[documentType === '01' ? 'invoice' : 'saleNote']
}

export function samePosScalePrices(left, right) {
    if (!left || !right) return !left && !right
    return left.invoice.n === right.invoice.n && left.invoice.d === right.invoice.d &&
        left.saleNote.n === right.saleNote.n && left.saleNote.d === right.saleNote.d
}

// The drawer can change document type after the barcode has been cleared.
export function applyPosScalePrices(form, percentageIgv) {
    if (!form.items?.some(row => row._pos_scale_prices)) return false

    let changed = false
    const items = form.items.map(row => {
        if (!row._pos_scale_prices) return row

        const amount = posScalePrice(row._pos_scale_prices, form.document_type_id)
        if (exactAmount(row.price_input.amount).compare(amount) === 0) return row
        const unitPrice = exactAmount(amount).decimal()
        changed = true
        return calculateRowItem({
            ...row,
            item: { ...row.item, unit_price: unitPrice },
            unit_price: unitPrice,
            price_input: { ...row.price_input, amount },
        }, form.currency_type_id, form.exchange_rate_sale, percentageIgv)
    })
    if (changed) form.items = items
    return changed
}

export function withoutPosScalePrices(form) {
    if (!form.items?.some(row => row._pos_scale_prices)) return form

    return {...form, items: form.items.map(row => {
        if (!row._pos_scale_prices) return row
        const {_pos_scale_prices, ...line} = row
        return line
    })}
}
