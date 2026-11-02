const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const compiler = require('vue-template-compiler');
const babel = require('@babel/core');
const parsed = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/partials/igtf_fields.vue','utf8'));
const exportsObject = {};
const mathExports = {};
vm.runInNewContext(babel.transformSync(fs.readFileSync('resources/js/helpers/exchange-rate-math.js','utf8'),{configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']}).code,{exports:mathExports});
vm.runInNewContext(babel.transformSync(parsed.script.content,{configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']}).code,{exports:exportsObject, require:name => name.includes('payment-classification') ? require('./helpers/load-module.cjs')(require('node:path').resolve('resources/js/helpers/payment-classification.js')) : mathExports, crypto:{randomUUID:()=> 'operation-uuid'}});
const component = exportsObject.default;
test('fiscal payment exposes a rate when the document lacks one',()=>{
    assert.equal(component.computed.hasDocumentRate.call({documentRate:null}),false);
    assert.equal(component.computed.hasDocumentRate.call({documentRate:'0'}),false);
    assert.equal(component.computed.hasDocumentRate.call({documentRate:'400'}),true);
    assert.match(parsed.template.content,/payment.currency_type_id !== documentCurrency \|\| !hasDocumentRate/);
});
function initialize(payment) {component.created.call({payment,$set:(obj,key,value)=>{obj[key]=value}});return payment;}
test('legacy payment rows default to no IGTF without adding a currency selector',()=>{
    const payment = initialize({payment:100});
    assert.equal(payment.igtf_status,'not_applicable');assert.equal(payment.exemption_reason,null);
    assert.equal(Object.hasOwn(payment,'currency_type_id'),false);
});
test('an explicitly exempt payment retains its classification and stated reason',()=>{
    const payment = initialize({payment:100,igtf_status:'exempt',exemption_reason:'Comprobante aportado'});
    assert.equal(payment.igtf_status,'exempt');assert.equal(payment.exemption_reason,'Comprobante aportado');
});

test('payment currency conversions preserve eight-decimal rates and separate IGTF',()=>{
    const payment = {currency_type_id:'USD',original_amount:'5',exchange_rate:'40.12345678'};
    const instance = {payment,documentCurrency:'VES',error:'',evaluate:()=>{}};
    component.methods.convertPrincipal.call(instance);
    assert.equal(payment.payment,'200.62'); assert.equal(payment.exchange_rate,'40.12345678');
    assert.equal(Object.hasOwn(payment,'tax_amount'),false);
});
test('selecting a USD method proposes received dollars without changing the applied bolivars',()=>{
    const payment={payment:'4000',payment_method_type_id:'13'};
    const instance={payment,methodCurrency:'USD',documentCurrency:'VES',documentRate:'400.00000000',error:'',
        $set:(obj,key,value)=>{obj[key]=value},evaluate:()=>{},clearServerError:()=>{}};
    component.methods.syncMethodCurrency.call(instance);
    assert.equal(payment.currency_type_id,'USD');
    assert.equal(payment.original_amount,'10.00');
    assert.equal(payment.payment,'4000');
    assert.equal(payment.exchange_rate,'400.00000000');
    assert.equal(payment.operation_key,'operation-uuid');
});
test('editing either fiscal amount keeps received and applied currency in sync',()=>{
    const payment={payment:'4000',currency_type_id:'USD',original_amount:'10',exchange_rate:'400'};
    const instance={payment,documentCurrency:'VES',error:'',lastApplied:null,
        $set:(obj,key,value)=>{obj[key]=value},evaluate:()=>{}};
    instance.proposeReceived=()=>component.methods.proposeReceived.call(instance);
    payment.payment='2000'; component.watch['payment.payment'].call(instance,payment.payment);
    assert.equal(payment.original_amount,'5.00');
    payment.original_amount='8'; component.methods.convertPrincipal.call(instance);
    assert.equal(payment.payment,'3200.00');
    component.watch['payment.payment'].call(instance,payment.payment);
    assert.equal(payment.original_amount,'8');
    payment.payment='4000'; component.watch['payment.payment'].call(instance,payment.payment);
    assert.equal(payment.original_amount,'10.00');
});
test('a missing exchange rate never invents a one-to-one conversion',()=>{
    const payment={payment:'4000',payment_method_type_id:'13'};
    const instance={payment,methodCurrency:'USD',documentCurrency:'VES',documentRate:null,error:'',
        $set:(obj,key,value)=>{obj[key]=value},evaluate:()=>{},clearServerError:()=>{}};
    component.methods.syncMethodCurrency.call(instance);
    assert.equal(payment.exchange_rate,null);
    assert.equal(payment.original_amount,null);
    assert.match(instance.error,/tasa válida/);
});
test('a stale proposal cannot overwrite the current collection', async()=>{
    let resolveFirst;
    const instance = {payment:{payment:100,igtf_status:'not_applicable'},documentCurrency:'VES',sequence:0,$set:(o,k,v)=>o[k]=v,
        $http:{post:()=>new Promise(resolve=>resolveFirst=resolve)}};
    const pending = component.methods.evaluate.call(instance);
    instance.sequence++;
    resolveFirst({data:{data:{payment:{igtf_status:'subject'}}}});
    await pending;
    assert.equal(instance.payment.igtf_status,'not_applicable');
});
test('Vue consumers compile with fiscal profiles and the shared payment fields',()=>{
    for (const name of ['documents/partials/igtf_fields','documents/partials/retention','fiscal_profiles/editor','pos/partials/multiple_payment_garage','pos/partials/multiple_payment','companies/form','persons/form','documents/partials/payments']) {
        const source=compiler.parseComponent(fs.readFileSync(`resources/js/views/tenant/${name}.vue`,'utf8'));
        const result=compiler.compile(source.template.content);
        assert.deepEqual(result.errors,[],name);
        assert.doesNotThrow(()=>babel.transformSync(source.script.content,{configFile:false,babelrc:false}),name);
    }
});

test('fiscal form displays the same settlement the server saves after rounding received currency',()=>{
    const payment={payment:'100',currency_type_id:'USD',original_amount:'10.006',exchange_rate:'400'}
    const instance={payment,documentCurrency:'VES',error:'',lastApplied:null,evaluate:()=>{},
        $set:(obj,key,value)=>{obj[key]=value}}
    component.methods.convertPrincipal.call(instance)
    assert.equal(payment.payment,'4004.00')
    payment.payment='100';payment.exchange_rate='3'
    component.methods.proposeReceived.call(instance)
    assert.equal(payment.original_amount,'33.33')
    assert.equal(payment.payment,'99.99')
})

test('method proposal and reverse conversion use the settled received amount',()=>{
    const payment={payment:'100',payment_method_type_id:'13'}
    const ctx={payment,methodCurrency:'USD',documentCurrency:'VES',documentRate:'3',error:'',lastApplied:null,
        $set:(obj,key,value)=>{obj[key]=value},evaluate:()=>{},clearServerError:()=>{}}
    component.methods.syncMethodCurrency.call(ctx)
    assert.equal(payment.original_amount,'33.33')
    assert.equal(payment.payment,'99.99')
    ctx.documentCurrency='USD'; payment.currency_type_id='VES';payment.original_amount='1.005';payment.exchange_rate='2'
    component.methods.convertPrincipal.call(ctx)
    assert.equal(payment.payment,'0.51')
})

test('fiscal form discloses the received amount rounded for settlement',()=>{
    assert.equal(component.computed.settledReceived.call({payment:{original_amount:'10.006'}}),'10.01')
    assert.equal(component.computed.settledReceived.call({payment:{original_amount:'10.00'}}),null)
    assert.equal(component.computed.settledReceived.call({payment:{original_amount:''}}),null)
})

test('fiscal fields display structured currency validation errors',()=>{
    const payment={errors:{currency_type_id:['La moneda del método cambió'],original_amount:['Indique el importe'],exchange_rate:['Tasa inválida']}}
    assert.deepEqual(Array.from(component.computed.serverErrors.call({payment})),['La moneda del método cambió','Indique el importe','Tasa inválida'])
})

test('document payment submission keeps wrapped currency errors on the payment row',async()=>{
    const parsed=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/partials/payments.vue','utf8'))
    const view={}
    vm.runInNewContext(babel.transformSync(parsed.script.content,{configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']}).code,
        {exports:view,require:()=>mathExports})
    const row={payment:'10',original_amount:'1',currency_type_id:'USD',exchange_rate:'10',errors:{}}
    const response={status:422,data:{message:'Error de validación',errors:{currency_type_id:['La moneda del método cambió']}}}
    const ctx={records:[row],documentId:4,showDialog:true,document:{currency_type_id:'VES',total_difference:'100'},resource:'document_payments',
        ensureFiscalPaymentsReady:async()=>true,$http:{post:()=>Promise.reject({response})},$message:{error:()=>{}},getRequestErrorMessage:()=> 'error'}
    await view.default.methods.clickSubmit.call(ctx,0)
    assert.deepEqual(Array.from(row.errors.currency_type_id),['La moneda del método cambió'])
})

test('same-currency settlement keeps the amount entered and displays the applied cents',()=>{
    const payment={payment:'10.006',currency_type_id:'VES',exchange_rate:'1'}
    const ctx={payment,documentCurrency:'VES',error:'',lastApplied:null,$set:(obj,key,value)=>{obj[key]=value}}
    component.methods.proposeReceived.call(ctx)
    assert.equal(payment.original_amount,'10.006')
    assert.equal(payment.payment,'10.01')
})

test('same-currency submission sends the received amount before rounding',async()=>{
    const parsed=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/partials/payments.vue','utf8'))
    const view={}
    vm.runInNewContext(babel.transformSync(parsed.script.content,{configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']}).code,
        {exports:view,require:()=>mathExports})
    const row={payment:'10.01',original_amount:'10.006',currency_type_id:'VES',exchange_rate:'1',errors:{}}
    let payload
    const ctx={records:[row],documentId:4,showDialog:true,document:{currency_type_id:'VES',total_difference:'100'},resource:'document_payments',
        ensureFiscalPaymentsReady:async()=>true,$http:{post:(_url,body)=>{payload=body;return Promise.resolve({data:{success:false}})}},$message:{error:()=>{}}}
    await view.default.methods.clickSubmit.call(ctx,0)
    assert.equal(payload.original_amount,'10.006')
    assert.equal(payload.payment,'10.01')
})

test('remounting a fiscal draft keeps its manual rate and recalculates applied settlement',()=>{
    const payment={payment:'4002.40',currency_type_id:'USD',original_amount:'10.006',exchange_rate:'400'}
    const ctx={payment,methodCurrency:'USD',documentCurrency:'VES',documentRate:'500',error:'',lastApplied:null,
        $set:(obj,key,value)=>{obj[key]=value},evaluate:()=>{},clearServerError:()=>{}}
    ctx.proposeReceived=()=>component.methods.proposeReceived.call(ctx)
    ctx.convertPrincipal=()=>component.methods.convertPrincipal.call(ctx)
    component.methods.syncMethodCurrency.call(ctx)
    assert.equal(payment.exchange_rate,'400')
    assert.equal(payment.original_amount,'10.006')
    assert.equal(payment.payment,'4004.00')
})
