
// ######## INICIO TASAS OCHO DECIMALES ########
import { exactAmount, exactSnapshot, exactValue } from "./exchange-rate-math"
// ######## FIN TASAS OCHO DECIMALES ########
// ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########
// ########## INICIO CAMBIO AFECTACIÓN IVA
// ######## INICIO CALCULOS EXACTOS PRO9 ########
function calculateRowItem(old, currency, rate, pigv = '0.16', exportation = false) {
// ######### FIN CAMBIO AFECTACIÓN IVA
    if (!(exportation ? ['EXP'] : ['10', '20']).includes(old.affectation_igv_type_id)) throw new Error('Seleccione una afectación de IVA vigente: Gravado o Exento.')
    const inputs = old.calculation_snapshot?.inputs || {}
    const unchangedQuantity = old.calculation_snapshot && exactValue(old, 'quantity').decimal() === exactAmount(old.quantity).decimal()
    const quantityInput = old.quantity_input ?? (unchangedQuantity ? inputs.quantity_input : null) ?? null
    const priceInput = old.price_input ?? inputs.price_input ?? (!old.calculation_snapshot
        ? {amount: String(old.item.unit_price), currency_type_id: old.item.currency_type_id || old.currency_type_id || currency, includes_iva: true} : null)
    const sourceCurrency = priceInput ? priceInput.currency_type_id : old.calculation_snapshot ? (old.currency_type_id || currency) : old.item.currency_type_id
    let price = priceInput ? exactAmount(priceInput.amount) : old.calculation_snapshot ? exactValue(old, 'unit_price') : exactAmount(old.item.unit_price)
    if (priceInput?.includes_iva === false && old.affectation_igv_type_id === '10') price = price.times(exactAmount(1).plus(pigv))
    if (sourceCurrency !== currency) price = sourceCurrency === 'VES' ? price.dividedBy(rate) : price.times(rate)
    const quantity = quantityInput
        ? exactAmount(quantityInput.amount).dividedBy(quantityInput.unit_price)
        : unchangedQuantity
            ? exactValue(old, 'quantity') : exactAmount(old.quantity)
    const percentage = old.affectation_igv_type_id === '10' ? exactAmount(pigv).times(100) : exactAmount(0)
    const unit = price.dividedBy(exactAmount(1).plus(percentage.dividedBy(100)))
    const gross = unit.times(quantity)
    let discountBase = exactAmount(0), discountOther = exactAmount(0), charges = exactAmount(0)
    const discounts = (old.discounts || []).map(source => {
        const discount = {...source}
        const onBase = discount.discount_type_id === '00' || discount.discount_type?.base
        const base = onBase ? gross : price.times(quantity)
        const factor = discount.percentage != null ? exactAmount(discount.percentage).dividedBy(100) : exactAmount(discount.factor || 0)
        const amount = discount.is_amount || discount.from_global_distribution
            ? exactAmount(discount.amount_exact ?? discount.amount_without_rounded ?? (discount.use_input_amount === false ? discount.percentage : discount.amount ?? discount.percentage) ?? 0)
            : base.times(factor)
        if (amount.compare(0) < 0) throw new Error('Descuento inválido')
        if (onBase) discountBase = discountBase.plus(amount); else discountOther = discountOther.plus(amount)
        return {...discount, base: base.decimal(), amount: amount.decimal(), amount_exact: amount.fraction(), factor: factor.decimal()}
    })
    const lineCharges = (old.charges || []).map(charge => {
        const factor = charge.percentage != null ? exactAmount(charge.percentage).dividedBy(100) : exactAmount(charge.factor || 0)
        const amount = gross.times(factor)
        if (amount.compare(0) < 0) throw new Error('Cargo inválido')
        charges = charges.plus(amount)
        return {...charge, base: gross.decimal(), amount: amount.decimal(), amount_exact: amount.fraction()}
    })
    const base = gross.minus(discountBase), value = base.minus(discountOther).plus(charges)
    if (base.compare(0) < 0 || value.compare(0) < 0) throw new Error('El descuento supera el valor de la línea')
    const iva = base.times(percentage).dividedBy(100)
    const values = {quantity, unit_price: price, unit_value: unit, percentage_igv: percentage,
        total_base_igv: base, total_value: value, total_discount: discountBase.plus(discountOther), total_charge: charges,
        total_igv: iva, total_taxes: iva, total_other_taxes: exactAmount(0), total_base_other_taxes: exactAmount(0), percentage_other_taxes: exactAmount(0), total: value.plus(iva)}
    const result = {...old, item_id: old.item.id, currency_type_id: currency, discounts, charges: lineCharges,
        quantity_input: quantityInput, price_input: priceInput,
        price_type_id: '01', record_id: old.record_id || old.id || null,
        calculation_snapshot: exactSnapshot(values, {quantity: String(old.quantity), unit_price: String(old.unit_price ?? old.item.unit_price), quantity_input: quantityInput, price_input: priceInput}),
        data_item_lot_group: getDataItemLotGroup(old)}
    for (const [field, value] of Object.entries(values)) result[field] = value.decimal()
    for (const field of ['total_value', 'total_base_igv', 'total_igv', 'total_taxes', 'total']) result[field + '_without_rounding'] = result[field]
    return result
}
// ######## FIN CALCULOS EXACTOS PRO9 ########


