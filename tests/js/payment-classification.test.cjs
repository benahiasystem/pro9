const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const babel = require('@babel/core');
const exported = {};
vm.runInNewContext(babel.transformSync(fs.readFileSync('resources/js/helpers/payment-classification.js','utf8'), {
    configFile:false, babelrc:false, plugins:['@babel/plugin-transform-modules-commonjs'],
}).code,{exports:exported});
const methods=[{id:'01',collection_kind:'cash',cash_currency_type_id:'VES'},{id:'10',collection_kind:'cash',cash_currency_type_id:'USD'},
    {id:'04',collection_kind:'account'},{id:'09',collection_kind:'credit'}];
const destinations=[{id:'cash',report_version:2},{id:1,currency_type_id:'VES',report_version:2},{id:2,currency_type_id:'USD',report_version:2}];
const ids=(rows)=>Array.from(rows,row=>row.id);

test('default cash sorts custom IDs by Unicode code point rather than locale',()=>{
    const rows=['^','!','01','𐀀','\uE000'].map(id=>({id,collection_kind:'cash',currency_type_id:'VES',is_active:true}))
    assert.equal(exported.defaultCashForCurrency(rows,'VES').id,'!')
    assert.equal(exported.defaultCashForCurrency(rows.slice(3),'VES').id,'\uE000')
})

test('store configuration can select bank destinations without a payment-method field',()=>{
    const compiler=require('vue-template-compiler');
    const source=compiler.parseComponent(fs.readFileSync('modules/Order/Resources/assets/js/views/mi_tienda_pe/form.vue','utf8'));
    assert.deepEqual(compiler.compile(source.template.content).errors,[]);
    assert.match(source.template.content,/v-for="option in payment_destinations"/);
    assert.doesNotMatch(source.template.content,/classifiedPaymentDestinations\(payment_destinations, form\)/);
});

test('cash default is active, matches document currency, and does not depend on a seeded ID',()=>{
    const methods=[
        {id:'01',collection_kind:'account',currency_type_id:'USD',is_active:true},
        {id:'10',collection_kind:'cash',currency_type_id:'USD',is_active:true},
        {id:'04',collection_kind:'cash',currency_type_id:'USD',is_active:true},
        {id:'02',collection_kind:'cash',currency_type_id:'USD',is_active:false},
        {id:'07',collection_kind:'cash',currency_type_id:'VES',is_active:true},
    ];
    assert.equal(exported.defaultCashForCurrency(methods,'USD').id,'04');
    assert.equal(exported.defaultCashForCurrency(methods,'VES').id,'07');
    assert.equal(exported.defaultCashForCurrency(methods,null),null);
    assert.equal(exported.defaultCashForCurrency(methods,'EUR'),null);
});

test('payment validation extracts the server message without guessing a payment row',()=>{
    assert.equal(exported.paymentValidationMessage({currency_type_id:['La moneda cambió']}),'La moneda cambió');
    assert.equal(exported.paymentValidationMessage({message:'Error',errors:{'payments.2.payment_destination_id':['Cuenta incompatible']}}),'Cuenta incompatible');
    assert.equal(exported.paymentValidationMessage({message:{exchange_rate:['Tasa inválida']}}),'Tasa inválida');
    assert.equal(exported.paymentValidationMessage({errors:{source_sale_note_payment_id:['El cobro original ya fue aplicado']}}),'El cobro original ya fue aplicado');
    assert.equal(exported.paymentValidationMessage({errors:{source_quotation_payment_id:['Cobro ya aplicado']}}),'Cobro ya aplicado');
    assert.equal(exported.paymentValidationMessage({errors:{financial_intermediation:['Indique la intermediación']}}),'Indique la intermediación');
    assert.equal(exported.paymentValidationMessage({total:['El total es menor que lo cobrado']}),'El total es menor que lo cobrado');
    assert.equal(exported.paymentValidationMessage({customer_id:['Cliente inválido']}),null);
    assert.equal(exported.paymentValidationMessage(null),null);
});

