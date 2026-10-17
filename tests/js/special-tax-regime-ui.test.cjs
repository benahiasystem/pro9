const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const compiler=require('vue-template-compiler'),babel=require('@babel/core');
const loadModule=require('./helpers/load-module.cjs');
function load(file) {
 const parsed=compiler.parseComponent(fs.readFileSync(file,'utf8'));
 assert.deepEqual(compiler.compile(parsed.template.content).errors,[]);
 const exports={};
 vm.runInNewContext(babel.transformSync(parsed.script.content,{configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']}).code,{
  exports,require:name=>name.includes('vuex')?{mapState:()=>({}),mapActions:()=>({})}
   :require('./support/exact-runtime.cjs')(name) || (name==='moment'?require('moment'):name==='lodash'?require('lodash'):{}),window:{},localStorage:{getItem:()=>null}
 });
 return {component:exports.default,template:parsed.template.content};
}
const invoice=load('resources/js/views/tenant/documents/invoice_generate.vue');

function context(extra={}) {
 const messages=[],ctx={company:{fiscal_emission_mode:'digital',fiscal_environment:'demo'},
  form:{document_type_id:'01',operation_type_id:'0101',payment_condition_id:'01',fiscal_data:{special_tax_regime_id:1},items:[]},
  specialTaxRegimeAvailable:true,thirdPartyMode:false,isExportInvoice:false,isUpdateDocument:false,
  $message:{error:m=>messages.push(m)},messages,...extra};return ctx;
}
test('regime availability requires a domestic invoice and digital DEMO',()=>{
 const ctx=context();assert.equal(invoice.component.computed.specialTaxRegimeAvailable.call(ctx),true);
 for(const change of [{document_type_id:'08'},{operation_type_id:'0201'},{is_itinerant:true}]) {
  assert.equal(invoice.component.computed.specialTaxRegimeAvailable.call({...ctx,form:{...ctx.form,...change}}),false);
 }
 ctx.company.fiscal_environment='production';assert.equal(invoice.component.computed.specialTaxRegimeAvailable.call(ctx),false);
});
test('selection keeps product IVA and money unchanged; credit refuses the regime',()=>{
 const ctx=context();ctx.form.items=[{percentage_igv:16,affectation_igv_type_id:'10',unit_price:116}];
 const before=JSON.stringify(ctx.form);invoice.component.methods.changeSpecialTaxRegime.call(ctx,1);
 assert.equal(JSON.stringify(ctx.form),before);
 ctx.form.payment_condition_id='02';invoice.component.methods.changeSpecialTaxRegime.call(ctx,1);
 assert.equal(ctx.form.fiscal_data.special_tax_regime_id,null);assert.equal(ctx.messages.length,1);
});
test('export and itinerant switching require removing the selected regime first',()=>{
 const ctx=context();for(const value of ['0201','0101_itinerant'])invoice.component.computed.operation_type_id_view.set.call(ctx,value);
 assert.equal(ctx.form.operation_type_id,'0101');assert.equal(ctx.messages.length,2);
 ctx.form.fiscal_data.special_tax_regime_id=null;
 invoice.component.computed.operation_type_id_view.set.call(ctx,'0201');assert.equal(ctx.form.operation_type_id,'0201');
});
test('excluded or credit submissions stop before any commercial request',async()=>{
 for(const extra of [{specialTaxRegimeAvailable:false},{form:{fiscal_data:{special_tax_regime_id:1},payment_condition_id:'02'}}]) {
  const ctx=context(extra);await invoice.component.methods.submit.call(ctx);assert.equal(ctx.messages.length,1);
 }
});
test('editable regime submits only its ID and preserves the existing payments',()=>{
 const ctx=context({isUpdateDocument:true});ctx.form.payments=[{id:9,payment:116}];
 ctx.form.fiscal_data.special_tax_regime_id=2;
 const posted=invoice.component.methods.documentSubmission.call(ctx);
 assert.equal(posted.fiscal_data.special_tax_regime_id,2);assert.equal(posted.payments.length,0);
 assert.equal(ctx.form.payments[0].id,9);
 assert.match(invoice.template,/Sin régimen especial/);assert.match(invoice.template,/Régimen registrado:/);
});
test('regime switch reveals the field without selecting a regime and clears it when disabled',()=>{
 const ctx=context({specialTaxRegimeMode:true});ctx.form.fiscal_data.special_tax_regime_id=null;
 invoice.component.methods.changeSpecialTaxRegimeMode.call(ctx,true);
 assert.equal(ctx.specialTaxRegimeMode,true);assert.equal(ctx.form.fiscal_data.special_tax_regime_id,null);
 ctx.form.fiscal_data.special_tax_regime_id=2;ctx.specialTaxRegimeMode=false;
 invoice.component.methods.changeSpecialTaxRegimeMode.call(ctx,false);
 assert.equal(ctx.form.fiscal_data.special_tax_regime_id,null);
 for(const extra of [{specialTaxRegimeAvailable:false},{form:{payment_condition_id:'02',fiscal_data:{special_tax_regime_id:null}}}]) {
  const invalid=context({specialTaxRegimeMode:true,...extra});
  invoice.component.methods.changeSpecialTaxRegimeMode.call(invalid,true);
  assert.equal(invalid.specialTaxRegimeMode,false);assert.equal(invalid.messages.length,1);
 }
});
test('fiscal switches live in additional information while enabled fields remain in the header',()=>{
 const panel=invoice.template.slice(invoice.template.indexOf('class="additional-information px-4"'));
 const header=invoice.template.slice(0,invoice.template.indexOf('</header>'));
 for(const model of ['specialTaxRegimeMode','thirdPartyMode']) {
  assert.match(panel,new RegExp('<el-switch v-model="'+model+'"'));
  assert.doesNotMatch(header,new RegExp('<el-(switch|checkbox) v-model="'+model+'"'));
 }
 assert.match(header,/v-if="specialTaxRegimeMode &&/);
 assert.match(header,/v-if="thirdPartyMode &&/);
});
test('loading existing fiscal options restores both switches and retains historical options',()=>{
 const ctx=context({specialTaxRegimes:[]});
 invoice.component.methods.restoreFiscalOptions.call(ctx,{
  special_tax_regime_id:2,special_tax_regime_description:'Histórico',
  is_third_party_invoice:true,third_party_id:5,third_party:{name:'Titular',rif:'J-12345678-9',activity:'Comercio'}
 });
 assert.equal(ctx.specialTaxRegimeMode,true);assert.equal(ctx.thirdPartyMode,true);
 assert.equal(ctx.form.fiscal_data.special_tax_regime_id,2);assert.equal(ctx.form.third_party_id,5);
 assert.equal(ctx.specialTaxRegimes[0].inactive,true);assert.equal(ctx.thirdParties[0].name,'Titular');
 invoice.component.methods.restoreFiscalOptions.call(ctx,{});
 assert.equal(ctx.specialTaxRegimeMode,false);assert.equal(ctx.thirdPartyMode,false);
 assert.equal(ctx.form.third_party_id,null);
});
