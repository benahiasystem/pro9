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
   :name==='moment'?require('moment'):name==='lodash'?require('lodash'):{},window:{},localStorage:{getItem:()=>null}
 });
 return {component:exports.default,template:parsed.template.content};
}
const invoice=load('resources/js/views/tenant/documents/invoice_generate.vue');
const person=load('resources/js/views/tenant/persons/form.vue');
function context(extra={}) {
 const messages=[],ctx={thirdPartyMode:true,originalThirdPartyInvoice:false,isUpdateDocument:false,thirdPartyAvailable:true,
  form:{third_party_id:3,payment_condition_id:'01',operation_type_id:'0101',document_type_id:'01'},
  thirdParties:[],thirdPartySavedOption:null,thirdPartySearchSequence:0,loadingThirdParties:false,
  $message:{error:m=>messages.push(m)},messages,...extra};
 for(const name of ['changeThirdPartyMode','loadThirdParties'])ctx[name]=invoice.component.methods[name].bind(ctx);
 return ctx;
}
test('client classification contains the third-party checkbox and complementary fiscal fields',()=>{
 const classification=person.template.slice(person.template.indexOf('Clasificación'),person.template.indexOf('form.perception_agent &&'));
 assert.match(classification,/v-model="form.is_third_party">Es tercero/);
 assert.match(classification,/type === 'customers' && form.is_third_party/);
 assert.match(classification,/v-model="form.third_party_rif"/);
 assert.match(classification,/v-model="form.third_party_activity"/);
});
test('third-party availability follows company, invoice and domestic operation',()=>{
 const ctx=context({company:{fiscal_emission_mode:'digital',fiscal_environment:'demo'}});
 assert.equal(invoice.component.computed.thirdPartyAvailable.call(ctx),true);
 for(const change of [{document_type_id:'08'},{operation_type_id:'0201'},{is_itinerant:true}]) {
  const changed={...ctx,form:{...ctx.form,...change}};
  assert.equal(invoice.component.computed.thirdPartyAvailable.call(changed),false);
 }
 ctx.company.fiscal_environment='production';assert.equal(invoice.component.computed.thirdPartyAvailable.call(ctx),false);
});
test('turning the mode off clears its titular; credit or existing-category changes are refused',()=>{
 for(const extra of [{isUpdateDocument:true,originalThirdPartyInvoice:true},{form:{payment_condition_id:'02'}}]) {
  const ctx=context(extra);ctx.changeThirdPartyMode(true);assert.equal(ctx.thirdPartyMode,ctx.originalThirdPartyInvoice);assert.equal(ctx.messages.length,1);
 }
 const ctx=context();ctx.thirdPartyMode=false;ctx.changeThirdPartyMode(false);assert.equal(ctx.form.third_party_id,null);
});
test('late searches cannot replace newer results or reopen the third-party mode',async()=>{
 const pending=[],ctx=context({$http:{get:(url,options)=>{assert.equal(url,'/documents/search/third-parties');return new Promise(resolve=>pending.push({resolve,options}));}}});
 const old=ctx.loadThirdParties('old'),fresh=ctx.loadThirdParties('new');
 pending[1].resolve({data:{customers:[{id:4,name:'New titular'}]}});await fresh;
 pending[0].resolve({data:{customers:[{id:3,name:'Old titular'}]}});await old;assert.equal(ctx.thirdParties[0].id,4);
 const closing=ctx.loadThirdParties('closing');ctx.thirdPartyMode=false;ctx.changeThirdPartyMode(false);
 pending[2].resolve({data:{customers:[{id:5}]}});await closing;assert.equal(ctx.form.third_party_id,null);assert.equal(ctx.thirdParties[0].id,4);
});
test('an editor displays its frozen titular even when not offered for new selection',async()=>{
 const saved={id:3,name:'Saved name',third_party_rif:'V123456789'};
 const ctx=context({thirdPartySavedOption:saved,$http:{get:async()=>({data:{customers:[]}})}});
 await ctx.loadThirdParties('');assert.equal(ctx.thirdParties[0],saved);
});
test('a third-party draft cannot switch to export or itinerant; submit rejects an absent titular',async()=>{
 const ctx=context({isExportInvoice:false});
 invoice.component.computed.operation_type_id_view.set.call(ctx,'0201');
 invoice.component.computed.operation_type_id_view.set.call(ctx,'0101_itinerant');
 assert.equal(ctx.form.operation_type_id,'0101');assert.equal(ctx.messages.length,2);
 ctx.form.third_party_id=null;ctx.editBlocked=false;
 await invoice.component.methods.submit.call(ctx);assert.equal(ctx.messages.length,3);
});
test('saving a titular uses its own event and cannot select or reset the buyer',()=>{
 const hub=[],events=[],ctx={selection_target:'third_party',external:true,form:{third_party_rif:'V234567891'},
  $eventHub:{$emit:(...args)=>hub.push(args)},$emit:(...args)=>events.push(args),initForm:()=>{}};
 ctx.close=person.component.methods.close.bind(ctx);
 person.component.methods.notifySaved.call(ctx,5);
 assert.deepEqual(hub,[]);
 assert.equal(events[0][0],'update:showDialog');assert.equal(events[0][1],false);
 assert.equal(events[1][0],'saved');assert.equal(events[1][1].id,5);assert.equal(events[1][1].rif,'V234567891');
 ctx.selection_target=undefined;events.length=0;
 person.component.methods.notifySaved.call(ctx,5);
 assert.deepEqual(hub,[['reloadDataPersons',5],['initInputPerson']]);
});
test('a saved titular is selected using its fiscal RIF while the buyer stays unchanged',async()=>{
 const ctx=context({form:{customer_id:42,third_party_id:3},$http:{get:async(url,{params})=>{
  assert.equal(params.input,'V234567891');return {data:{customers:[{id:5,name:'New titular'}]}};
 }}});
 await invoice.component.methods.thirdPartySaved.call(ctx,{id:5,rif:'V234567891'});
 assert.equal(ctx.form.third_party_id,5);assert.equal(ctx.form.customer_id,42);
 ctx.thirdPartySavedOption={id:5,name:'Historical titular'};
 ctx.$http.get=async()=>({data:{customers:[]}});
 await invoice.component.methods.thirdPartySaved.call(ctx,{id:5,rif:'V234567891'});
 assert.equal(ctx.form.third_party_id,null);assert.equal(ctx.form.customer_id,42);
});