test('hotel exposes wrapped and indexed payment errors and retains other form errors',()=>{
    const messages=[]; const fallback=[];
    const view={errors:{},$message:{error:value=>messages.push(value)},axiosError:error=>fallback.push(error)};
    exported.handleHotelPaymentError({response:{status:422,data:{message:'No válido',errors:{currency_type_id:['Moneda inactiva'],room:['Habitación ocupada']}}}},view);
    assert.equal(messages.pop(),'Moneda inactiva');
    assert.equal(view.errors.room[0],'Habitación ocupada');
    exported.handleHotelPaymentError({response:{status:422,data:{'products.1.rent_payment.payment_destination_id':['Cuenta incompatible']}}},view);
    assert.equal(messages.pop(),'Cuenta incompatible');
    assert.equal(view.errors['products.1.rent_payment.payment_destination_id'][0],'Cuenta incompatible');
    exported.handleHotelPaymentError({response:{status:422,data:{message:{exchange_rate:['Tasa inválida']}}}},view);
    assert.equal(messages.pop(),'Tasa inválida');
    assert.equal(view.errors.exchange_rate[0],'Tasa inválida');
    exported.handleHotelPaymentError({response:{status:422,data:{customer_id:['Cliente inválido']}}},view);
    assert.equal(messages.length,0);
    assert.equal(view.errors.customer_id[0],'Cliente inválido');
    exported.handleHotelPaymentError({message:'Sin conexión'},view);
    assert.equal(messages.pop(),'Sin conexión');
    exported.handleHotelPaymentError({response:{status:500}},view);
    assert.equal(fallback.length,1);
});

test('physical currency and banking destinations are independent',()=>{
    assert.deepEqual(ids(exported.classifiedDestinations(destinations,methods,{payment_method_type_id:'01'},'VES')),['cash']);
    assert.deepEqual(ids(exported.classifiedDestinations(destinations,methods,{payment_method_type_id:'10'},'VES')),[]);
    assert.deepEqual(ids(exported.classifiedDestinations(destinations,methods,{payment_method_type_id:'10'},undefined)),['cash']);
    assert.deepEqual(ids(exported.classifiedDestinations(destinations,methods,{payment_method_type_id:'10',currency_type_id:'USD'},'VES')),['cash']);
    assert.deepEqual(ids(exported.classifiedDestinations(destinations,methods,{payment_method_type_id:'04'},'VES')),[1]);
    assert.deepEqual(ids(exported.classifiedDestinations(destinations,methods,{payment_method_type_id:'04'},'USD')),[2]);
    assert.deepEqual(ids(exported.classifiedDestinations(destinations,methods,{payment_method_type_id:'09'},'VES')),[]);
});
test('the method currency determines eligible bank accounts',()=>{
    const usdMethod = [{id:'13',collection_kind:'account',currency_type_id:'USD'}];
    assert.deepEqual(ids(exported.classifiedDestinations(destinations,usdMethod,{payment_method_type_id:'13'},'VES')),[2]);
    assert.deepEqual(ids(exported.classifiedDestinations(destinations,usdMethod,{payment_method_type_id:'13',currency_type_id:'VES'},'VES')),[]);
    assert.deepEqual(ids(exported.classifiedDestinations(destinations,usdMethod,{payment_method_type_id:'13',currency_type_id:'USD'},'VES')),[2]);
});
test('supplier and loan payments use their own currency even if the receipt method has another currency',()=>{
    const mixin=exported.default.methods;
    const method=[{id:'13',collection_kind:'account',currency_type_id:'USD'}];
    const row={payment_method_type_id:'13',currency_type_id:'VES'};
    const payment={payment_method_types:method,form:{currency_type_id:'VES'},$options:{paymentCurrencyPolicy:'document'}};
    const receipt={payment_method_types:method,form:{currency_type_id:'VES'}};
    assert.deepEqual(ids(mixin.classifiedPaymentDestinations.call(payment,destinations,row)),[1]);
    assert.deepEqual(ids(mixin.classifiedPaymentDestinations.call(receipt,destinations,row)),[]);
    const purchase={payment_method_types:method,purchase:{currency_type_id:'VES'},$options:{paymentCurrencyPolicy:'document'}};
    assert.deepEqual(ids(mixin.classifiedPaymentDestinations.call(purchase,destinations,{payment_method_type_id:'13'})),[1]);
});
test('expense IDs never use the payment-method catalog',()=>{
    assert.deepEqual(ids(exported.classifiedDestinations(destinations,methods,{expense_method_type_id:1},'USD',true)),['cash']);
    assert.deepEqual(ids(exported.classifiedDestinations(destinations,methods,{expense_method_type_id:2},'VES',true)),[1]);
});
test('classification applies without an activation flag on destination options',()=>{
    const legacy=destinations.map(({report_version,...destination})=>destination);
    assert.deepEqual(ids(exported.classifiedDestinations(legacy,methods,{payment_method_type_id:'04'},'VES')),[1]);
});
test('an incompatible default is cleared and a new banking destination remains selectable',()=>{
    const mixin=exported.default.methods;
    const row={payment_method_type_id:'04',payment_destination_id:'cash',payment_destination_disabled:true};
    const instance={payment_destinations:destinations,payment_method_types:methods,form:{currency_type_id:'VES'},$set:(obj,key,value)=>{obj[key]=value}};
    instance.classifiedPaymentDestinations=(items,payment)=>mixin.classifiedPaymentDestinations.call(instance,items,payment);
    mixin.clearIncompatiblePaymentDestinations.call(instance,[row]);
    assert.equal(row.payment_destination_id,null);assert.equal(row.collection_kind,'account');
    assert.equal(mixin.classifiedPaymentDestinationDisabled.call(instance,destinations,row),false);
});

