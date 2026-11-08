import { exactAmount, exactValue } from './exchange-rate-math';

export function noteSeries(series, type, establishment) {
    return series.filter(row => row.document_type_id === type && Number(row.establishment_id) === Number(establishment) && !row.contingency);
}
// ######## INICIO SALDO ACREDITABLE EXACTO ########
export function noteAmountLabel(record, field) {
    const amount=exactValue(record,field), displayed=amount.decimal();
    return displayed === '0' && amount.compare(0) > 0 ? '<0.000000000001' : displayed;
}
export function noteIsPositive(record, field) { return exactValue(record,field).compare(0)>0; }
export function noteCanReturnPartially(record) { return exactValue(record,'remaining_quantity').compare('0.000000000001')>=0; }
function noteExactTotal(form, context) {
    if (form.document_type_id === '07' && form.note_credit_or_debit_type_id === '01') return exactValue(context,'credit_available');
    return form.items.reduce((sum, row) => {
        const source = context.items.find(item => item.id === row.affected_document_item_id);
        const returning = form.document_type_id === '07' && form.note_credit_or_debit_type_id === '07';
        const amount = returning && source && noteIsPositive(source,'remaining_quantity')
            ? exactValue(source, 'remaining_total').times(row.quantity || 0).dividedBy(exactValue(source, 'remaining_quantity'))
            : row.adjustment_amount || 0;
        return sum.plus(amount);
    }, exactAmount(0));
}
export function noteTotal(form, context) {
    if (form.document_type_id === '07' && form.note_credit_or_debit_type_id === '01') return context.credit_available;
    return noteExactTotal(form,context).decimal();
}
export function noteTotalLabel(form, context) {
    try {
        const amount=noteExactTotal(form,context), displayed=amount.decimal();
        return displayed === '0' && amount.compare(0)>0 ? '<0.000000000001' : displayed;
    } catch (_) { return '0'; }
}
export function noteCanGenerate(form, context) {
    try { return noteExactTotal(form,context).compare(0)>0; }
    catch (_) { return false; }
}
// ######## FIN SALDO ACREDITABLE EXACTO ########
export function noteRequestId() {
    const bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);
    bytes[6] = (bytes[6] & 15) | 64; bytes[8] = (bytes[8] & 63) | 128;
    const hex = Array.from(bytes, n => n.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0,8)}-${hex.slice(8,12)}-${hex.slice(12,16)}-${hex.slice(16,20)}-${hex.slice(20)}`;
}
