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
    ...load('resources/js/helpers/payment-classification.js'), ...load('resources/js/helpers/functions.js'),
    ...load('resources/js/helpers/exchange-rate-math.js'), _:require('lodash'), moment:()=>({format:()=> '2026-11-07'})}
const run = vm.runInNewContext(`({${generate(submit).code}}).submit`,globals)
const get=name=>vm.runInNewContext(`({${generate(methods.properties.find(node=>node.key?.name===name)).code}})[${JSON.stringify(name)}]`,globals)

test('technical service conversion keeps exact IVA and suggests credit installments after receipts', async () => {
    const receipt={id:4,payment:'50',currency_type_id:'VES'}
    const ctx={recordId:8,establishment:{id:1},percentage_igv:'0.16',
        form:{items:[],payments:[],fee:[],payment_condition_id:'01'},
        $http:{get:async()=>({data:{data:{cost:'100',currency_type_id:'VES',exchange_rate_sale:'40',customer_id:2,
            date_of_issue:'2026-11-07',description:'Prueba',state:'recibido',reason:'diagnóstico',items:[],payments:[receipt]}}})},
        getPercentageIgv:async()=>{},onPrepareItems:rows=>rows,parserDocumentByCustomer:()=>{}}
    await get('getRecord').call(ctx)
    assert.equal(ctx.form.total,'100')
    assert.equal(ctx.form.items[0].calculation_snapshot.values.total_value.n,'2500')
    assert.equal(ctx.form.items[0].calculation_snapshot.values.total_value.d,'29')
    assert.equal(ctx.form.items[0].calculation_snapshot.values.total_igv.n,'400')
    assert.equal(ctx.form.items[0].calculation_snapshot.values.total_igv.d,'29')
    for(const name of ['calculateFee','clickAddFee','changePaymentCondition'])ctx[name]=get(name).bind(ctx)
    ctx.form.payment_condition_id='02';ctx.changePaymentCondition()
    assert.equal(ctx.form.fee[0].amount,'50.00')
    assert.equal(ctx.form.payments[0].id,4)
    assert.match(source.template.content,/Condición de pago/)
    assert.deepEqual(compiler.compile(source.template.content).errors,[])
})

test('technical service conversion retains a large decimal cost from the resource', async () => {
    const cost='9007199254740993.01'
    const ctx={recordId:8,establishment:{id:1},percentage_igv:'0.16',
        form:{items:[],payments:[],fee:[],payment_condition_id:'01'},
        $http:{get:async()=>({data:{data:{cost,currency_type_id:'VES',exchange_rate_sale:'40',customer_id:2,
            date_of_issue:'2026-11-07',description:'Prueba',state:'recibido',reason:'diagnóstico',items:[],payments:[]}}})},
        getPercentageIgv:async()=>{},onPrepareItems:rows=>rows,parserDocumentByCustomer:()=>{}}
    await get('getRecord').call(ctx)
    assert.equal(ctx.form.items[0].price_input.amount,cost)
    assert.equal(ctx.form.total,cost)
})

test('technical service conversion retains header adjustments and suggests the adjusted balance', async () => {
    const item=globals.calculateRowItem({item_id:3,item:{id:3,unit_price:'116',unit_type_id:'UND',currency_type_id:'VES'},
        quantity:'1',unit_price:'116',affectation_igv_type_id:'10',discounts:[],charges:[]},'VES','40','0.16')
    const discount={discount_type_id:'02',amount:'10',base:'200',factor:'0.05',description:'Descuento'}
    const charge={charge_type_id:'50',amount:'5',base:'116',factor:'0.043103448276',description:'Cargo'}
    const ctx={recordId:8,establishment:{id:1},percentage_igv:'0.16',
        form:{items:[],payments:[],fee:[],payment_condition_id:'01'},
        $http:{get:async()=>({data:{data:{cost:'116',total:'109.4',total_taxed:'190',total_igv:'30.4',total_taxes:'30.4',total_value:'195',
            total_discount:'10',total_charge:'5',total_prepayment:'0',discounts:[discount],charges:[charge],
            currency_type_id:'VES',exchange_rate_sale:'40',customer_id:2,date_of_issue:'2026-11-07',
            description:'Prueba',state:'recibido',reason:'diagnóstico',items:[item],payments:[]}}})},
        getPercentageIgv:async()=>{},onPrepareItems:rows=>rows,parserDocumentByCustomer:()=>{}}
    await get('getRecord').call(ctx)
    assert.equal(ctx.form.total,'225.4')
    assert.equal(ctx.form.total_igv,'30.4')
    assert.equal(ctx.form.discounts[0].amount,'10')
    assert.equal(ctx.form.charges[0].amount,'5')
    assert.notEqual(ctx.form.discounts[0],discount)
    assert.match(source.template.content,/v-model="form\.items\[form\.items\.length - 1\]\.description"/)
    for(const name of ['calculateFee','clickAddFee','changePaymentCondition'])ctx[name]=get(name).bind(ctx)
    ctx.form.payment_condition_id='02';ctx.changePaymentCondition()
    assert.equal(ctx.form.fee[0].amount,'225.40')
})

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
    assert.doesNotMatch(source.template.content,/Intermediación del cobro original/)
})

test('technical service sends credit installments and keeps them in the draft when switching to cash', async () => {
    let sent
    const fee={date:'2026-11-08',currency_type_id:'VES',amount:'50.00'}
    const ctx={form:{document_type_id:'01',date_of_issue:'2026-11-07',currency_type_id:'VES',exchange_rate_sale:'40',
        payment_condition_id:'02',fee:[fee],payments:[{id:4,payment:'50'}]},
        validatePaymentDestination:async()=>({error_by_item:0}),ensureFiscalPaymentsReady:async()=>true,
        $message:{error(){}},$http:{post:async(url,data)=>{sent=data;return {data:{success:false}}}}}
    await run.call(ctx);assert.equal(sent.payment_condition_id,'02');assert.equal(sent.fee[0],fee)
    ctx.form.payment_condition_id='01';await run.call(ctx);assert.equal(sent.fee.length,0);assert.equal(ctx.form.fee[0],fee)
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