/*
* Monto de descuento por linea
* Retorna el valor dependiendo del input asignado para monto de descuento (use_input_amount)
* use_input_amount propiedad para determinar si se toma el valor de discount.amount
* y no de discount.percentage cuando es descuento por monto
*/
function getDiscountBaseIncrement(discount) {
    return exactAmount(discount.amount_exact ?? discount.amount ?? '0').decimal()
}

function getAmountFromInputDiscount(discount) {
    return exactAmount(discount.use_input_amount === true ? discount.amount : discount.percentage).decimal()
}

function getUniqueArray(arr, keyProps) {
    if (arr == null) return null
    return Object.values(
        arr.reduce((uniqueMap, entry) => {
            const key = keyProps.map((k) => entry[k]).join('|')
            if (!(key in uniqueMap)) uniqueMap[key] = entry
            return uniqueMap
        }, {})
    )
}

function showNamePdfOfDescription(item, show_pdf_name) {
    if (show_pdf_name !== undefined &&
        show_pdf_name === true &&
        item !== undefined &&
        item.name_product_pdf !== undefined &&
        item.name_product_pdf !== null
    ) {
        let temn = item.name_product_pdf;
        temn = temn.substring(3)
        temn = temn.slice(0, -4)
        if (temn.length > 0) {
            return temn;
        }
    }
    return item.description
}

function sumAmountDiscountsNoBaseByItem(row) {
    return (row.discounts || []).filter(discount => discount.discount_type_id === '01')
        .reduce((sum, discount) => sum.plus(discount.amount_exact ?? discount.amount ?? '0'), exactAmount(0)).decimal()
}

function FormatUnitPriceRow(unit_price) {
    return exactAmount(unit_price).decimal()
}

const filterWords = (input, items) => {
    let search_value = input.toLowerCase();
    return items.filter((item) => {
        let text_filter = item.text_filter.toLowerCase();
        return !search_value.split(' ')
            .some(p => !text_filter.includes(p));
    });
}


/**
 *
 * Retorna datos del lote en la edicion de compra
 *
 */
function getDataItemLotGroup(row_old)
{
    let data = null

    if(row_old.date_of_due && row_old.lot_code)
    {
        data = {
            date_of_due: row_old.date_of_due,
            lot_code: row_old.lot_code,
            item_lot_group_id: row_old.item_lot_group_id,
        }
    }

    return data
}


/**
 * Unidades de medida que expresan magnitudes continuas: admiten fracciones.
 * El resto (unidades, cajas, docenas, etc.) se cuentan de a enteros.
 */
const DECIMAL_UNIT_TYPES = [
    // Peso
    'KGM', 'GRM', 'MGM', 'TNE', 'LBR', 'ONZ',
    // Volumen
    'LTR', 'MLT', 'GLL', 'GLI', 'MTQ', 'CMQ', 'MMQ', 'FTQ',
    // Longitud
    'MTR', 'CMT', 'MMT', 'KTM', 'FOT', 'INH', 'YRD',
    // Superficie
    'MTK', 'CMK', 'MMK', 'FTK',
    // Tiempo y energía
    'HUR', 'HT', 'SEC', 'KWH', 'MWH',
]


/**
 *
 * Decimales admitidos en una cantidad según la unidad de medida del producto.
 * Las magnitudes continuas admiten hasta 12 posiciones; las unidades discretas conservan su regla.
 *
 * @param  {string} unit_type_id
 * @param  {number|undefined} fallback  Precisión para unidades discretas
 * @return {number|undefined}
 */
const getQuantityPrecisionByUnitType = (unit_type_id, fallback = 0) => {
    const unit_type = String(unit_type_id || '').toUpperCase()

    return DECIMAL_UNIT_TYPES.includes(unit_type) ? 12 : fallback
}

// ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
/**
 * Resuelve la afectación permitida para una línea nueva en Venezuela.
 * No sustituye un código desconocido por otro tratamiento fiscal.
 */
const resolveSelectableAffectationType = (requestedId, affectationTypes = [], exportation = false) => {
    const requested = String(requestedId || '')
    if (!(exportation ? ['EXP'] : ['10', '20']).includes(requested)) return null
    return affectationTypes.find(type => String(type.id) === requested) || null
}
// ######### FIN CAMBIO CATÁLOGOS DE NOMBRES

export {
    calculateRowItem, getUniqueArray, showNamePdfOfDescription,
    sumAmountDiscountsNoBaseByItem, FormatUnitPriceRow, filterWords,
    getQuantityPrecisionByUnitType, resolveSelectableAffectationType
}

// ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
