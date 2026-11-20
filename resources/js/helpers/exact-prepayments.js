import {exactAmount, exactInput, exactSnapshot, exactSum, exactValue} from './exchange-rate-math'

// An edited decimal supersedes its old fraction; an unchanged representation retains it.
export function prepaymentValue(row, field) {
    const scalar = exactAmount(row[field] ?? '0')
    const original = row.calculation_snapshot?.version === 1 ? row.calculation_snapshot.values?.[field] : null
    return original && scalar.compare(exactAmount(original).decimal()) === 0 ? exactAmount(original) : scalar
}

function referenceRow(row, amount, total) {
    return {...row, amount: amount.decimal(), total: total.decimal(),
        calculation_snapshot: exactSnapshot({amount, total})}
}

export function selectPrepayment(row, selected) {
    if (!selected) throw new Error('Seleccione un anticipo disponible.')
    return referenceRow({...row, number: selected.description, document_type_id: selected.document_type_id},
        prepaymentValue(selected, 'amount'), prepaymentValue(selected, 'total'))
}

export function updatePrepaymentInput(row, selected, affectation, rate, enteredAmount) {
    if (!selected) throw new Error('Seleccione un anticipo disponible.')
    const input = exactInput(String(enteredAmount ?? row.amount ?? '').trim() || '0')
    let amount = enteredAmount === undefined ? prepaymentValue({...row, amount: input}, 'amount') : exactAmount(input)
    if (amount.compare(0) < 0) throw new Error('El importe del anticipo no puede ser negativo.')
    const available = prepaymentValue(selected, 'amount')
    const clamped = amount.compare(available) > 0
    if (clamped) amount = available
    let total = String(affectation) === '10' ? amount.times(exactAmount(1).plus(rate)) : amount
    if (selected.total != null && amount.compare(available) === 0) total = prepaymentValue(selected, 'total')
    return {row: referenceRow(row, amount, total), clamped}
}

export function applyPrepaymentDiscount(form, originalBase = null) {
    const rows = form.prepayments || []
    const amount = exactSum(rows.map(row => prepaymentValue(row, 'amount')))
    const total = exactSum(rows.map(row => prepaymentValue(row, 'total')))
    if (amount.compare(0) < 0 || total.compare(0) < 0) throw new Error('El importe del anticipo no puede ser negativo.')
    const taxed = String(form.affectation_type_prepayment) === '10'
    const type = taxed ? '04' : '05'
    const discounts = (form.discounts || []).filter(row => !['04', '05'].includes(row.discount_type_id))
    const base = originalBase ? exactValue(originalBase, taxed ? 'total_taxed' : 'total_exonerated')
        : exactSum((form.items || []).filter(row => row.affectation_igv_type_id === (taxed ? '10' : '20'))
            .map(row => exactValue(row, 'total_base_igv')))
    if (amount.compare(base) > 0) throw new Error('El anticipo supera la base disponible del documento.')
    if (amount.compare(0) > 0 && base.compare(0) > 0) discounts.push({discount_type_id: type,
        description: 'Descuento por anticipos', amount: amount.decimal(), amount_exact: amount.fraction(),
        base: base.decimal(), factor: amount.dividedBy(base).decimal(), is_amount: true})
    form.discounts = discounts
    form.total_prepayment = total.decimal()
    return form
}
