const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const compiler = require('vue-template-compiler')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default
const load = require('./helpers/load-module.cjs')

const source = compiler.parseComponent(fs.readFileSync('modules/Sale/Resources/assets/js/views/technical-services/partials/options.vue', 'utf8'))
const tree = parser.parse(source.script.content, {sourceType:'module'})
const component = tree.program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration
const methods = component.properties.find(node => node.key.name === 'methods').value
const submit = methods.properties.find(node => node.key?.name === 'submit')
const globals = {...load('resources/js/helpers/ensure-exchange-rate-sale.js'), ...load('resources/js/helpers/document-creation.js'),
    ...load('resources/js/helpers/payment-classification.js')}
const run = vm.runInNewContext(`({${generate(submit).code}}).submit`,globals)

test('technical service conversion preserves recorded and source-only receipts', () => {
    const cancel=vm.runInNewContext(`({${generate(methods.properties.find(node=>node.key?.name==='clickCancel')).code}}).clickCancel`,globals)
    for (const origin of [{id:4},{source_technical_service_payment_id:4},{payment_method_snapshot:{receipt_origin:{id:4}}}]) {
        const receipt={payment:'10',...origin},ctx={form:{payments:[receipt]}}
        cancel.call(ctx,0)
        assert.equal(ctx.form.payments[0],receipt)
    }
    const draft={id:null,payment:'10'},ctx={form:{payments:[draft]}}
    cancel.call(ctx,0); assert.equal(ctx.form.payments.length,0)
    const validate=vm.runInNewContext(`({${generate(methods.properties.find(node=>node.key?.name==='validatePaymentDestination')).code}}).validatePaymentDestination`,globals)
    ctx.form.payments=[{source_technical_service_payment_id:4,payment_destination_id:null}]
    assert.equal(validate.call(ctx).error_by_item,0)
    ctx.form.payments.push({id:null,payment_destination_id:null})
    assert.equal(validate.call(ctx).error_by_item,1)
    assert.match(source.template.content,/v-if="!isRecordedReceipt\(row\)"[\s\S]*?clickCancel\(index\)/)
})

test('technical service conversion submits an existing receipt without changing its data', async () => {
    const errors=[],calls=[]
    const receipt={id:4,technical_service_id:8,date_of_payment:'2026-10-01',payment_method_type_id:'13',currency_type_id:'USD',original_amount:'2.50',exchange_rate:'40',payment:'100',payment_destination_id:2,financial_intermediation:'financial_institution'}
    const ctx={record:{payments:[receipt]},form:{document_type_id:'01',date_of_issue:'2026-10-01',currency_type_id:'VES',exchange_rate_sale:'40',payments:[{...receipt}]},
        validatePaymentDestination:async()=>({error_by_item:0}),ensureFiscalPaymentsReady:async()=>true,
        $message:{error:m=>errors.push(m)},$http:{post:async(url,data)=>{calls.push({url,data});return {data:{success:false,message:'sin guardar'}}}}}
    await run.call(ctx)
    assert.equal(calls.length,1)
    assert.deepEqual(calls[0].data.payments[0],receipt)
    assert.deepEqual(errors,['sin guardar'])
    assert.deepEqual(compiler.compile(source.template.content).errors,[])
    assert.match(source.template.content,/<igtf-payment-fields/)
    assert.match(source.template.content,/<receipt-currency-fields/)
    assert.match(source.template.content,/Intermediación del cobro original/)
})

test('technical service without prior receipts submits the received currency and source rate', async () => {
    const calls=[],errors=[]
    const payment={id:null,payment_method_type_id:'13',currency_type_id:'USD',original_amount:'2.50',exchange_rate:'40',payment:'100',payment_destination_id:2}
    const ctx={record:{payments:[]},form:{document_type_id:'01',date_of_issue:'2026-10-01',currency_type_id:'VES',exchange_rate_sale:'40',payments:[payment]},
        validatePaymentDestination:async()=>({error_by_item:0}),ensureFiscalPaymentsReady:async()=>true,
        $message:{error:m=>errors.push(m)},$http:{post:async(url,data)=>{calls.push({url,data});return {data:{success:false,message:'sin guardar'}}}}}
    await run.call(ctx)
    assert.equal(calls.length,1)
    assert.equal(calls[0].url,'/generate-document')
    assert.equal(calls[0].data.exchange_rate_sale,'40.00000000')
    assert.equal(calls[0].data.payments[0].currency_type_id,'USD')
    assert.equal(calls[0].data.payments[0].original_amount,'2.50')
    assert.deepEqual(errors,['sin guardar'])
})
