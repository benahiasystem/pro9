import {exactAmount, exactInput, exactValue, exactSum, allocateExact} from './exchange-rate-math'
import {documentTotals} from './exact-document'

// ######## INICIO CALCULOS EXACTOS PRO9 ########
// Rebuild headers from line fractions. Calling this repeatedly never compounds adjustments.
export function commercialTotals(form, options = {}) {
    const raw = documentTotals(form.items || [])
    const type = options.discountType || '02'
    let discounts = (form.discounts || []).filter(row => row.discount_type_id !== type)
    let charges = (form.charges || []).filter(row => row.charge_type_id !== '50')
    if (options.discount !== undefined && options.discountEnabled !== false) {
        const base = exactValue(raw, ['02','04'].includes(type) ? 'total_taxed' : 'total')
        let amount = exactAmount(options.discount || '0')
        if (options.discountIsAmount === false) amount = base.times(amount).dividedBy(100)
        else if (options.discountIncludesIva && type === '02' && base.compare(0) > 0) {
            amount = amount.dividedBy(exactAmount(1).plus(exactValue(raw, 'total_igv').dividedBy(base)))
        }
        if (amount.compare(0) < 0 || amount.compare(base) > 0) throw new Error('El descuento supera su base o es negativo.')
        if (amount.compare(0) > 0) discounts.push({discount_type_id: type, description: options.discountDescription || 'Descuento global',
            is_amount: true, amount: amount.decimal(), amount_exact: amount.fraction(),
            base: base.decimal(), factor: amount.dividedBy(base).decimal()})
    } else if (options.discount === undefined) discounts = form.discounts || []
    const base = exactValue(raw, 'total')
    const charge = options.chargePercentage !== undefined
        ? base.times(options.chargePercentage).dividedBy(100) : exactAmount(options.charge || '0')
    if (charge.compare(0) < 0) throw new Error('Cargo inválido.')
    if (charge.compare(0) > 0) charges.push({charge_type_id: '50', description: 'Cargos globales',
        amount: charge.decimal(), amount_exact: charge.fraction(), base: base.decimal(),
        factor: base.compare(0) > 0 ? charge.dividedBy(base).decimal() : '0'})
    if (options.charge === undefined && options.chargePercentage === undefined) charges = form.charges || []
    return {...documentTotals(form.items || [], discounts, charges, form.total_prepayment), discounts, charges}
}

export function applyCommercialTotals(vm) {
    const config = vm.config || vm.configuration || {}
    const type = vm.global_discount_type || {}
    const totals = commercialTotals(vm.form, {
        discount: vm.total_global_discount, discountEnabled: vm.enabled_discount_global,
        discountType: type.id || config.global_discount_type_id || '02', discountDescription: type.description,
        discountIsAmount: vm.is_amount, discountIncludesIva: config.exact_discount,
        charge: vm.total_global_charge,
        chargePercentage: config.active_allowance_charge ? config.percentage_allowance_charge : undefined,
    })
    Object.assign(vm.form, totals)
    vm.total_discount_no_base = exactSum((vm.form.items || []).flatMap(row => (row.discounts || [])
        .filter(discount => discount.discount_type_id === '01').map(discount => discount.amount_exact ?? discount.amount))).decimal()
    if (vm.form_payment) vm.form_payment.payment = totals.total_payable
    if ('showPayments' in vm) vm.showPayments = vm.form.items.length > 0
    return totals
}

export function allocateCommercialRows(rows, field, total) {
    const amounts = allocateExact(total, rows.map(() => '1'), 2)
    rows.forEach((row, index) => {
        row[field] = amounts[index]
        if (field === 'amount') row.allocation_weight = '1'
    })
}

// The requested strings remain inputs; this preview never submits net cash as tender.
export function prepareCommercialPayments(payable, rows) {
    const target = exactAmount(payable)
    if (target.compare(0) < 0 || target.compare(target.final(2)) !== 0) throw new Error('Importe liquidable inválido')
    const inputs = rows.map(row => {
        if (typeof row.payment === 'number' && !Number.isSafeInteger(row.payment)) throw new Error('Transmita el decimal como texto')
        return {...row, payment: exactInput(String(row.payment ?? ''))}
    })
        .filter(row => exactAmount(row.payment).compare(0) !== 0)
    const received = inputs.map(row => {
        const requested = exactAmount(row.payment), cash = exactAmount(requested.final(2))
        if (requested.compare(0) < 0 || cash.compare(0) <= 0) throw new Error('Cobro no liquidable en céntimos')
        return cash
    })
    const total = exactSum(received)
    let change = total.minus(target)
    if (change.compare(0) < 0) change = exactAmount('0')
    const cash = exactSum(received.filter((_, index) => ['01','10'].includes(inputs[index].payment_method_type_id)))
    if (change.compare(cash) > 0) throw new Error('El exceso sólo puede devolverse desde el efectivo recibido')
    let remaining = change
    const payments = inputs.map((row, index) => {
        const returned = ['01','10'].includes(row.payment_method_type_id)
            ? (remaining.compare(received[index]) < 0 ? remaining : received[index]) : exactAmount('0')
        remaining = remaining.minus(returned)
        return {...row, payment: received[index].minus(returned).decimal(), change: returned.decimal()}
    })
    return {inputs, payments, total, change}
}

export function checkoutTotals(vm) {
    const config=vm.configuration || vm.config || {}
    Object.assign(vm.form,commercialTotals(vm.form,{
        discount: vm.enabled_discount ? vm.discount_amount || '0' : '0',
        discountType:config.global_discount_type_id || '03',
        discountIsAmount:vm.discount_type !== '02', discountIncludesIva:config.exact_discount,
    }))
    if (vm.setTotalPointsBySale) vm.setTotalPointsBySale(config)
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
