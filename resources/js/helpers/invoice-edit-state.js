import {captureCreationSuggestions} from './document-creation'

/** Restore the same objects, including receipt/schedule ownership, after a failed edit. */
export function captureInvoiceEdit(view) {
    const fields=['form','total_global_discount','total_global_charge','invoiceAllowancePercent',
        'subtotal_before_global_discount','total_discount_no_base','is_amount','currency_type','invoiceCalculationError',
        'invoiceGlobalDiscountSource','recordDiscountsGlobal','invoiceDiscountRestoreError','invoiceGlobalChargeSource']
    const values=fields.map(key=>[key,view[key]])
    const seen=new WeakSet(),records=[]
    function capture(value) {
        if (!value || typeof value!=='object' || seen.has(value)) return
        if (!Array.isArray(value) && Object.prototype.toString.call(value)!=='[object Object]') return
        seen.add(value)
        const keys=Reflect.ownKeys(value).filter(key=>Object.prototype.propertyIsEnumerable.call(value,key))
        const entries=keys.map(key=>[key,value[key]])
        records.push({value,entries,array:Array.isArray(value),length:value.length})
        for (const [,child] of entries) capture(child)
    }
    for (const [,value] of values) capture(value)
    const restoreSuggestions=captureCreationSuggestions(view.form)
    return () => {
        for (const record of [...records].reverse()) {
            const keys=new Set(record.entries.map(([key])=>key))
            for (const key of Reflect.ownKeys(record.value)) {
                if (Object.prototype.propertyIsEnumerable.call(record.value,key) && !keys.has(key)) {
                    if (view.$delete && typeof key!=='symbol') view.$delete(record.value,key);else delete record.value[key]
                }
            }
            for (const [key,value] of record.entries) {
                if (view.$set && typeof key!=='symbol') view.$set(record.value,key,value);else record.value[key]=value
            }
            if (record.array) record.value.splice(record.length)
        }
        for (const [key,value] of values) view[key]=value
        restoreSuggestions()
    }
}
