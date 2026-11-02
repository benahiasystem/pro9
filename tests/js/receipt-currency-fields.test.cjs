const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const compiler = require('vue-template-compiler')
const babel = require('@babel/core')
const transform = source => babel.transformSync(source, {configFile:false, babelrc:false, plugins:['@babel/plugin-transform-modules-commonjs']}).code
const math = {}
vm.runInNewContext(transform(fs.readFileSync('resources/js/helpers/exchange-rate-math.js','utf8')), {exports:math})
const source = compiler.parseComponent(fs.readFileSync('resources/js/components/ReceiptCurrencyFields.vue','utf8'))
const output = {}
vm.runInNewContext(transform(source.script.content), {exports:output, require:name=>name.includes('payment-classification')
    ? require('./helpers/load-module.cjs')(require('node:path').resolve('resources/js/helpers/payment-classification.js')) : math})
const component = output.default

test('reused receipts retain their received currency and amount when the catalog or document rate changes', () => {
    for (const origin of [{id:5},{receipt_parent_id:5},{receipt_origin:{id:5}},
        {payment_method_snapshot:{receipt_origin:{id:5}}},{source_sale_note_payment_id:5},
        {source_quotation_payment_id:5},{source_technical_service_payment_id:5},{_reused_receipt:true}]) {
        const row={payment:'100',currency_type_id:'VES',original_amount:'100',exchange_rate:'10',...origin};
        const before=JSON.stringify(row);
        const ctx={row,methodCurrency:'USD',documentCurrency:'VES',documentRate:'20',
            $set:(obj,key,value)=>{obj[key]=value},$delete:(obj,key)=>{delete obj[key]},clearServerError(){}};
        ctx.proposeReceived=()=>component.methods.proposeReceived.call(ctx);
        ctx.updateApplied=()=>component.methods.updateApplied.call(ctx);
        component.methods.syncMethod.call(ctx,true);
        component.watch.documentRate.call(ctx,'20','10');
        component.methods.changeReceived.call(ctx,'5');
        component.methods.changeRate.call(ctx,'20');
        assert.equal(JSON.stringify(row),before,JSON.stringify(origin));
    }
});

test('a missing documentary rate keeps the rate field available for same-currency payments', () => {
    assert.equal(component.computed.hasDocumentRate.call({documentRate:null}),false)
    assert.equal(component.computed.hasDocumentRate.call({documentRate:'0'}),false)
    assert.equal(component.computed.hasDocumentRate.call({documentRate:'400'}),true)
    assert.match(source.template.content,/methodCurrency !== documentCurrency \|\| !hasDocumentRate/)
})

test('hotel creates a secure UUID for retrying one operation', () => {
    const operation = {}
    const window = {crypto: {getRandomValues: bytes => { bytes.fill(17); return bytes }}}
    vm.runInNewContext(transform(fs.readFileSync('resources/js/helpers/payment-operation-key.js','utf8')), {exports:operation, window, Uint8Array})
    assert.match(operation.newPaymentOperationKey(), /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/)
    window.crypto = null
    assert.throws(() => operation.newPaymentOperationKey(), /forma segura/)
})

test('proposes method currency and converts the received amount', () => {
    const row = {payment:'4000', payment_method_type_id:'13'}
    const ctx = {row, methodCurrency:'USD', documentCurrency:'VES', documentRate:'400', error:'', lastApplied:null,
        $set:(object,key,value)=>{object[key]=value}}
    ctx.clearServerError = () => {}
    ctx.proposeReceived = () => component.methods.proposeReceived.call(ctx)
    ctx.updateApplied = () => component.methods.updateApplied.call(ctx)
    component.methods.syncMethod.call(ctx)
    assert.equal(row.currency_type_id,'USD')
    assert.equal(row.original_amount,'10.00')
    assert.equal(row.payment,'4000')
    component.methods.changeReceived.call(ctx,'5')
    assert.equal(row.payment,'2000.00')
})

test('invalid rate leaves the received amount unset', () => {
    const row = {payment:'100'}
    const ctx = {row, methodCurrency:'USD', documentCurrency:'VES', documentRate:'0', error:'',
        $set:(object,key,value)=>{object[key]=value}}
    ctx.proposeReceived = () => component.methods.proposeReceived.call(ctx)
    component.methods.syncMethod.call(ctx)
    assert.equal(row.original_amount,null)
    assert.match(ctx.error,/tasa válida/)
})

test('balance check uses the rounded received amount and its own rate', () => {
    const row={payment:'1', currency_type_id:'USD', original_amount:'10.006', exchange_rate:'400'}
    assert.equal(math.appliedReceiptAmount(row,'VES').decimal(), '4004')
    assert.equal(math.appliedReceiptAmount(row,'VES').compare(math.exactAmount('4000')), 1)
    assert.equal(math.appliedReceiptAmount({currency_type_id:'VES',original_amount:'400.00',exchange_rate:'400'},'USD').decimal(), '1')
    assert.throws(() => math.appliedReceiptAmount({...row, exchange_rate:'0'},'VES'), /tasa positiva/)
})

test('remounting a draft keeps its manually selected rate and received amount', () => {
    const row = {payment:'4500', currency_type_id:'USD', original_amount:'10', exchange_rate:'450'}
    const ctx = {row, methodCurrency:'USD', documentCurrency:'VES', documentRate:'400', error:'',
        $set:(object,key,value)=>{object[key]=value}, proposeReceived:()=>{throw Error('unexpected recalculation')}}
    ctx.updateApplied = () => component.methods.updateApplied.call(ctx)
    component.methods.syncMethod.call(ctx)
    assert.equal(row.exchange_rate,'450')
    assert.equal(row.original_amount,'10')
})

