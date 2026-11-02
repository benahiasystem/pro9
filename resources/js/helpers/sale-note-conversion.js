import {normalizeExchangeRate} from './exchange-rate-math'

// ######## INICIO CALCULOS EXACTOS PRO9 ########
// Restore the source context before preparing any rows. The server must still
// authorize and resolve the source notes again when persisting the invoice.
export function storeSaleNoteConversion(storage, {context, client, items, notes, payments = []}) {
    const keys=['noteConversionContext','client','itemsNotGroupForNotes','notes','itemsForNotes','lotsItems','noteConversionPayments']
    try {
        // Clear stale staging first; publish the context only after the complete selection.
        keys.forEach(key=>storage.removeItem(key))
        storage.setItem('client',JSON.stringify(client))
        storage.setItem('itemsNotGroupForNotes',JSON.stringify(items))
        storage.setItem('notes',JSON.stringify(notes))
        storage.setItem('noteConversionPayments',JSON.stringify(payments))
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
    const rawPayments=storage.getItem('noteConversionPayments')
    if (!rawPayments) throw new Error('Los cobros conservados de las notas están incompletos. Vuelva a seleccionar las notas.')
    const payments = JSON.parse(rawPayments)
    if (!Array.isArray(payments)) throw new Error('Los cobros conservados de las notas están incompletos.')
    view.saleNoteConversionRevision=(view.saleNoteConversionRevision || 0)+1
    view.form.currency_type_id=context.currency_type_id
    view.form.exchange_rate_sale=rate
    view.form.customer_id=context.customer_id
    view.form.establishment_id=context.establishment_id
    view.form.payments=payments
    storage.removeItem('noteConversionPayments')
    storage.removeItem('noteConversionContext')
    return context
}

// An earlier date lookup must not overwrite a restored note or a manual edit.
export function documentRateRequest(view) {
    view.documentRateRequestSequence=(view.documentRateRequestSequence || 0)+1
    return {form:view.form, date:view.form.date_of_issue, rate:view.form.exchange_rate_sale,
        noteRevision:view.saleNoteConversionRevision || 0, sequence:view.documentRateRequestSequence}
}

export function mayApplyDocumentRate(view, request) {
    return view.form === request.form && view.form.date_of_issue === request.date
        && view.form.exchange_rate_sale === request.rate
        && (view.saleNoteConversionRevision || 0) === request.noteRevision
        && view.documentRateRequestSequence === request.sequence
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
