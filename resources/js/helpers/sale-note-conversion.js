import {normalizeExchangeRate} from './exchange-rate-math'

// ######## INICIO CALCULOS EXACTOS PRO9 ########
// Restore the source context before preparing any rows. The server must still
// authorize and resolve the source notes again when persisting the invoice.
export function storeSaleNoteConversion(storage, {context, client, items, notes}) {
    const keys=['noteConversionContext','client','itemsNotGroupForNotes','notes','itemsForNotes','lotsItems']
    try {
        // Clear stale staging first; publish the context only after the complete selection.
        keys.forEach(key=>storage.removeItem(key))
        storage.setItem('client',JSON.stringify(client))
        storage.setItem('itemsNotGroupForNotes',JSON.stringify(items))
        storage.setItem('notes',JSON.stringify(notes))
        storage.setItem('noteConversionContext',JSON.stringify(context))
    } catch (error) {
        keys.forEach(key=>{try { storage.removeItem(key) } catch (_) { /* Storage may be unavailable. */ }})
        throw new Error(error.name==='QuotaExceededError'
            ? 'La selección es demasiado grande para transferirla. Reduzca las notas seleccionadas e inténtelo de nuevo.'
            : 'No se pudo transferir la selección. Vuelva a intentarlo antes de abrir la factura.')
    }
}

export function restoreSaleNoteContext(view, storage) {
    const saved=storage.getItem('noteConversionContext')
    if (!saved) return null
    const context=JSON.parse(saved)
    if (!['VES','USD'].includes(context.currency_type_id) || !context.customer_id || !context.establishment_id
        || typeof context.exchange_rate_sale!=='string') {
        throw new Error('El contexto conservado de las notas está incompleto.')
    }
    const rate=normalizeExchangeRate(context.exchange_rate_sale)
    view.form.currency_type_id=context.currency_type_id
    view.form.exchange_rate_sale=rate
    view.form.customer_id=context.customer_id
    view.form.establishment_id=context.establishment_id
    storage.removeItem('noteConversionContext')
    return context
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
