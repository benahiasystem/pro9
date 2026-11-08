import {exactAmount, exactSum, exactValue} from './exchange-rate-math'

// The service cost is separate from `form.total`, which is the additional
// amount for items and header adjustments. Match the server's fraction math.
export function technicalServiceTotals(form, taxRate, fractions = false) {
    const rows = form.items || [], zero = () => exactAmount(0)
    const sum = (items, field) => exactSum(items.map(row => exactValue(row, field)))
    const list = value => Array.isArray(value) ? value : Object.values(value || {})
    const cost = exactAmount(form.cost || '0'), rate = exactAmount(taxRate || '0')
    const serviceBase = cost.dividedBy(exactAmount(1).plus(rate))
    const taxedRows = rows.filter(row => row.affectation_igv_type_id === '10')
    const exemptRows = rows.filter(row => row.affectation_igv_type_id === '20')
    if (taxedRows.length + exemptRows.length !== rows.length) throw new Error('Seleccione IVA gravado o exento para las partidas.')
    const taxable = serviceBase.plus(sum(taxedRows, 'total_base_igv'))
    const exempt = sum(exemptRows, 'total_base_igv')
    let distributed=zero(), distributedTaxed=zero(), distributedExempt=zero()
    for (const row of rows) for (const discount of list(row.discounts)) {
        if (!discount.from_global_distribution) continue
        const amount=exactAmount(discount.amount_exact ?? discount.amount ?? 0)
        distributed=distributed.plus(amount)
        if (discount.discount_type_id === '00') {
            if (row.affectation_igv_type_id === '20') distributedExempt=distributedExempt.plus(amount)
            else distributedTaxed=distributedTaxed.plus(amount)
        }
    }
    let discount=zero(), taxedDiscount=zero(), exemptDiscount=zero(), includesAdvance=false
    for (const row of list(form.discounts)) {
        const amount=exactAmount(row.amount_exact ?? row.amount ?? 0)
        discount=discount.plus(amount)
        if (['02','04'].includes(row.discount_type_id)) taxedDiscount=taxedDiscount.plus(amount)
        if (row.discount_type_id === '05') exemptDiscount=exemptDiscount.plus(amount)
        if (['04','05'].includes(row.discount_type_id)) includesAdvance=true
    }
    const maxZero = amount => amount.compare(0) < 0 ? zero() : amount
    discount=maxZero(discount.minus(distributed)); taxedDiscount=maxZero(taxedDiscount.minus(distributedTaxed)); exemptDiscount=maxZero(exemptDiscount.minus(distributedExempt))
    if (taxedDiscount.compare(taxable)>0 || exemptDiscount.compare(exempt)>0) throw new Error('El descuento supera su base.')
    let iva=sum(rows,'total_igv').plus(cost.minus(serviceBase))
    if (taxable.compare(0)!==0 && taxedDiscount.compare(0)!==0) iva=iva.times(taxable.minus(taxedDiscount)).dividedBy(taxable)
    const charge=exactSum(list(form.charges).map(row=>exactAmount(row.amount_exact ?? row.amount ?? 0)))
    const other=sum(rows,'total_other_taxes'), advance=exactAmount(form.total_prepayment || 0)
    const value=sum(rows,'total_value').plus(serviceBase).minus(discount).plus(charge)
    const total=value.plus(iva).plus(other).minus(includesAdvance ? 0 : advance)
    if (value.compare(0)<0 || total.compare(0)<0) throw new Error('El total no puede ser negativo.')
    const values={total_discount:sum(rows,'total_discount').plus(discount), total_charge:sum(rows,'total_charge').plus(charge),
        total_exportation:zero(), total_free:zero(), total_taxed:taxable.minus(taxedDiscount),
        total_unaffected:zero(), total_exonerated:exempt.minus(exemptDiscount), total_igv:iva,
        total_igv_free:zero(), total_base_other_taxes:zero(), total_other_taxes:other,
        total_taxes:iva.plus(other), total_value:value, subtotal:sum(rows,'total'), total:total.minus(cost)}
    return Object.fromEntries(Object.entries(values).map(([key, amount])=>[key,fractions ? amount.fraction() : amount.decimal()]))
}
