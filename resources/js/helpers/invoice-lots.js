import {exactAmount, exactInput, exactSum} from './exchange-rate-math'

export function serialIdKey(id) {
    if (Number.isSafeInteger(id) && id > 0) return String(id)
    return typeof id === 'string' && /^[1-9][0-9]*$/.test(id) ? id : null
}

export function sameInvoiceRecordId(left, right) {
    const key = serialIdKey(left)
    return key !== null && key === serialIdKey(right)
}

export function lotQuantity(value) {
    const amount=exactInput(String(value ?? '0').trim().replace(',','.'))
    if (exactAmount(amount).compare(0)<0) throw new Error('La cantidad del lote no puede ser negativa.')
    return amount
}
export function itemRequiresLot(row) { return !!(row?.item && (row.item.lots_enabled || row.item.has_lot || row.lots_enabled)) }
function empty(value) { return value==null || value==='' || value===false || (Array.isArray(value) && !value.length) }
export function resolveIdLoteSelected(row) {
    if (!empty(row?.IdLoteSelected)) return row.IdLoteSelected
    if (!empty(row?.item?.IdLoteSelected)) return row.item.IdLoteSelected
    const lots=row?.item?.lots_group
    return Array.isArray(lots) ? lots.filter(lot=>exactAmount(lotQuantity(lot.compromise_quantity)).compare(0)>0) : null
}
export function normalizeInvoiceLots(value) {
    return Array.isArray(value) ? value.map(lot=>({id:lot.id,code:lot.code,
        compromise_quantity:lotQuantity(lot.compromise_quantity ?? lot.compromiseQuantity),date_of_due:lot.date_of_due ?? null})) : value
}
export function hydrateItemLots(row) {
    const selected=resolveIdLoteSelected(row)
    if (!empty(selected)) {
        row.IdLoteSelected=normalizeInvoiceLots(selected)
        if (row.item) row.item.IdLoteSelected=row.IdLoteSelected
    }
    return row
}
export function validateItemsLots(items) {
    for (const [index,row] of (items || []).entries()) {
        if (!itemRequiresLot(row)) continue
        const message=`El producto [${row.item.description || index+1}] requiere asignación de lotes válida.`
        try {
            const selected=resolveIdLoteSelected(row)
            if (empty(selected)) return {valid:false,index,message}
            if (!Array.isArray(selected)) continue // Existing single-ID assignment; the server owns its quantity.
            if (selected.some(lot=>empty(lot.id) || exactAmount(lotQuantity(lot.compromise_quantity)).compare(0)<=0)) return {valid:false,index,message}
            // DocumentRequest validates the original twelve-place transport values.
            if (exactSum(selected.map(lot=>lotQuantity(lot.compromise_quantity))).compare(lotQuantity(row.quantity))!==0) return {valid:false,index,message}
        } catch (error) { return {valid:false,index,message:error.message} }
    }
    return {valid:true}
}
export function rowNeedsLotAssignment(row) { return !validateItemsLots([row]).valid }
export function invoiceSeriesValid(items) {
    try { return (items || []).every(row=>!row.item?.series_enabled || exactAmount(row.quantity).compare((row.item.lots || []).length)===0) }
    catch (error) { return false }
}
export function selectedInvoiceLots(lots, quantity) {
    const normalized=lots.map(lot=>({...lot,compromise_quantity:lotQuantity(lot.compromise_quantity)}))
    const selected=normalized.filter(lot=>exactAmount(lot.compromise_quantity).compare(0)>0)
    if (exactSum(selected.map(lot=>lot.compromise_quantity)).compare(lotQuantity(quantity))!==0) throw new Error('La suma de cantidades comprometidas debe ser igual a la cantidad pedida.')
    if (selected.some(lot=>exactAmount(lot.compromise_quantity).compare(lotQuantity(lot.quantity))>0)) throw new Error('La cantidad comprometida no debe sobrepasar la capacidad del lote.')
    return normalizeInvoiceLots(selected)
}
