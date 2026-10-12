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
vm.runInNewContext(babel.transformSync(parsed.script.content,{configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']}).code,{exports:exportsObject, require:() => mathExports});
const component = exportsObject.default;
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
    assert.equal(payment.payment,200.62); assert.equal(payment.exchange_rate,'40.12345678');
    assert.equal(Object.hasOwn(payment,'tax_amount'),false);
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
