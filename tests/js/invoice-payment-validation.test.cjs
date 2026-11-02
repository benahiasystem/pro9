const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const compiler = require('vue-template-compiler')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default
const t = require('@babel/types')
const {validateInvoicePayments} = require('./helpers/load-module.cjs')(require('node:path').resolve('resources/js/helpers/invoice-payment-validation.js'))
const {restoreDeferredInvoiceCash} = require('./helpers/load-module.cjs')(require('node:path').resolve('resources/js/helpers/payment-classification.js'))

const methods = [{id:'01',collection_kind:'account'}, {id:'04',collection_kind:'cash'}]
const row = (id, payment, extra = {}) => ({payment_method_type_id:id, payment, ...extra})

test('invoice validation uses exact sums and preserves recorded receipts', () => {
    const historical = row('01','0.10',{source_sale_note_payment_id:5})
    const result = validateInvoicePayments([historical,row('04','0.20')], '0.30', methods)
    assert.equal(result.error_by_item,0)
    assert.equal(result.acum_total.compare('0.30'),0)
    assert.equal(result.inputs[0],historical)
    assert.equal(validateInvoicePayments([row('01','0.31')], '0.30', methods).error_by_item,1)
    assert.equal(validateInvoicePayments([row('01','-1')], '0.30', methods).error_by_item,1)
    assert.equal(validateInvoicePayments([row('01','invalid')], '0.30', methods).error_by_item,1)
    assert.equal(validateInvoicePayments([row('01','0',{source_sale_note_payment_id:5})], '0.30', methods).error_by_item,1)
    assert.equal(validateInvoicePayments([row('01','0'),row('04','0')], '0.30', methods).inputs.length,0)
})

test('only new cash can provide change after IDs are reclassified', () => {
    assert.equal(validateInvoicePayments([row('01','100'),row('04','20')], '110', methods).error_by_item,0)
    assert.equal(validateInvoicePayments([row('01','120')], '110', methods).error_by_item,1)
    assert.equal(validateInvoicePayments([row('04','20',{source_quotation_payment_id:7}),row('01','100')], '110', methods).error_by_item,1)
    assert.equal(validateInvoicePayments([row('04','20',{source_quotation_payment_id:7}),row('04','20')], '30', methods).error_by_item,0)
    for (const origin of [{receipt_parent_id:7},{receipt_origin:{id:7}},{payment_method_snapshot:{receipt_origin:{id:7}}},{_reused_receipt:true}]) {
        assert.equal(validateInvoicePayments([row('04','20',origin),row('01','100')], '110', methods).error_by_item,1)
    }
})

test('late catalog resolves only still-pending new rows and preserves selections', () => {
    const empty=row(null,'0.1'), selected=row('01','0.2'), removed=row(null,'1'), historical=row(null,'1',{id:7})
    const vm={form:{currency_type_id:'VES',payments:[empty,selected,historical]},pendingDefaultPaymentRows:[empty,selected,removed,historical],
        payment_method_types:[{id:'01',collection_kind:'account',currency_type_id:'VES',is_active:true},
            {id:'04',collection_kind:'cash',currency_type_id:'VES',cash_currency_type_id:'VES',is_active:true}],
        payment_destinations:[{id:'cash'}],config:{destination_sale:true}}
    restoreDeferredInvoiceCash(vm)
    assert.equal(empty.payment_method_type_id,'04')
    assert.equal(empty.payment_destination_id,'cash')
    assert.equal(selected.payment_method_type_id,'01')
    assert.equal(removed.payment_method_type_id,null)
    assert.equal(historical.payment_method_type_id,null)
    assert.equal(vm.pendingDefaultPaymentRows.length,0)
    const noMatch=row(null,'1')
    vm.form.payments=[noMatch]; vm.form.currency_type_id='USD'; vm.pendingDefaultPaymentRows=[noMatch]
    restoreDeferredInvoiceCash(vm)
    assert.equal(noMatch.payment_method_type_id,null)
})

for (const file of ['invoice.vue','invoiceupdate.vue','invoice_generate.vue']) {
    test(`${file} validates its actual payment method through the shared exact helper`, () => {
        const source = fs.readFileSync(`resources/js/views/tenant/documents/${file}`, 'utf8')
        const component = compiler.parseComponent(source)
        assert.deepEqual(compiler.compile(component.template.content).errors,[])
        const ast = parser.parse(component.script.content,{sourceType:'module'})
        const properties = ast.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties
        const entry = properties.find(node=>node.key?.name==='methods').value.properties.find(node=>node.key?.name==='validate_payments')
        const fn = t.functionExpression(null,entry.params,entry.body,entry.generator,entry.async)
        const run = vm.runInNewContext(`(${generate(fn).code})`,{validateInvoicePayments})
        const receipt = row('01','0.1',{source_sale_note_payment_id:3})
        const ctx = {form:{total:'0.3',payments:[receipt,row('04','0.2')]},payment_method_types:methods}
        assert.equal(run.call(ctx).error_by_item,0)
        assert.equal(ctx.form.payments[0],receipt)
        assert.doesNotMatch(component.script.content,/validate\.acum_total\s*>\s*parseFloat/)
        assert.match(component.script.content,/restoreDeferredInvoiceCash\(this\)/)
        assert.match(component.template.content,/paymentMethodsLoaded \?/)
        const addEntry = properties.find(node=>node.key?.name==='methods').value.properties.find(node=>node.key?.name==='clickAddPayment')
        const addFn = t.functionExpression(null,addEntry.params,addEntry.body,addEntry.generator,addEntry.async)
        const {defaultCashForCurrency} = require('./helpers/load-module.cjs')(require('node:path').resolve('resources/js/helpers/payment-classification.js'))
        const add = vm.runInNewContext(`(${generate(addFn).code})`,{defaultCashForCurrency,moment:()=>({format:()=> '2026-11-02'})})
        const draft = {form:{currency_type_id:'VES',payments:[]},payment_method_types:[],paymentMethodsLoaded:false,
            pendingDefaultPaymentRows:[],payment_destinations:[{id:'cash'}],config:{destination_sale:true},
            getPaymentDestinationId:()=> 'cash',calculatePayments(){}}
        add.call(draft)
        assert.equal(draft.form.payments[0].payment_method_type_id,null)
        draft.payment_method_types=[{id:'04',collection_kind:'cash',currency_type_id:'VES',cash_currency_type_id:'VES',is_active:true}]
        restoreDeferredInvoiceCash(draft)
        assert.equal(draft.form.payments[0].payment_method_type_id,'04')
        assert.equal(draft.form.payments[0].payment_destination_id,'cash')
    })
}
