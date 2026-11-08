import {exactAmount, exactInput, exactSnapshot, exactValue} from './exchange-rate-math'
import {documentTotals} from './exact-document'

// Reconstruct the server's commercial calculation without converting inputs to Number.
export function storefrontLine(row, currentRate) {
    const quantityText = exactInput(String(row.quantity ?? ''))
    const priceText = exactInput(String(row.unit_price ?? ''))
    const percentageText = exactInput(String(row.discount_percentage ?? '0'))
    const pct = exactAmount(percentageText)
    if (exactAmount(quantityText).compare(0) <= 0 || exactAmount(priceText).compare(0) <= 0 || pct.compare(0) < 0 || pct.compare(100) > 0) {
        throw new Error('Revise cantidad, precio unitario y descuento de cada producto.')
    }
    if (!['10', '20', 'EXP'].includes(row.affectation_igv_type_id)) throw new Error('Seleccione una afectación de IVA vigente.')
    const sameQuantity = exactAmount(quantityText).compare(row.original_quantity) === 0
    const samePrice = exactAmount(priceText).compare(row.original_unit_price) === 0
    const sameDiscount = pct.compare(row.original_discount_percentage) === 0
    const editable = (row.discounts || []).filter(d => d.discount_type_id === '00' && !d.from_global_distribution)
    if (editable.length > 1 || editable.some(d => d.is_amount)) throw new Error('Esta partida tiene descuentos que requieren la edición completa.')
    if (sameQuantity && samePrice && sameDiscount && row.calculation_snapshot) return row
    const quantity = sameQuantity ? exactValue(row, 'quantity') : exactAmount(quantityText)
    const price = samePrice ? exactValue(row, 'unit_price') : exactAmount(priceText)
    const rate = exactAmount(row.affectation_igv_type_id === '10' ? currentRate : '0')
    const gross = price.dividedBy(exactAmount(1).plus(rate.dividedBy(100))).times(quantity)
    let baseDiscount = exactAmount(0), otherDiscount = exactAmount(0), charges = exactAmount(0)
    let discounts = row.discounts || []
    if (!sameDiscount) {
        discounts = discounts.filter(d => d.discount_type_id !== '00' || d.from_global_distribution)
        if (pct.compare(0) > 0) discounts = [...discounts, {discount_type_id: '00', percentage: percentageText, is_amount: false}]
    }
    discounts = discounts.map(discount => {
        const base = discount.discount_type_id === '00' ? gross : price.times(quantity)
        const factor = discount.percentage != null ? exactAmount(discount.percentage).dividedBy(100) : exactAmount(discount.factor || 0)
        const amount = discount.is_amount || discount.from_global_distribution
            ? exactAmount(discount.amount_exact ?? discount.amount_without_rounded ?? discount.amount ?? 0) : base.times(factor)
        if (discount.discount_type_id === '00') baseDiscount = baseDiscount.plus(amount)
        else otherDiscount = otherDiscount.plus(amount)
        return {...discount, amount: amount.decimal(), amount_exact: amount.fraction()}
    })
    const lineCharges = (row.charges || []).map(charge => {
        const factor = charge.percentage != null ? exactAmount(charge.percentage).dividedBy(100) : exactAmount(charge.factor || 0)
        const amount = gross.times(factor); charges = charges.plus(amount)
        return {...charge, amount: amount.decimal(), amount_exact: amount.fraction()}
    })
    const base = gross.minus(baseDiscount), value = base.minus(otherDiscount).plus(charges)
    if (base.compare(0) < 0 || value.compare(0) < 0) throw new Error('El descuento supera el valor de la línea.')
    const iva = base.times(rate).dividedBy(100)
    const values = {quantity, unit_price: price, total_base_igv: base, total_value: value,
        total_discount: baseDiscount.plus(otherDiscount), total_charge: charges, total_igv: iva,
        total_taxes: iva, total_other_taxes: exactAmount(0), total: value.plus(iva)}
    return {...row, discounts, charges: lineCharges, calculation_snapshot: exactSnapshot(values)}
}

export function storefrontPreview(items, quotation) {
    const lines = items.map(row => storefrontLine(row, quotation.current_percentage_igv))
    return {lines, totals: documentTotals(lines, quotation.discounts || [], quotation.charges || [], quotation.total_prepayment || '0')}
}
