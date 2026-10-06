const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const compiler = require('vue-template-compiler');
const babel = require('@babel/core');
const parsed = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/partials/igtf_fields.vue','utf8'));
const exportsObject = {};
vm.runInNewContext(babel.transformSync(parsed.script.content,{configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']}).code,{exports:exportsObject});
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