test('document payment rows clear incompatible destinations without modifying recorded receipts',()=>{
    const mixin=exported.default;
    const fresh={payment_method_type_id:'13',currency_type_id:'USD',payment_destination_id:1};
    const recorded={id:5,payment_method_type_id:'13',currency_type_id:'USD',payment_destination_id:1};
    const instance={payment_destinations:destinations,payment_method_types:[{id:'13',collection_kind:'account',currency_type_id:'USD'}],
        document:{currency_type_id:'VES',payments:[fresh,recorded]},$set:(obj,key,value)=>{obj[key]=value}};
    instance.classifiedPaymentDestinations=(items,row)=>mixin.methods.classifiedPaymentDestinations.call(instance,items,row);
    instance.clearIncompatiblePaymentDestinations=rows=>mixin.methods.clearIncompatiblePaymentDestinations.call(instance,rows);
    mixin.watch['document.payments'].handler.call(instance,instance.document.payments);
    assert.equal(fresh.payment_destination_id,null);
    assert.equal(recorded.payment_destination_id,1);
    fresh.payment_destination_id=1;
    mixin.methods.refreshClassifiedPaymentDestinations.call(instance);
    assert.equal(fresh.payment_destination_id,null);
});

test('reused receipts retain their historical destination and nature when the catalog changes',()=>{
    const mixin=exported.default.methods;
    const origins=[{id:5},{receipt_parent_id:5},{receipt_origin:{id:5}},
        {payment_method_snapshot:{receipt_origin:{id:5},collection_kind:'cash'}},
        {source_sale_note_payment_id:5},{source_quotation_payment_id:5},{source_technical_service_payment_id:5},{_reused_receipt:true}];
    const rows=origins.map(origin=>({payment_method_type_id:'13',currency_type_id:'VES',
        collection_kind:'cash',payment_destination_id:'cash',...origin}));
    const instance={payment_destinations:destinations,payment_method_types:[{id:'13',collection_kind:'account',currency_type_id:'USD'}],
        form:{currency_type_id:'VES'},$set:(obj,key,value)=>{obj[key]=value}};
    instance.classifiedPaymentDestinations=(items,row)=>mixin.classifiedPaymentDestinations.call(instance,items,row);
    mixin.clearIncompatiblePaymentDestinations.call(instance,rows);
    rows.forEach(row=>{assert.equal(row.payment_destination_id,'cash');assert.equal(row.collection_kind,'cash')});
});

test('order draft rows clear a destination that conflicts with the method currency',()=>{
    const mixin=exported.default;
    const draft={payment_method_type_id:'13',currency_type_id:'USD',payment_destination_id:1};
    const instance={payment_destinations:destinations,payment_method_types:[{id:'13',collection_kind:'account',currency_type_id:'USD'}],
        form:{currency_type_id:'VES',prepayments:[draft]},$set:(obj,key,value)=>{obj[key]=value}};
    instance.classifiedPaymentDestinations=(items,row)=>mixin.methods.classifiedPaymentDestinations.call(instance,items,row);
    instance.clearIncompatiblePaymentDestinations=rows=>mixin.methods.clearIncompatiblePaymentDestinations.call(instance,rows);
    mixin.watch['form.prepayments'].handler.call(instance,instance.form.prepayments);
    assert.equal(draft.payment_destination_id,null);
});
