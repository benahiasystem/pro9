const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs'), vm = require('node:vm'), path = require('node:path');
const compiler = require('vue-template-compiler'), babel = require('@babel/core');
const moment = require('moment'), _ = require('lodash');
const shared = require('./helpers/load-module.cjs')(path.resolve('resources/js/mixins/document-email.js'));
function load(file) {
 const parsed = compiler.parseComponent(fs.readFileSync(file,'utf8')), exports = {};
 vm.runInNewContext(babel.transformSync(parsed.script.content,{configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']}).code,
 {exports, moment, _, require: name => name === '@mixins/document-email' ? shared : name.includes('vuex') ? {mapActions:()=>({}),mapState:()=>({})} : {}});
 assert.deepEqual(compiler.compile(parsed.template.content).errors, []);
 return exports.default;
}
const dialog = load('resources/js/views/tenant/pos/partials/options.vue');
function context(post, delivery = {provider:'hka',can_send:true,status:null}) {
 const messages=[], ctx={...dialog.data(),resource:'documents',form:{id:44,customer_email:'test@example.test',email_delivery:delivery},
 $http:{post},$set:(obj,k,v)=>obj[k]=v,$message:Object.fromEntries(['success','warning','error','info'].map(k=>[k,m=>messages.push(m)])),messages};
 for(const [name, fn] of Object.entries(dialog.methods)) ctx[name]=fn.bind(ctx);
 ctx.newEmailRequestId=()=> 'request-uuid'; ctx.emailRequestId='request-uuid';return ctx;
}
test('both POS payment panels expose the same dialog and shared HKA behavior',()=>{
 for(const file of ['payment.vue','fast_payment_garage.vue','fast_payment.vue']) {
  const text=fs.readFileSync('resources/js/views/tenant/pos/partials/'+file,'utf8');
  assert.match(text,/import OptionsForm from '.\/options.vue'/);assert.match(text,/:showDialog.sync="showDialogOptions"/);
 }
 assert.equal(dialog.methods.clickSendEmail,shared.documentEmail.methods.clickSendEmail);
});
test('POS sends one request with UUID, blocks double clicks and offers explicit resend',async()=>{
 let resolve,calls=0;const ctx=context((url,body)=>{calls++;assert.equal(url,'/documents/email');assert.equal(body.id,44);assert.equal(body.request_id,'request-uuid');return new Promise(r=>resolve=r)});
 const first=ctx.clickSendEmail();await ctx.clickSendEmail();assert.equal(calls,1);
 resolve({data:{success:true,message:'Solicitud de correo aceptada por HKA',email_delivery:{provider:'hka',status:'accepted',can_send:true}}});await first;
 assert.equal(dialog.computed.emailSendLabel.call(ctx),'Enviar de nuevo');assert.equal(ctx.messages[0],'Solicitud de correo aceptada por HKA');
 ctx.$http.post=async(url,body)=>{assert.equal(body.resend,true);return{data:{success:true,email_delivery:{status:'accepted'}}}};await ctx.clickSendEmail();
});
test('unconfirmed, pending and uncertain mail never sends; failures preserve the invoice and request',async()=>{
 for(const status of [null,'pending','uncertain']){const ctx=context(()=>assert.fail('HTTP blocked'),{provider:'hka',can_send:false,status});await ctx.clickSendEmail();}
 const ctx=context(async()=>{throw Error('network')});await ctx.clickSendEmail();assert.equal(ctx.form.id,44);assert.equal(ctx.emailRequestId,'request-uuid');assert.match(ctx.messages[0],/Factura guardada/);assert.equal(ctx.emailBusy,false);
 ctx.$http.post=async()=>{throw{response:{status:422,data:{errors:{'recipients.0':['Correo inválido']}}}}};await ctx.clickSendEmail();assert.equal(ctx.errors.customer_email[0],'Correo inválido');
});
test('POS loads persisted pending attempts and queries tracking without changing fiscal state',async()=>{
 const ctx=context(async url=>{assert.equal(url,'/documents/44/query-hka-email');return{data:{message:'Por verificar',email_delivery:{status:'uncertain',can_query:true}}}});
 ctx.setEmailDeliveryRecord({id:44,email_delivery:{status:'pending',request_id:'persisted-attempt'},fiscal_emission:{status:'confirmed'}});
 assert.equal(ctx.emailRequestId,'persisted-attempt');await ctx.queryHkaEmail();assert.equal(ctx.form.fiscal_emission.status,'confirmed');assert.equal(ctx.form.email_delivery.status,'uncertain');
});
test('failure to create an attempt UUID releases the send button and explains the failure',async()=>{
 const ctx=context(()=>assert.fail('No HTTP without UUID'));
 ctx.emailRequestId=null;ctx.newEmailRequestId=()=>{throw Error('crypto unavailable')};
 await ctx.clickSendEmail();assert.equal(ctx.emailBusy,false);assert.match(ctx.messages[0],/Factura guardada/);
});
test('request validation errors cannot silently disappear in the POS dialog',async()=>{
 const ctx=context(async()=>{throw{response:{status:422,data:{message:'Identificador de intento requerido',errors:{request_id:['Requerido']}}}}});
 await ctx.clickSendEmail();assert.equal(ctx.messages[0],'Identificador de intento requerido');assert.equal(ctx.emailBusy,false);
});
test('flat Pro9 AJAX validation maps the recipient error to the visible email field',async()=>{
 const ctx=context(async()=>{throw{response:{status:422,data:{'recipients.0':['Ingrese una dirección de correo válida.']}}}});
 await ctx.clickSendEmail();assert.deepEqual(ctx.errors.customer_email,['Ingrese una dirección de correo válida.']);assert.equal(ctx.emailBusy,false);
});
test('garage initial amount waits for rendering and tolerates an absent input',async()=>{
 const payment=load('resources/js/views/tenant/pos/partials/fast_payment_garage.vue');
 const calls=[],ctx={form:{total:400},$refs:{},$nextTick:async()=>calls.push('rendered')};
 await payment.methods.setInitialAmount.call(ctx);assert.equal(ctx.enter_amount,400);
 ctx.$refs.enter_amount={$el:{querySelector:()=>({focus:()=>calls.push('focus'),select:()=>calls.push('select')})}};
 await payment.methods.setInitialAmount.call(ctx);assert.deepEqual(calls,['rendered','rendered','focus','select']);
});
for(const file of ['payment.vue','fast_payment_garage.vue']) {
 test(`${file} tolerates missing optional configuration after the sale is saved`,async()=>{
  const payment=load('resources/js/views/tenant/pos/partials/'+file);
  const ctx={configuration:{},$http:{post:()=>assert.fail('No automatic email')}};
  await payment.methods.autoSendPdfMail.call(ctx,{fiscal_emission:{status:'confirmed'}});
  await payment.methods.autoSendPdfMail.call(ctx,{fiscal_emission:{status:null}});
 });
}
for(const file of ['payment.vue','fast_payment_garage.vue']) {
 const payment=load('resources/js/views/tenant/pos/partials/'+file);
 test(`${file} generates a paid invoice and opens options even when HKA is uncertain`,async()=>{
  const requests=[],ctx={form:{document_type_id:'01',series_id:2,customer_id:10,date_of_issue:moment().format('YYYY-MM-DD'),items:[{quantity:1,unit_type_id:'UND'}],payments:[{payment:100}],total:100},
   config:{auto_send_pdf_email:true},configuration:{},businessTurns:{active:false},rowsItems:1,form_cash_document:{},hidePdfViewDocuments:false,
   $message:{success(){},warning:m=>assert.fail(m),error:m=>assert.fail(m)},$eventHub:{$emit(){}},
   $http:{post:async(url,form)=>{requests.push({url,form:JSON.parse(JSON.stringify(form))});return{data:{success:true,data:{id:44,fiscal_emission:{status:'uncertain'}}}}}}};
  for(const[name,fn]of Object.entries(payment.methods))ctx[name]=fn.bind(ctx);
  for(const name of ['asignPlateNumberToItems','saveCashDocument','cleanLocalStoragePayment','initDataComponent'])ctx[name]=()=>{};
  ctx.validateRestrictSaleItemsCpe=ctx.validateRestrictSellerDiscount=()=>({success:true});
  await ctx.clickPayment();assert.equal(requests.length,1);assert.equal(requests[0].url,'/documents');assert.equal(requests[0].form.series_id,2);assert.equal(ctx.documentNewId,44);assert.equal(ctx.showDialogOptions,true);assert.equal(ctx.loading_submit,false);
 });
 test(`${file} handles loss of the creation response without another sale`,async()=>{
  const messages=[],ctx={form:{document_type_id:'01',series_id:2,customer_id:10,date_of_issue:moment().format('YYYY-MM-DD'),items:[{quantity:1,unit_type_id:'UND'}],payments:[{payment:100}],total:100},config:{},configuration:{},businessTurns:{active:false},rowsItems:1,
   $message:{error:m=>messages.push(m),warning:m=>messages.push(m)},$http:{post:async()=>{throw Error('network')}},$eventHub:{$emit(){}}};
  for(const[name,fn]of Object.entries(payment.methods))ctx[name]=fn.bind(ctx);
  ctx.validateRestrictSaleItemsCpe=ctx.validateRestrictSellerDiscount=()=>({success:true});ctx.asignPlateNumberToItems=()=>{};
  await ctx.clickPayment();assert.match(messages[0],/Consulte el listado/);assert.equal(ctx.loading_submit,false);
 });
}
