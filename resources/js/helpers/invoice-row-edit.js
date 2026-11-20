import {exactAmount, exactValue} from './exchange-rate-math'
import {calculateRowItem} from './functions'

const limit = '1000000000000000000'
function input(value, label) {
    const text = String(value ?? '').trim().replace(',', '.')
    if (!/^\d+(?:\.\d{0,12})?$/.test(text)) throw new Error(`${label}: ingrese un decimal válido de hasta doce posiciones.`)
    const result = exactAmount(text)
    if (result.compare(limit) >= 0) throw new Error(`${label}: el importe está fuera del rango permitido.`)
    return result
}

/** Only invoice inline edits use this adapter; the shared legacy mixin is unchanged. */
export function recalculateInvoiceRow(row, field, context) {
    if (!['quantity','unit_price','total','recalculate'].includes(field)) throw new Error('Campo de partida inválido.')
    if (row.calculation_snapshot && row.calculation_snapshot.version !== 1) throw new Error('La partida no conserva una versión de cálculo compatible.')
    if ((row.discounts || []).length || (row.charges || []).length) throw new Error('Edite los ajustes desde el detalle de la partida.')
    const currency = context.currency_type_id
    const rate = exactAmount(context.exchange_rate_sale)
    if (!['VES','USD'].includes(currency) || rate.compare(0) <= 0) throw new Error('Seleccione moneda y tasa válidas antes de editar la partida.')
    const draft = {...row, item: {...row.item}}
    let quantity = exactValue(row,'quantity')
    if (field === 'quantity') {
        const entered = input(row.quantity,'Cantidad')
        if (entered.compare('0.01') < 0) throw new Error('La cantidad no puede ser inferior a 0,01.')
        if (!row.calculation_snapshot || quantity.decimal() !== entered.decimal()) {
            quantity = entered
            // An explicit decimal must take precedence over the source order when both display the same quantity.
            draft.quantity_input = row._from_delivery_order ? {amount: entered.decimal(), unit_price: '1'} : null
            draft.calculation_snapshot = {...row.calculation_snapshot, version:1,
                inputs:{...row.calculation_snapshot?.inputs, quantity_input:draft.quantity_input},
                values:{...row.calculation_snapshot?.values, quantity:quantity.fraction()}}
        }
    }
    if (quantity.compare(0) <= 0) throw new Error('La cantidad debe ser positiva.')
    if (field === 'unit_price' || field === 'total') {
        const entered = input(row[field],field === 'total' ? 'Total' : 'Precio')
        if (field === 'unit_price' && entered.compare('0.01') < 0) throw new Error('El precio no puede ser inferior a 0,01.')
        const unchanged = row.calculation_snapshot && exactValue(row,field).decimal() === entered.decimal()
        const price = unchanged ? exactValue(row,'unit_price') : field === 'total' ? entered.dividedBy(quantity) : entered
        let cost = exactValue(row.item,'purchase_unit_price')
        const costCurrency = row.item.currency_type_id || currency
        if (costCurrency !== currency) {
            if (costCurrency === 'VES' && currency === 'USD') cost = cost.dividedBy(rate)
            else if (costCurrency === 'USD' && currency === 'VES') cost = cost.times(rate)
            else throw new Error('No se conserva la moneda del costo.')
        }
        if (cost.compare(price) > 0) throw new Error('El precio de compra no puede ser superior al precio de venta.')
        draft.price_input = {amount:price.fraction(), currency_type_id:currency, includes_iva:true}
        draft.input_unit_price_value = price.decimal()
        draft.input_has_igv = true
    }
    draft.item.has_editable_row_items = true
    const result = calculateRowItem(draft,currency,context.exchange_rate_sale,context.percentage_igv,context.exportation)
    for (const name of ['quantity','unit_price','unit_value','total_value','total_igv','total']) {
        const value = exactValue(result,name)
        if (value.compare(0) < 0 || value.compare(limit) >= 0 || exactAmount(result[name]).compare(limit) >= 0) {
            throw new Error('El resultado de la partida está fuera del rango permitido.')
        }
    }
    return result
}
