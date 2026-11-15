// ######## INICIO VERIFICACION FINAL DE CALCULOS ########
import {exactAmount as D, exactValue, allocateExact} from './exchange-rate-math'
import {isRecordedReceipt} from './payment-classification'
import {invoicePaymentsForCondition, paymentConditionPayload} from './document-creation'

export const totalFields = ['total_taxed','total_exonerated','total_igv','total_value','total_discount','total_charge','total_exportation','total_other_taxes','total']
export const lineFields = ['quantity','unit_price','total_value','total_igv','total']
export function calculationDraftKey(form) {
    if (!form) return JSON.stringify(form)
    return JSON.stringify({...form, payments:(form.payments || []).map(row => {
        if (isRecordedReceipt(row)) return row
        const copy = {...row}
        for (const key of ['igtf_status','quoted_tax_amount','tax_amount','financial_intermediation','errors']) delete copy[key]
        if (Object.prototype.hasOwnProperty.call(row,'tendered_amount')) {
            delete copy.payment; delete copy.original_amount
        }
        return copy
    })})
}
export function calculationExpectation(form, quote, revision, methods = [], destinations = []) {
    const rows = (form.payments || []).filter(row => { try { return D(row.payment || '0').compare(0) > 0 } catch (_) { return false } })
    const destination = (id, selected) => {
        const method = methods.find(value => String(value.id) === String(id))
        return method?.collection_kind === 'cash' ? 'cash' : String(selected ?? method?.bank_account_id ?? '')
    }
    const cashId = (id, selected) => destination(id,selected) === 'cash' ? String(destinations.find(row=>row.id === 'cash')?.cash_id ?? '') : null
    const payments = (quote?.payments || []).map((row,index) => ({
        ...Object.fromEntries(['currency_type_id','exchange_rate','original_amount','payment','change','tax_amount','igtf_status','reused'].map(key => [key,row[key]])),
        payment_exact:D(row.payment_exact ?? (row.reused ? exactValue(rows[index],'payment').fraction() : row.payment)).fraction(),
        payment_method_type_id:String(rows[index].payment_method_type_id),
        payment_destination_id:row.reused ? 'recorded' : destination(rows[index].payment_method_type_id, rows[index].payment_destination_id),
        cash_id:row.reused ? null : cashId(rows[index].payment_method_type_id,rows[index].payment_destination_id),
        change_disbursements:row.reused ? [] : (rows[index].change_disbursements || []).map(line=>({
            payment_method_type_id:String(line.payment_method_type_id), payment_destination_id:destination(line.payment_method_type_id,line.payment_destination_id),
            cash_id:cashId(line.payment_method_type_id,line.payment_destination_id),
            currency_type_id:methods.find(value=>String(value.id)===String(line.payment_method_type_id))?.currency_type_id,
            amount:D(line.amount).final(2)})),
    }))
    const balance = quote?.balance ?? D(form.total_payable ?? form.total).final(2)
    let fee = []
    if (form.payment_condition_id !== '01' && form.fee?.length) {
        const submitted = form.fee.map(row => row.amount ?? '0')
        const weighted = form.fee.every(row=>row.allocation_weight != null)
        let weights = weighted ? form.fee.map(row=>row.allocation_weight) : submitted
        if (weighted && weights.some(value=>D(value).compare(0) !== 0)) {
            const provisional = allocateExact(balance, weights, 2)
            if (submitted.some((value,index)=>D(value).compare(provisional[index]) !== 0)) weights = submitted
        }
        if (weights.every(value => D(value).compare(0) === 0)) weights = weights.map(() => '1')
        const amounts = allocateExact(balance, weights, 2)
        fee = form.fee.map((row,index) => ({date:row.date, payment_method_type_id:String(row.payment_method_type_id), amount:amounts[index]}))
    }
    return {version:1, revision, currency_type_id:form.currency_type_id, exchange_rate_sale:form.exchange_rate_sale,
        igtf_enabled:quote?.igtf_enabled ?? false,igtf_rate:quote?.igtf_rate ?? null,
        totals:Object.fromEntries(totalFields.map(field => [field, exactValue(form,field).fraction()])),
        items:(form.items || []).map(row => ({item_id:String(row.item_id),affectation_igv_type_id:row.affectation_igv_type_id,
            ...Object.fromEntries(lineFields.map(field => [field,exactValue(row,field).fraction()]))})),
        payments, total_with_tax:quote?.total_with_tax ?? D(form.total_payable ?? form.total).final(2),
        total_with_tax_exact:quote?.total_with_tax_exact ?? exactValue(form,'total').fraction(),
        settlement_adjustment_exact:quote?.settlement_adjustment_exact ?? D(form.total_payable ?? form.total).minus(exactValue(form,'total')).fraction(),
        balance, balance_exact:quote?.balance_exact ?? D(balance).fraction(), fee}
}
export function calculationPayload(form, quote, revision, approved = null, methods = [], destinations = []) {
    form = paymentConditionPayload({...form,payments:invoicePaymentsForCondition(form)})
    const result = {...form, payments:(form.payments || []).map(row => {
        if (isRecordedReceipt(row)) return {...row}
        const copy = {...row}
        for (const key of ['quoted_tax_amount','tax_amount','igtf_status']) delete copy[key]
        return copy
    })}
    result.expected_calculation = approved ? {...approved,revision} : calculationExpectation(form,quote,revision,methods,destinations)
    return result
}
// ######## FIN VERIFICACION FINAL DE CALCULOS ########
