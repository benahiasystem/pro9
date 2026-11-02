const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const path = require('node:path')
const babel = require('@babel/core')
const compiler = require('vue-template-compiler')
const exact = require('./support/exact-runtime.cjs')

const source = fs.readFileSync('resources/js/views/tenant/dispatches/generate-document.vue', 'utf8')
const parsed = compiler.parseComponent(source)
const exportsObject = {}
const code = babel.transformSync(parsed.script.content, {configFile: false, babelrc: false, plugins: ['@babel/plugin-transform-modules-commonjs']}).code
vm.runInNewContext(code, {exports: exportsObject, _: {round:(value,places=0)=>Math.round(value*10**places)/10**places}, require: name => {
    if (name.includes('exact-commercial')) return exact(name)
    if (name.includes('payment-classification')) return require('./helpers/load-module.cjs')(path.resolve('resources/js/helpers/payment-classification.js'))
    if (name.includes('document-creation')) return require('./helpers/load-module.cjs')(path.resolve('resources/js/helpers/document-creation.js'))
    if (name.includes('exchange-rate-math')) return require('./helpers/load-module.cjs')(path.resolve('resources/js/helpers/exchange-rate-math.js'))
    if (name.includes('document-number')) return require('./helpers/load-module.cjs')(path.resolve('resources/js/helpers/document-number.js'))
    if (name.endsWith('/helpers/functions')) return require('./helpers/load-module.cjs')(path.resolve('resources/js/helpers/functions.js'))
    if (name.includes('vuex')) return {mapActions: () => ({}), mapState: () => ({})}
    if (name === 'moment') return require('moment')
    if (name.endsWith('.vue') || name.includes('fiscal-payment-settings')) return {}
    if (name.includes('mixins/functions')) return {exchangeRate: {}, functions: {}}
    return {}
}})
const component = exportsObject.default

test('delivery order conversion exposes the configured receipt currency and fiscal fields', () => {
    assert.deepEqual(compiler.compile(parsed.template.content).errors, [])
    assert.match(parsed.template.content, /<igtf-payment-fields[^>]*:payment-methods="payment_method_types"/s)
    assert.match(parsed.template.content, /:document-currency="document.currency_type_id" :document-rate="document.exchange_rate_sale"/)
    assert.match(parsed.template.content, /<receipt-currency-fields/)
})

test('delivery order retains entered receipts and suggests installments from the outstanding balance', () => {
    const receipt={payment:'25.00',payment_method_type_id:'01',currency_type_id:'USD'},fee={id:null,amount:'0'}
    const ctx={document:{total:'100.00',currency_type_id:'VES',payment_condition_id:'02',payments:[receipt],fee:[]}}
    ctx.calculateFee=()=>component.methods.calculateFee.call(ctx)
    ctx.clickAddFee=()=>{ctx.document.fee.push(fee);ctx.calculateFee()}
    component.methods.changePaymentCondition.call(ctx)
    assert.equal(ctx.document.payments[0],receipt)
    assert.equal(ctx.document.fee[0].amount,'75.00')
    ctx.document.payment_condition_id='01'; component.methods.changePaymentCondition.call(ctx)
    ctx.document.payment_condition_id='02'; component.methods.changePaymentCondition.call(ctx)
    assert.equal(ctx.document.payments[0],receipt)
    assert.equal(ctx.document.fee[0],fee)
    assert.doesNotMatch(parsed.template.content,/v-if="document.payment_condition_id === '01'"/)
})

test('adding a receipt preserves earlier manual amounts', async () => {
    const receipt={payment:'25.00',payment_method_type_id:'01',currency_type_id:'USD'}
    const ctx={document:{total:'100',currency_type_id:'VES',payments:[receipt],fee:[]},form:{dispatch:{total:'100'}},
        payment_method_types:[{id:'04',collection_kind:'cash',is_active:true,currency_type_id:'VES'}],payment_destinations:[{id:'cash'}]}
    ctx.calculatePayments=()=>component.methods.calculatePayments.call(ctx)
    await component.methods.clickAddPayment.call(ctx)
    assert.equal(receipt.payment,'25.00')
    assert.equal(ctx.document.payments[1].payment,'75.00')
})

