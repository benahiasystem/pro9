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

test('physical currency and banking destinations are independent',()=>{
    assert.deepEqual(ids(exported.classifiedDestinations(destinations,methods,{payment_method_type_id:'01'},'VES')),['cash']);
    assert.deepEqual(ids(exported.classifiedDestinations(destinations,methods,{payment_method_type_id:'10'},'VES')),[]);
    assert.deepEqual(ids(exported.classifiedDestinations(destinations,methods,{payment_method_type_id:'10'},undefined)),['cash']);
    assert.deepEqual(ids(exported.classifiedDestinations(destinations,methods,{payment_method_type_id:'10',currency_type_id:'USD'},'VES')),['cash']);
    assert.deepEqual(ids(exported.classifiedDestinations(destinations,methods,{payment_method_type_id:'04'},'VES')),[1]);
    assert.deepEqual(ids(exported.classifiedDestinations(destinations,methods,{payment_method_type_id:'04'},'USD')),[2]);
    assert.deepEqual(ids(exported.classifiedDestinations(destinations,methods,{payment_method_type_id:'09'},'VES')),[]);
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