test('shows server currency, received amount and rate errors without HTML interpolation', () => {
    const row={errors:{currency_type_id:['La moneda cambió'],original_amount:['Importe inválido'],exchange_rate:['Tasa inválida']}}
    assert.deepEqual(Array.from(component.computed.serverErrors.call({row})), ['La moneda cambió','Importe inválido','Tasa inválida'])
    assert.deepEqual(Array.from(component.computed.serverErrors.call({row:{}})), [])
    assert.match(source.template.content, /\{\{ message \}\}/)
    assert.doesNotMatch(source.template.content, /v-html/)
    component.methods.clearServerError.call({row,$delete:(object,key)=>delete object[key]},'exchange_rate')
    assert.deepEqual(Array.from(component.computed.serverErrors.call({row})), ['La moneda cambió','Importe inválido'])
})

test('quotation and technical service preserve wrapped validation errors for the currency fields', async () => {
    for (const path of ['resources/js/views/tenant/quotations/partials/payments.vue', 'modules/Sale/Resources/assets/js/views/technical-services/partials/payments.vue']) {
        const parsed=compiler.parseComponent(fs.readFileSync(path,'utf8'))
        const view={}
        vm.runInNewContext(transform(parsed.script.content), {exports:view, require:name=>name.includes('exchange-rate-math') ? math : {}})
        const row={payment:'1',original_amount:'1',currency_type_id:'VES',exchange_rate:'400',errors:{}}
        const response={status:422,data:{message:'Error de validación',errors:{currency_type_id:['La moneda cambió']}}}
        const ctx={records:[row],document:{currency_type_id:'VES',total_difference:'5'},
            $http:{post:()=>Promise.reject({response})},$message:{error:()=>{throw Error('unexpected generic error')}}}
        view.default.methods.clickSubmit.call(ctx,0)
        await new Promise(resolve=>setImmediate(resolve))
        assert.equal(row.errors.currency_type_id[0], 'La moneda cambió', path)
        assert.deepEqual(Array.from(component.computed.serverErrors.call({row})),['La moneda cambió'],path)
    }
})

test('component template compiles', () => {
    assert.deepEqual(compiler.compile(source.template.content).errors,[])
    for (const path of ['modules/Finance/Resources/assets/js/views/income/form.vue', 'resources/js/views/tenant/quotations/form.vue', 'resources/js/views/tenant/quotations/form_edit.vue', 'resources/js/views/tenant/quotations/partials/payments.vue', 'modules/Sale/Resources/assets/js/views/technical-services/partials/payments.vue', 'modules/Sale/Resources/assets/js/views/contracts/form.vue', 'modules/Hotel/Resources/assets/js/views/rooms/Rent.vue', 'modules/Hotel/Resources/assets/js/views/rooms/AddProductToRoom.vue', 'resources/js/views/tenant/sale_notes/partials/payments.vue', 'resources/js/views/tenant/sale_notes/form.vue', 'resources/js/views/tenant/pos/partials/fast_payment.vue', 'resources/js/views/tenant/pos/partials/fast_payment_garage.vue', 'resources/js/views/tenant/pos/partials/payment.vue', 'resources/js/views/tenant/pos/partials/multiple_payment.vue', 'resources/js/views/tenant/pos/partials/multiple_payment_garage.vue']) {
        const view = compiler.parseComponent(fs.readFileSync(path,'utf8'))
        assert.deepEqual(compiler.compile(view.template.content).errors,[],path)
        assert.match(view.template.content, /receipt-currency-fields/)
    }
})

test('displayed applied amount follows rounded received amount after conversion', () => {
    const row={payment:'100', currency_type_id:'USD', original_amount:'10.006', exchange_rate:'400'}
    const ctx={row,methodCurrency:'USD',documentCurrency:'VES',error:'',lastApplied:null,
        $set:(object,key,value)=>{object[key]=value}}
    component.methods.updateApplied.call(ctx)
    assert.equal(row.payment,'4004.00')
    row.payment='100'; row.exchange_rate='3'
    component.methods.proposeReceived.call(ctx)
    assert.equal(row.original_amount,'33.33')
    assert.equal(row.payment,'99.99')
})

test('reverse conversion uses cent settlement before applying the exchange rate', () => {
    const row={currency_type_id:'VES', original_amount:'1.005', exchange_rate:'2', payment:'0'}
    const ctx={row,methodCurrency:'VES',documentCurrency:'USD',error:'',lastApplied:null,
        $set:(object,key,value)=>{object[key]=value}}
    component.methods.updateApplied.call(ctx)
    assert.equal(row.payment,'0.51')
})

test('shared receipt fields disclose rounding of the received amount', () => {
    assert.equal(component.computed.settledReceived.call({row:{original_amount:'10.006'}}),'10.01')
    assert.equal(component.computed.settledReceived.call({row:{original_amount:'10.00'}}),null)
    assert.equal(component.computed.settledReceived.call({row:{original_amount:''}}),null)
})

test('remounting a shared receipt draft reconciles its applied amount using its manual rate',()=>{
    const row={payment:'4002.40',currency_type_id:'USD',original_amount:'10.006',exchange_rate:'400'}
    const ctx={row,methodCurrency:'USD',documentCurrency:'VES',documentRate:'500',error:'',lastApplied:null,
        $set:(obj,key,value)=>{obj[key]=value}}
    ctx.proposeReceived=()=>component.methods.proposeReceived.call(ctx)
    ctx.updateApplied=()=>component.methods.updateApplied.call(ctx)
    component.methods.syncMethod.call(ctx)
    assert.equal(row.exchange_rate,'400')
    assert.equal(row.original_amount,'10.006')
    assert.equal(row.payment,'4004.00')
})