test('switching an untouched automatic cash suggestion to credit does not create a receipt', async () => {
    const ctx={document:{total:'100',currency_type_id:'VES',payment_condition_id:'01',payments:[],fee:[]},form:{dispatch:{total:'100'}},
        payment_method_types:[{id:'04',collection_kind:'cash',is_active:true,currency_type_id:'VES'}],payment_destinations:[{id:'cash'}]}
    ctx.calculatePayments=()=>component.methods.calculatePayments.call(ctx)
    ctx.calculateFee=()=>component.methods.calculateFee.call(ctx)
    ctx.clickAddFee=()=>component.methods.clickAddFee.call(ctx)
    await component.methods.clickAddPayment.call(ctx,true)
    assert.equal(ctx.document.payments[0].payment,'100.00')
    ctx.document.payments[0].original_amount='100'
    ctx.document.payment_condition_id='02'
    component.methods.changePaymentCondition.call(ctx)
    assert.equal(ctx.document.payments.length,0)
    assert.equal(ctx.document.fee[0].amount,'100.00')
})

test('editing the suggested receipt preserves it on credit', async () => {
    const ctx={document:{total:'100',currency_type_id:'VES',payment_condition_id:'01',payments:[],fee:[]},
        payment_method_types:[{id:'04',collection_kind:'cash',is_active:true,currency_type_id:'VES'}],payment_destinations:[{id:'cash'}]}
    ctx.calculatePayments=()=>component.methods.calculatePayments.call(ctx)
    ctx.calculateFee=()=>component.methods.calculateFee.call(ctx)
    ctx.clickAddFee=()=>component.methods.clickAddFee.call(ctx)
    await component.methods.clickAddPayment.call(ctx,true)
    const receipt=ctx.document.payments[0]
    receipt.payment='25.00';component.methods.markDefaultReceiptEdited.call(ctx,receipt)
    ctx.document.payment_condition_id='02';component.methods.changePaymentCondition.call(ctx)
    assert.equal(ctx.document.payments[0],receipt)
    assert.equal(ctx.document.fee[0].amount,'75.00')
})

test('delivery order defaults to a currently classified receipt method', async () => {
    const ctx={document:{total:'100',currency_type_id:'VES',payments:[]},form:{dispatch:{total:'100'}},
        payment_method_types:[{id:'01',collection_kind:'account',is_active:true,currency_type_id:'VES'},{id:'04',collection_kind:'cash',is_active:true,currency_type_id:'VES'}],
        payment_destinations:[{id:'cash'}]}
    ctx.calculatePayments=()=>component.methods.calculatePayments.call(ctx)
    await component.methods.clickAddPayment.call(ctx)
    assert.equal(ctx.document.payments[0].payment_method_type_id,'04')
    assert.equal(ctx.document.payments[0].payment_destination_id,'cash')
    assert.equal(ctx.document.payments[0].payment,'100.00')
})

test('delivery order converts item prices using the document rate, not a default rate', async () => {
    const observed=[]
    const ctx={resource:'dispatches',recordId:1,document:{date_of_issue:'2026-11-02',currency_type_id:'VES',payments:[]},
        form:{},exchangeRateSale:1,percentage_igv:'0.16',
        $http:{get:async()=>({data:{document_types_invoice:[],series:[],payment_destinations:[],payment_method_types:[],
            affectation_igv_types:[{id:'10'}],payment_conditions:[],dispatch:{id:1,establishment_id:1,date_of_issue:'2026-10-01',series:'T',number:1,
                items:[{item_id:1,quantity:'0.1'},{item_id:1,quantity:'0.2'}]},items:[{id:1,currency_type_id:'USD',sale_unit_price:'10',has_igv:true}]}})},
        getPercentageIgv:async()=>{},getCustomer:async()=>{},validateIdentityDocumentType:async()=>{},
        searchExchangeRateByDate:async()=> '40.00000000',onGetItems(item){observed.push(this.exchangeRateSale);return item},
        onCalculateTotals:async()=>{},clickAddPayment:async()=>{}}
    await component.methods.create.call(ctx)
    assert.deepEqual(observed,['40.00000000'])
    assert.equal(ctx.document.exchange_rate_sale,'40.00000000')
    assert.equal(ctx.items[0].quantity,'0.3')
})

test('delivery order keeps source price exact when IVA is added and currency is converted',()=>{
    const ctx={affectation_igv_type:{id:'10'},currencyTypeIdActive:'VES',exchangeRateSale:'12.5',percentage_igv:'0.16'}
    const item={id:1,currency_type_id:'USD',sale_unit_price:'10',unit_price:'10',has_igv:false,quantity:'0.3',unit_type_id:'KG'}
    const result=component.methods.onGetItems.call(ctx,item)
    assert.equal(result.price_input.amount,'10')
    assert.equal(result.price_input.currency_type_id,'USD')
    assert.equal(result.price_input.includes_iva,false)
    assert.equal(result.unit_price,'145')
    assert.equal(result.total,'43.5')
    const fractional=component.methods.onGetItems.call(ctx,{...item,quantity:'0.333333333333'})
    const document={items:[fractional],payments:[],fee:[],discounts:[],charges:[],payment_condition_id:'02'}
    component.methods.onCalculateTotals.call({document})
    assert.equal(document.total,'48.333333333285')
})

