import {exactAmount, exactInput, exactValue} from './exchange-rate-math'

export function samePosId(left, right) {
    return (typeof left === 'string' || Number.isSafeInteger(left))
        && (typeof right === 'string' || Number.isSafeInteger(right))
        && String(left) === String(right)
}

export function isPosPriceAllowed(value) {
    try { return (value && typeof value==='object' ? posPriceAmount(value) : exactAmount(exactInput(String(value)))).compare('0.1')>=0 } catch (_) { return false }
}
export function quotedAmount(record, field) {
    const entered=exactInput(String(record[field]))
    const saved=record.calculation_snapshot?.values?.[field]
    if (saved && exactAmount(saved).decimal()!==exactAmount(entered).decimal()) throw new Error('El valor no coincide con su fracción de catálogo.')
    return saved ? exactAmount(saved) : exactAmount(entered)
}
function selectedQuote(item) {
    const entered=exactInput(String(item.sale_unit_price))
    const useSaved=!item.manual_price_selected || ['manual','catalog'].includes(item.selected_price_origin)
    const saved=useSaved ? item.selected_price_amount ?? item.calculation_snapshot?.values?.sale_unit_price : null
    const original=saved && exactAmount(saved).decimal()===exactAmount(entered).decimal()
    return {amount:original ? exactAmount(saved) : exactAmount(entered),input:original ? saved : entered}
}
export function posPriceAmount(item) { return selectedQuote(item).amount }
function rememberOriginal(item) {
    if (item.original_sale_price_amount) return
    item.original_sale_unit_price=exactInput(String(item.sale_unit_price))
    item.original_sale_price_amount=posPriceAmount(item).fraction()
    item.original_pos_presentation=item.presentation ?? null
    item.original_pos_unit_type_id=item.unit_type_id
}
/** A list choice has its own source; it must never reuse the previous quote. */
export function selectPosCatalogPrice(item, quote, presentation=null, lock=true) {
    rememberOriginal(item)
    const field=quote.price!==undefined ? 'price' : ['1','2','3'].includes(String(quote.price_default)) ? `price${quote.price_default}` : null
    if (!field || quote[field]===null) throw new Error('Seleccione un precio disponible.')
    const amount=quotedAmount(quote,field)
    const parent=presentation ?? (quote.item_unit_type_id!==undefined
        ? [...(item.item_unit_types || []),...(item.unit_type || [])].find(unit=>String(unit.id)===String(quote.item_unit_type_id))
        : quote.quantity_unit!==undefined ? quote : null)
    if (quote.item_unit_type_id!==undefined && !parent) throw new Error('No se encontró la presentación del precio.')
    item.sale_unit_price=amount.decimal()
    item.selected_price_amount=amount.fraction()
    item.selected_price_origin='catalog'
    item.manual_price_selected=lock
    item.affected_list_price=true
    if (parent) {
        item.presentation={...parent,quantity_unit:quotedAmount(parent,'quantity_unit').decimal()}
        item.unit_type_id=parent.unit_type_id
    }
    return amount
}
export function selectPosOriginalPrice(item) {
    rememberOriginal(item)
    item.sale_unit_price=item.original_sale_unit_price
    item.selected_price_amount=item.original_sale_price_amount
    item.selected_price_origin='catalog'
    item.manual_price_selected=false
    item.presentation=item.original_pos_presentation
    item.unit_type_id=item.original_pos_unit_type_id
}
/** Saving typed text creates a new finite decimal, even if the old projection matched. */
export function commitPosManualPrice(item, text) {
    const entered=exactInput(String(text))
    rememberOriginal(item)
    item.sale_unit_price=entered
    item.selected_price_amount=exactAmount(entered).fraction()
    item.selected_price_origin='manual'
    item.manual_price_selected=true
    item.edit_unit_price=false
}
export function posPresentationQuantity(item) {
    return item.presentation ? quotedAmount(item.presentation,'quantity_unit') : exactAmount('1')
}
/** Validate physical units across all prices/presentations already in the cart. */
export function posRequiredStock(rows, item, quantity, replacedRow=null) {
    let required=posPresentationQuantity(item).times(quantity)
    for (const row of rows) {
        if (row!==replacedRow && String(row.item_id)===String(item.item_id)) {
            required=required.plus(posPresentationQuantity(row.item).times(exactValue(row,'quantity')))
        }
    }
    return required.fraction()
}
export function samePosSelection(row, item) {
    const previous=row.item
    return String(row.item_id)===String(item.item_id)
        && String(row.unit_type_id)===String(item.unit_type_id)
        && String(previous.presentation?.id ?? '')===String(item.presentation?.id ?? '')
        && String(previous.currency_type_id)===String(item.currency_type_id)
        && previous.has_igv===item.has_igv
        && previous.sale_affectation_igv_type_id===item.sale_affectation_igv_type_id
        && posPriceAmount(previous).compare(posPriceAmount(item))===0
}
export function posLinePrice(item, currency, percentage) {
    const quote=selectedQuote(item),price=quote.amount
    const includesIva=Boolean(item.has_igv)
    const taxed=item.sale_affectation_igv_type_id==='10'
    const gross=!includesIva && taxed ? price.times(exactAmount(1).plus(percentage)) : price
    return {unit_price:gross.decimal(),price_input:{amount:quote.input,
        currency_type_id:item.currency_type_id || currency,includes_iva:includesIva}}
}
export function posInvoiceQuantityError(row) {
    try {
        const entered = exactAmount(row.quantity)
        const saved = exactValue(row, 'quantity')
        const unchanged = row.calculation_snapshot && saved.decimal() === entered.decimal()
        const quantityInput = row.quantity_input ?? (unchanged ? row.calculation_snapshot.inputs?.quantity_input : null)
        const quantity = quantityInput
            ? exactAmount(quantityInput.amount).dividedBy(quantityInput.unit_price)
            : unchanged ? saved : entered

        if (quantity.compare(0) <= 0) return 'Los productos deben tener cantidades mayor a 0'
        if (['SERV', 'UND'].includes(row.unit_type_id) && quantity.fraction().d !== '1') {
            return 'El producto con ese tipo de unidad no permite cantidad en decimales'
        }
        return null
    } catch (_) {
        return 'Cantidad negativa o incorrecta'
    }
}

/** A repeated click changes quantity; it never replaces an earlier exact price input. */
export function incrementPosLine(row, increment='1') {
    const quantity=exactValue(row,'quantity').plus(exactInput(String(increment)))
    const text=exactInput(quantity.decimal())
    if (quantity.compare(0)<=0) throw new Error('La cantidad debe ser positiva.')
    return {...row,quantity:text,item:{...row.item,aux_quantity:text},quantity_input:null,
        calculation_snapshot:{...row.calculation_snapshot,version:1,
            inputs:{...row.calculation_snapshot?.inputs,quantity_input:null},
            values:{...row.calculation_snapshot?.values,quantity:quantity.fraction()}}}
}
