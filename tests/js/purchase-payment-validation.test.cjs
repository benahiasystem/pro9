const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs'), vm = require('node:vm'), path = require('node:path')
const compiler = require('vue-template-compiler'), parser = require('@babel/parser')
const generate = require('@babel/generator').default
const moment = require('moment'), _ = require('lodash')
const {purchasePaymentValidation, showPurchaseValidationError, markAutomaticPurchasePayment, isUntouchedAutomaticPurchasePayment} = require('./helpers/load-module.cjs')(path.resolve('resources/js/helpers/purchase-payment-validation.js'))
const {exactAmount} = require('./helpers/load-module.cjs')(path.resolve('resources/js/helpers/exchange-rate-math.js'))
const {suggestInstallments, suggestRemainingPayment} = require('./helpers/load-module.cjs')(path.resolve('resources/js/helpers/document-creation.js'))
const {defaultCashForCurrency} = require('./helpers/load-module.cjs')(path.resolve('resources/js/helpers/payment-classification.js'))

function method(file, name) {
    const source = compiler.parseComponent(fs.readFileSync(file, 'utf8'))
    assert.deepEqual(compiler.compile(source.template.content).errors, [])
    const tree = parser.parse(source.script.content, {sourceType:'module'})
    const methods = tree.program.body.find(node=>node.type === 'ExportDefaultDeclaration').declaration.properties.find(node=>node.key.name === 'methods').value
    const entry = methods.properties.find(node=>node.key?.name === name)
    return vm.runInNewContext(`({${generate(entry).code}}).${name}`, {purchasePaymentValidation, showPurchaseValidationError, markAutomaticPurchasePayment, isUntouchedAutomaticPurchasePayment, defaultCashForCurrency, exactAmount, suggestRemainingPayment, suggestInstallments, moment, _})
}

test('purchase payment amounts compare decimal fractions without float errors',()=>{
    const validate = (values,total='0.30') => purchasePaymentValidation(values.map(payment=>({payment})),total)
    assert.equal(validate(['0.10','0.20']).exceeds_total,false)
    assert.equal(validate(['0.10','0.200000000001']).exceeds_total,true)
    for (const value of ['',null,'foo','0','-1']) assert.equal(validate([value]).error_by_item,1)
    assert.equal(validate(['0.1'],'invalido').error_by_item,1)
})

for (const file of ['resources/js/views/tenant/purchases/form.vue','resources/js/views/tenant/purchases/form_edit.vue']) {
 test(`${file} accepts exact cents and rejects invalid or excessive payments`,async()=>{
    const run = method(file,'validate_payments')
    const ctx = {form:{payments:[{payment:'0.10'},{payment:'0.20'}],total:'0.30',items:[{affectation_igv_type:{free:false}}],has_client:false,has_payment:false}}
    assert.equal((await run.call(ctx)).success,true)
    ctx.form.payments[1].payment='0.200000000001'
    assert.equal((await run.call(ctx)).success,false)
    ctx.form.payments[1].payment='foo'
    assert.equal((await run.call(ctx)).success,false)
 })
}

test('purchase order conversion submits exact cents but blocks excess and invalid input',async()=>{
    const file='modules/Purchase/Resources/assets/js/views/purchase-orders/partials/document_generate.vue'
    const validate=method(file,'validate_payments'), submit=method(file,'submit')
    const sent=[], errors=[]
    const ctx={form:{payments:[{payment:'0.10'},{payment:'0.20'}],total:'0.30'},resource:'purchases',
      $message:{error:message=>errors.push(message)},$http:{post:async(_url,form)=>{sent.push(form);return {data:{success:false,message:'Rechazo de prueba'}}}}}
    ctx.validate_payments=validate.bind(ctx)
    await submit.call(ctx);assert.equal(sent.length,1)
    ctx.form.payments[1].payment='0.200000000001';await submit.call(ctx);assert.equal(sent.length,1)
    ctx.form.payments[1].payment='foo';await submit.call(ctx);assert.equal(sent.length,1)
    assert.equal(errors.length,3)
})