test('delivery order refuses to submit without a positive document rate',async()=>{
    const errors=[],posts=[]
    const ctx={document:{exchange_rate_sale:0},$message:{error:m=>errors.push(m)},$http:{post:async()=>posts.push(1)}}
    await component.methods.submit.call(ctx)
    assert.equal(posts.length,0)
    assert.match(errors[0],/tasa documental válida/)
})

test('delivery order displays non-payment validation errors without discarding receipts',async()=>{
    const errors=[],receipt={payment:'25'}
    const ctx={document:{document_type_id:'01',exchange_rate_sale:'40',payment_condition_id:'02',payments:[receipt],fee:[]},
        assignDocument(){},onCalculateTotals(){},validatePaymentDestination:async()=>({error_by_item:0}),
        ensureFiscalPaymentsReady:async()=>true,$message:{error:m=>errors.push(m)},
        $http:{post:async()=>{throw {response:{status:422,data:{errors:{series_id:['Seleccione una serie.']}}}}}}}
    await component.methods.submit.call(ctx)
    await new Promise(resolve=>setImmediate(resolve))
    assert.deepEqual(errors,['Seleccione una serie.'])
    assert.equal(ctx.document.payments[0],receipt)
})

test('delivery order retains the item IVA classification when generating its invoice',()=>{
    const ctx={affectation_igv_type:{id:'10'},affectation_igv_types:[{id:'10'},{id:'20'}],
        currencyTypeIdActive:'VES',exchangeRateSale:'12.5',percentage_igv:'0.16'}
    const item={id:1,currency_type_id:'USD',sale_unit_price:'10',unit_price:'10',has_igv:false,
        quantity:'0.3',unit_type_id:'KG',sale_affectation_igv_type_id:'20'}
    const row=component.methods.onGetItems.call(ctx,item)
    assert.equal(row.affectation_igv_type_id,'20')
    assert.equal(row.total,'37.5')
    assert.equal(row.total_igv,'0')
})

test('delivery order submission waits for fiscal readiness and reports receipt errors', async () => {
    const messages = []
    const requests = []
    const ctx = {
        document: {document_type_id: '01', exchange_rate_sale:'40', payments: [{payment_method_type_id: '13', currency_type_id: 'USD', original_amount: '2.50', exchange_rate: '40', payment: '100.00', payment_destination_id: 4}]},
        assignDocument() {}, onCalculateTotals() {}, validatePaymentDestination: async () => ({error_by_item: 0}),
        ensureFiscalPaymentsReady: async () => false,
        $message: {error: message => messages.push(message)},
        $http: {post: async (url, data) => {requests.push({url, data}); throw {response: {status: 422, data: {errors: {currency_type_id: ['La moneda no coincide.']}}}}}},
    }
    await component.methods.submit.call(ctx)
    assert.equal(requests.length, 0)
    ctx.ensureFiscalPaymentsReady = async () => true
    await component.methods.submit.call(ctx)
    await new Promise(resolve => setImmediate(resolve))
    assert.equal(requests.length, 1)
    assert.equal(requests[0].data.payments[0].original_amount, '2.50')
    assert.equal(requests[0].data.payments[0].exchange_rate, '40')
    assert.deepEqual(messages, ['La moneda no coincide.'])
    assert.equal(ctx.loading_submit, false)
})

test('delivery order sends credit receipts and omits installments only for cash', async () => {
    const posts=[],messages=[]
    const receipt={payment_method_type_id:'01',currency_type_id:'USD',original_amount:'2.50',exchange_rate:'40',payment:'100',payment_destination_id:4}
    const fee={amount:'50.00'},document={document_type_id:'01',exchange_rate_sale:'40',payment_condition_id:'02',payments:[receipt],fee:[fee]}
    const ctx={document,assignDocument(){},onCalculateTotals(){},validatePaymentDestination:async()=>({error_by_item:0}),
        ensureFiscalPaymentsReady:async()=>true,$message:{error:m=>messages.push(m)},
        $http:{post:async (url,data)=>{posts.push(data);return {data:{success:false,message:'Conservar borrador'}}}}}
    await component.methods.submit.call(ctx)
    await new Promise(resolve=>setImmediate(resolve))
    assert.equal(posts[0].payments[0],receipt)
    assert.equal(posts[0].fee[0],fee)
    document.payment_condition_id='01'
    await component.methods.submit.call(ctx)
    await new Promise(resolve=>setImmediate(resolve))
    assert.equal(posts[1].fee.length,0)
    assert.equal(document.fee[0],fee)
    assert.deepEqual(messages,['Conservar borrador','Conservar borrador'])
})
