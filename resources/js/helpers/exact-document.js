import {exactAmount, exactValue, exactSum, exactSnapshot} from './exchange-rate-math'

// ######## INICIO CALCULOS EXACTOS PRO9 ########
export function documentTotals(items, discounts = [], charges = [], prepayment = '0') {
    const sum = (rows, field) => exactSum(rows.map(row => exactValue(row, field)))
    const amount = row => exactAmount(row.amount_exact ?? row.amount ?? 0)
    const distributed = exactSum(items.flatMap(row => (row.discounts || []).filter(d => d.from_global_distribution).map(amount)))
    const nonnegative = value => value.compare(0) < 0 ? exactAmount(0) : value
    const global = nonnegative(exactSum((discounts || []).map(amount)).minus(distributed))
    const globalCharge = exactSum((charges || []).map(amount))
    let taxed = sum(items.filter(row => row.affectation_igv_type_id === '10'), 'total_base_igv')
    let exempt = sum(items.filter(row => row.affectation_igv_type_id === '20'), 'total_base_igv')
    let iva = sum(items, 'total_igv')
    const taxedDiscount = nonnegative(exactSum((discounts || []).filter(row => ['02','04'].includes(row.discount_type_id)).map(amount)).minus(distributed))
    const exemptDiscount = exactSum((discounts || []).filter(row => row.discount_type_id === '05').map(amount))
    if (taxed.compare(0) > 0 && taxedDiscount.compare(0) > 0) iva = iva.times(taxed.minus(taxedDiscount)).dividedBy(taxed)
    taxed = taxed.minus(taxedDiscount); exempt = exempt.minus(exemptDiscount)
    const value = sum(items, 'total_value').minus(global).plus(globalCharge)
    const other = sum(items, 'total_other_taxes')
    const taxes = iva.plus(other)
    const advance = (discounts || []).some(row => ['04','05'].includes(row.discount_type_id)) ? '0' : prepayment
    const total = value.plus(taxes).minus(advance || 0)
    const values = {total_taxed: taxed, total_exonerated: exempt, total_igv: iva,
        total_value: value, total_taxes: taxes, total_other_taxes: other,
        total_discount: sum(items, 'total_discount').plus(global), total_charge: sum(items, 'total_charge').plus(globalCharge),
        total_exportation: sum(items.filter(row => row.affectation_igv_type_id === 'EXP'), 'total_value').minus(items.some(row => row.affectation_igv_type_id === 'EXP') ? global : 0).plus(items.some(row => row.affectation_igv_type_id === 'EXP') ? globalCharge : 0),
        subtotal: total, total, total_payable: exactAmount(total.final(2)), settlement_adjustment: exactAmount(total.final(2)).minus(total)}
    return {...Object.fromEntries(Object.entries(values).map(([field, value]) => [field, value.decimal()])), calculation_snapshot: exactSnapshot(values)}
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