test('purchase validation displays server detail without losing the draft',()=>{
    for (const data of [{errors:{'payments.0.payment_destination_id':['Cuenta incompatible']},message:'Datos inválidos'},
        {'payments.0.payment_destination_id':['Cuenta incompatible']}]) {
        const messages=[], form={payments:[{payment:'0.10'}]}
        const ctx={form,errors:{},$message:{error:message=>messages.push(message)}}
        showPurchaseValidationError({response:{status:422,data}},ctx)
        assert.equal(messages[0],'Cuenta incompatible')
        assert.equal(ctx.form,form)
        assert.deepEqual(Array.from(ctx.errors['payments.0.payment_destination_id']),['Cuenta incompatible'])
    }
    const messages=[];showPurchaseValidationError(new Error('Red no disponible'),{$message:{error:m=>messages.push(m)}})
    assert.equal(messages[0],'Red no disponible')
    showPurchaseValidationError({response:{status:500,data:{message:'SQLSTATE: datos privados'}}},{$message:{error:m=>messages.push(m)}})
    assert.equal(messages[1],'No se pudo guardar la compra. Inténtelo nuevamente.')
})

test('editing a purchase normalizes persisted indexed discounts and charges before calculating totals',async()=>{
    const run=method('resources/js/views/tenant/purchases/form_edit.vue','initRecord')
    const purchase={id:4,document_type_id:'01',series:'P001',number:'900005',currency_type_id:'USD',
        payment_condition_id:'02',purchase_payments:[{id:4,payment:'0.25'}],fee:[{amount:'0.75'}],
        items:[{discounts:{0:{amount:'0.10'}},charges:{},affectation_igv_type:{free:false}}]}
    let checked=false
    const ctx={resource:'purchases',resourceId:4,form:{},$http:{get:async()=>({data:{data:{purchase}}})},
        setCurrencyType(){},changeDocumentType(){},getPercentageIgv:async()=>{},
        calculateTotal(){assert.equal(Array.isArray(this.form.items[0].discounts),true);assert.equal(Array.isArray(this.form.items[0].charges),true);checked=true}}
    await run.call(ctx)
    assert.equal(checked,true);assert.equal(ctx.form.payments[0].payment,'0.25');assert.equal(ctx.form.fee[0].amount,'0.75')
})

test('editing a purchase keeps saved installments and suggests only the remaining balance',()=>{
    const file='resources/js/views/tenant/purchases/form_edit.vue'
    const calculate=method(file,'calculateFee')
    const ctx={form:{total:'1',payments:[{id:4,payment:'0.25'}],fee:[{id:3,amount:'0.50'},{id:null,amount:'0'}]}}
    calculate.call(ctx)
    assert.equal(ctx.form.fee[0].amount,'0.50')
    assert.equal(ctx.form.fee[1].amount,'0.25')
    ctx.form.payments.push({payment:'0.10'});calculate.call(ctx)
    assert.equal(ctx.form.fee[0].amount,'0.50')
    assert.equal(ctx.form.fee[1].amount,'0.15')
})

test('purchase generated from a USD credit order retains the condition without creating a payment',async()=>{
    const run=method('resources/js/views/tenant/purchases/form.vue','isGeneratePurchaseOrder')
    const order={id:3,supplier_id:7,supplier:{identity_document_type_id:'6'},currency_type_id:'USD',
      payment_method_type_id:'06',date_of_due:'2026-12-02',total:'1',items:[{item:{},quantity:'1'}]}
    const ctx={purchase_order_id:3,creditPaymentMethod:[{id:'06',collection_kind:'credit',number_days:30}],
      currency_types:[{id:'USD'}],form:{date_of_issue:'2026-11-02',total_payable:'0',payments:[],fee:[]},
      $http:{get:async()=>({data:{data:{purchase_order:order,warehouse:{id:5}}}})}}
    await run.call(ctx)
    assert.equal(ctx.form.has_payment,true);assert.equal(ctx.form.payment_condition_id,'02')
    assert.equal(ctx.form.currency_type_id,'USD');assert.equal(ctx.form.payments.length,0)
    assert.equal(ctx.form.fee[0].payment_method_type_id,'06');assert.equal(ctx.form.fee[0].date,'2026-12-02')
    assert.equal(ctx.form.fee[0].currency_type_id,'USD');assert.equal(ctx.form.fee[0].amount,'1.00')
    ctx.form.payments.push({payment:'0.25'})
    method('resources/js/views/tenant/purchases/form.vue','calculateFee').call(ctx)
    assert.equal(ctx.form.fee[0].amount,'0.75')
    ctx.form.fee[0].amount='0.50';ctx.form.payments[0].payment='0.30'
    method('resources/js/views/tenant/purchases/form.vue','calculateFee').call(ctx)
    assert.equal(ctx.form.fee[0].amount,'0.50','la cuota editada no se sobrescribe')
})

