import { exactAmount } from './exchange-rate-math';

export function noteSeries(series, type, establishment) {
    return series.filter(row => row.document_type_id === type && Number(row.establishment_id) === Number(establishment) && !row.contingency);
}
export function noteTotal(form, context) {
    if (form.document_type_id === '07' && form.note_credit_or_debit_type_id === '01') return context.credit_available;
    return form.items.reduce((sum, row) => {
        const source = context.items.find(item => item.id === row.affected_document_item_id);
        const returning = form.document_type_id === '07' && form.note_credit_or_debit_type_id === '07';
        const amount = returning && source && Number(source.remaining_quantity) > 0
            ? exactAmount(source.remaining_total).times(row.quantity || 0).dividedBy(source.remaining_quantity).final(2)
            : row.adjustment_amount || 0;
        return sum.plus(amount);
    }, exactAmount(0)).final(2);
}
export function noteRequestId() {
    const bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);
    bytes[6] = (bytes[6] & 15) | 64; bytes[8] = (bytes[8] & 63) | 128;
    const hex = Array.from(bytes, n => n.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0,8)}-${hex.slice(8,12)}-${hex.slice(12,16)}-${hex.slice(16,20)}-${hex.slice(20)}`;
}
