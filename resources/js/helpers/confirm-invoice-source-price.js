// ######## INICIO MONEDA DOCUMENTAL DE VENTAS ########
export async function confirmInvoiceSourcePrice(vm, draft) {
    if (typeof window === 'undefined' || !window.salesDocumentsVes || draft.document_type_id !== '01' ||
        !(draft.quotation_id || draft.order_note_id || draft.technical_service_id ||
          draft.sale_note_id || draft.sale_notes_relateds?.length || draft.dispatch_id || draft.dispatches_relateds?.length)) {
        return draft;
    }
    let quote;
    try {
        const response = await vm.$http.post('/documents/source-price-preview', draft);
        quote = response.data.data;
    } catch (error) {
        const validation = Object.values(error.response?.data?.errors ?? {})[0];
        vm.$message.error(validation?.[0] || error.response?.data?.message || 'No se pudo calcular la factura en bolívares.');
        return null;
    }
    if (!quote) return draft;
    try {
        await vm.$confirm(
            `${quote.source_total == null ? '' : `Origen: Bs. ${quote.source_total}. `}Tasa: Bs. ${quote.rate} por USD. Nuevo total antes de IGTF: Bs. ${quote.total}. Abonos: Bs. ${quote.applied}. Saldo: Bs. ${quote.balance}.`,
            'Confirmar el nuevo total',
            {confirmButtonText: 'Confirmar', cancelButtonText: 'Volver'}
        );
    } catch {
        return null;
    }
    return {...draft, source_price_confirmation: quote.confirmation};
}
// ######## FIN MONEDA DOCUMENTAL DE VENTAS ########