test('switching a purchase to credit discards only empty rows and keeps entered payments',()=>{
    const run=method('resources/js/views/tenant/purchases/form.vue','changePaymentCondition')
    for(const condition of ['02','03']) {
        const ctx={form:{payment_condition_id:condition,fee:[],payments:[{payment:'0.00'},{payment:'0.10'},{id:7,payment:'0'}]},
          clickAddFeeNew(){},clickAddFee(){},initDataPaymentCondition(){}}
        run.call(ctx)
        assert.deepEqual(Array.from(ctx.form.payments,row=>row.payment),['0.10','0'])
    }
})

for (const file of ['resources/js/views/tenant/purchases/form.vue','resources/js/views/tenant/purchases/form_edit.vue']) {
 test(`${file} removes only an untouched automatic cash proposal when selecting credit`,()=>{
    const change=method(file,'changePaymentCondition')
    const auto={id:null,payment_method_type_id:'04',payment_destination_id:'cash',reference:null,payment:'1.00'}
    const edited={...auto,payment:'0.25'}, explicit={...auto}, recorded={...auto,id:7}
    markAutomaticPurchasePayment(auto);markAutomaticPurchasePayment(edited);edited.payment='0.30'
    const ctx={form:{payment_condition_id:'02',fee:[],payments:[auto,edited,explicit,recorded]},
        clickAddFeeNew(){},clickAddFee(){},initDataPaymentCondition(){}}
    change.call(ctx)
    assert.deepEqual(Array.from(ctx.form.payments),[edited,explicit,recorded])
 })
 test(`${file} keeps tracking the automatic proposal after a total recalculation`,()=>{
    const auto={id:null,payment_method_type_id:'04',payment_destination_id:'cash',reference:null,payment:'0'}
    const ctx={form:{payment_condition_id:'01',currency_type_id:'VES',total:'0',payments:[auto],fee:[]}}
    method(file,'calculatePayments').call(ctx)
    markAutomaticPurchasePayment(auto)
    ctx.form.total='1';method(file,'calculatePayments').call(ctx)
    assert.equal(auto.payment,'1.00')
    assert.equal(isUntouchedAutomaticPurchasePayment(auto),true)
    ctx.form.payment_condition_id='02';ctx.clickAddFeeNew=()=>{};ctx.clickAddFee=()=>{};ctx.initDataPaymentCondition=()=>{}
    method(file,'changePaymentCondition').call(ctx)
    assert.equal(ctx.form.payments.length,0)
 })
 test(`${file} marks the row created automatically by the condition change`,()=>{
    const ctx={form:{payment_condition_id:'01',currency_type_id:'VES',total:'1',payments:[],fee:[]},
      payment_method_types:[{id:'04',collection_kind:'cash',currency_type_id:'VES',is_active:true}],payment_destinations:[{id:'cash'}],
      calculateFee(){},initDataPaymentCondition(){},clickAddFeeNew(){},clickAddFee(){}}
    ctx.calculatePayments=method(file,'calculatePayments').bind(ctx)
    ctx.clickAddPayment=method(file,'clickAddPayment').bind(ctx)
    const change=method(file,'changePaymentCondition')
    change.call(ctx)
    assert.equal(ctx.form.payments[0].payment,'1.00')
    ctx.form.payment_condition_id='02';change.call(ctx)
    assert.equal(ctx.form.payments.length,0)
 })
}

test('selecting credit without an active credit method leaves a selectable installment instead of throwing',()=>{
    const file='resources/js/views/tenant/purchases/form.vue'
    const ctx={creditPaymentMethod:[],form:{date_of_issue:'2026-11-02',currency_type_id:'USD',fee:[],payments:[],total:'1'}}
    ctx.calculateFee=method(file,'calculateFee').bind(ctx)
    method(file,'clickAddFeeNew').call(ctx)
    assert.equal(ctx.form.fee[0].payment_method_type_id,null)
    assert.equal(ctx.form.fee[0].amount,'1.00')
})
