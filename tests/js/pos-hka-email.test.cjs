const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs'), vm = require('node:vm'), path = require('node:path');
const compiler = require('vue-template-compiler'), babel = require('@babel/core');
const moment = require('moment'), _ = require('lodash');
const shared = require('./helpers/load-module.cjs')(path.resolve('resources/js/mixins/document-email.js'));
function load(file, globals = {}) {
 const parsed = compiler.parseComponent(fs.readFileSync(file,'utf8')), exports = {};
 vm.runInNewContext(babel.transformSync(parsed.script.content,{configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']}).code,
 {exports, moment, _, require: name => require('./support/exact-runtime.cjs')(name) || (name === '@helpers/payment-classification' ? require('./helpers/load-module.cjs')(path.resolve('resources/js/helpers/payment-classification.js')) : name === '@mixins/document-pdf' ? require('./helpers/load-module.cjs')(require('node:path').resolve('resources/js/mixins/document-pdf.js')) : name === '@mixins/document-email' ? shared : name.includes('vuex') ? {mapActions:()=>({}),mapState:()=>({})} : {}), ...globals});
 assert.deepEqual(compiler.compile(parsed.template.content).errors, []);
 const component=exports.default;
 // Checkout tests isolate creation/email; the calculation mixin has its own integration tests.
 component.methods={prepareCalculationSubmission:form=>form,handleCalculationChanged:()=>false,...component.methods};
 return component;
}
const dialog = load('resources/js/views/tenant/pos/partials/options.vue');
test('POS enables and validates payment using the exact invoice total', async () => {
 const pos = load('resources/js/views/tenant/pos/index.vue');
 const payment = load('resources/js/views/tenant/pos/partials/payment.vue');
 const runtime = require('./support/exact-runtime.cjs');
 const {exactSnapshot} = runtime('exchange-rate-math');
 const {documentTotals} = runtime('exact-document');
 const amount = {n: '1', d: '10000000000000'};
 const line = {affectation_igv_type_id: '20', calculation_snapshot: exactSnapshot({
  total_value: amount, total_base_igv: amount, total_igv: '0', total_other_taxes: '0', total_discount: '0', total_charge: '0',
 })};
 const totals = documentTotals([line]);
 assert.equal(totals.total, '0');
 for (const [documentType, values, allowed] of [
  ['01', totals, true],
  ['01', {total: '0', calculation_snapshot: exactSnapshot({total: '0'})}, false],
  ['01', {total: '0', calculation_snapshot: exactSnapshot({total: {n: '-1', d: amount.d}})}, false],
  ['80', totals, false],
 ]) {
  const errors = [];
  let checked = false;
  const form = {...values, document_type_id: documentType, items: [line], series_id: 1, customer_id: 2,
   payments: [], payment_condition_id: '02', date_of_issue: moment().format('YYYY-MM-DD')};
  const ctx = {
   form, refreshScaleTotals: () => true, validateRestrictSaleItemsCpe: () => ({success: true}),
   validateRestrictSellerDiscount: () => ({success: true}), asignPlateNumberToItems: async () => {},
   ensureFiscalPaymentsReady: async () => { checked = true; return false; },
   $http: {post: () => assert.fail('La prueba no emite un documento')},
   $message: {error: message => errors.push(message), warning: message => errors.push(message)},
  };
  await payment.methods.clickPayment.call(ctx);
  assert.equal(pos.computed.canPay.call(ctx), allowed, `Botón de cobro para ${documentType}`);
  assert.equal(checked, allowed, `Validación de cobro para ${documentType}`);
  assert.deepEqual(errors, allowed ? [] : ['El total debe ser mayor a 0']);
 }
});
test('fast checkout validates the current cart before trying to submit a receipt', async () => {
 const parent = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/pos/fast.vue', 'utf8'));
 const paymentTag = parent.template.content.match(/<fast-payment\b[\s\S]*?>/)[0];
 const boundCount = paymentTag.match(/:rows-items="([^"]+)"/)?.[1];
 const checkout = load('resources/js/views/tenant/pos/partials/fast_payment.vue');

 for (const items of [[], [{quantity: '1', unit_type_id: 'UND'}]]) {
  const warnings = [];
  let checkedPayments = false;
  const form = {items, document_type_id: '01', series_id: 2, payments: [], date_of_issue: moment().format('YYYY-MM-DD')};
  const ctx = {form, rowsItems: boundCount && vm.runInNewContext(boundCount, {form}), businessTurns: {active: false},
   asignPlateNumberToItems: async () => {},
   ensureFiscalPaymentsReady: async () => { checkedPayments = true; return false; },
   $message: {warning: message => warnings.push(message), error: message => warnings.push(message)},
   $http: {post: () => assert.fail('La prueba nunca debe emitir un documento')},
  };
  await checkout.methods.clickPayment.call(ctx);
  assert.deepEqual(warnings, items.length ? [] : ['Debe agregar productos']);
  assert.equal(checkedPayments, items.length > 0);
 }
});
for (const file of ['index.vue', 'partials/fast_payment.vue', 'partials/fast_payment_garage.vue']) {
 test(`${file} validates whole invoice quantities without losing their exact fraction`, async () => {
  const component = load('resources/js/views/tenant/pos/' + file);
  const fraction = {values: {quantity: {n: '3000000000001', d: '3000000000000'}}};
  const cases = [
   [{quantity: '9007199254740993.1', unit_type_id: 'UND'}, 'decimales'],
   [{quantity: '1', unit_type_id: 'SERV', calculation_snapshot: fraction}, 'decimales'],
   [{quantity: '1', unit_type_id: 'UND', quantity_input: {amount: '3000000000001', unit_price: '3000000000000'}}, 'decimales'],
   [{quantity: '9007199254740993', unit_type_id: 'UND'}, null],
   [{quantity: '1.5', unit_type_id: 'KGM'}, null],
   [{quantity: '2', unit_type_id: 'UND', calculation_snapshot: fraction}, null],
   [{quantity: '0', unit_type_id: 'UND'}, 'mayor a 0'],
   [{quantity: '1', unit_type_id: 'UND', calculation_snapshot: {values: {quantity: {n: '1', d: '0'}}}}, 'incorrecta'],
   ...(file === 'partials/fast_payment.vue' ? [] : [[{quantity: '1.5', unit_type_id: 'UND'}, 'decimales', '80']]),
  ];

  for (const [row, message, type = '01'] of cases) {
   const errors = [];
   let validated = false;
   const context = {
    form: {items: [{aux_quantity: '1', total: '1', ...row}], total: '1', exchange_rate_sale: '1',
     customer_id: 1, series_id: 1, date_of_issue: moment().format('YYYY-MM-DD'), document_type_id: type, currency_type_id: 'VES'},
    establishment: {id: 1}, rowsItems: 1, businessTurns: {}, configuration: {},
    isPaymentCurrencyBlocked: () => false,
    sleep: async () => { validated = true; }, asignPlateNumberToItems: async () => {},
    ensureFiscalPaymentsReady: async () => { validated = true; return false; },
    $message: {error: value => errors.push(value), warning: value => errors.push(value)},
    $http: {post: () => assert.fail('La validación de esta prueba no debe emitir una factura')},
   };
   await component.methods.clickPayment.call(context);
   assert.equal(validated, message === null, JSON.stringify(row));
   if (message === null) assert.deepEqual(errors, []);
   else assert.match(errors[0], new RegExp(message));
  }
 });
}
test('fast checkout applies a directly entered amount to a new cash receipt only when its method and destination exist',async()=>{
 const component=load('resources/js/views/tenant/pos/partials/fast_payment.vue'), warnings=[];
 const ctx={form:{currency_type_id:'VES',payments:[]},enter_amount:'0.30',singleAmountLocked:false,
  payment_method_types:[{id:'14',is_active:true,collection_kind:'cash',currency_type_id:'VES'}],
  payment_destinations:[{id:'cash'}],$message:{warning:m=>warnings.push(m)},inputAmount:()=>{}};
 await component.methods.enterAmount.call(ctx);
 assert.equal(ctx.form.payments.length,1);assert.equal(ctx.form.payments[0].payment,'0.3');
 assert.equal(ctx.form.payments[0].payment_method_type_id,'14');assert.equal(ctx.form.payments[0].payment_destination_id,'cash');
 ctx.enter_amount='0.10';await component.methods.enterAmount.call(ctx);assert.equal(ctx.form.payments[0].payment,'0.1');
 ctx.form.payments=[];ctx.payment_destinations=[];await component.methods.enterAmount.call(ctx);
 assert.equal(ctx.form.payments.length,0);assert.match(warnings[0],/Agregar pagos/);
});
test('fast checkout preserves an authorized series and selects a series for a replaced form',()=>{
 const component=load('resources/js/views/tenant/pos/partials/fast_payment.vue'),warnings=[];
 const ctx={all_series:[{id:1,document_type_id:'01',number:'FF01'},{id:2,document_type_id:'01',number:''},
  {id:3,document_type_id:'80',number:'NV01'}],form:{document_type_id:'01',currency_type_id:'VES',series_id:'2'},$message:{warning:m=>warnings.push(m)}};
 ctx.filterSeries=component.methods.filterSeries.bind(ctx);
 ctx.filterSeries();assert.equal(ctx.form.series_id,2);
 ctx.form={document_type_id:'01',series_id:null};component.watch.form.call(ctx);assert.equal(ctx.form.series_id,1);
 ctx.form.document_type_id='80';component.watch['form.document_type_id'].call(ctx);assert.equal(ctx.form.series_id,3);
 ctx.form={document_type_id:'08',series_id:null};component.watch.form.call(ctx);assert.equal(ctx.form.series_id,null);assert.equal(warnings.length,0);
 ctx.filterSeries();assert.equal(warnings.length,1);
 ctx.all_series=[{id:'9007199254740992',document_type_id:'01'},{id:'9007199254740993',document_type_id:'01'}];
 ctx.form={document_type_id:'01',currency_type_id:'VES',series_id:'9007199254740993'};
 ctx.filterSeries();assert.equal(ctx.form.series_id,'9007199254740993');
});

for (const file of ['payment.vue', 'fast_payment.vue']) {
    test(`${file} shows the selector only when the active document type has a named series`, () => {
        const panelPath = `resources/js/views/tenant/pos/partials/${file}`;
        const payment = load(panelPath);
        const template = compiler.parseComponent(fs.readFileSync(panelPath, 'utf8')).template.content;
        assert.match(template, /<div v-if="hasNamedSeries"[^>]*>\s*<el-select v-model="form.series_id"/);

        const ctx = {
            form: {document_type_id: '01', series_id: null},
            $message: {warning() {}},
        };
        for (const [type, otherType, code] of [['01', '80', 'FF01'], ['80', '01', 'NV01']]) {
            const blank = {id: 1, document_type_id: type, number: ''};
            const named = {id: 2, document_type_id: type, number: code};
            const other = {id: 3, document_type_id: otherType, number: 'OTHER'};
            ctx.form.document_type_id = type;

            for (const [options, expectedId, visible] of [
                [[blank, named], 1, true],
                [[blank], 1, false],
                [[named], 2, true],
                [[], null, false],
            ]) {
                ctx.all_series = [other, ...options];
                payment.methods.filterSeries.call(ctx);
                assert.equal(ctx.form.series_id, expectedId);
                assert.equal(payment.computed.hasNamedSeries.call(ctx), visible);
            }
        }
    });
}

test('standard POS only applies a default series authorized for the active document type', () => {
    const payment = load('resources/js/views/tenant/pos/partials/payment.vue');
    const ctx = {
        authUser: {document_id: '01', serie: 3},
        all_series: [
            {id: 1, document_type_id: '01', number: ''},
            {id: 3, document_type_id: '80', number: 'NV01'},
        ],
        form: {},
        $message: {warning: message => assert.fail(message)},
    };
    for (const name of ['filterSeries', 'selectDocumentType', 'refreshScaleTotals']) {
        ctx[name] = payment.methods[name].bind(ctx);
    }
    payment.methods.setDefaultDocumentType.call(ctx);
    assert.equal(ctx.form.series_id, 1);

    ctx.authUser.document_id = '80';
    ctx.all_series.push({id: 4, document_type_id: '80', number: ''});
    ctx.all_series.reverse();
    payment.methods.setDefaultDocumentType.call(ctx);
    assert.equal(ctx.form.series_id, 3);
});

test('fast payment restores its own amounts without reading Garage values and formats totals to cents',async()=>{
 const values={amount:'"0.30"',amount_garage:'"1.00"',enter_amount:'"0.30"',enter_amount_garage:'"1.00"',difference:'"0"'};
 const component=load('resources/js/views/tenant/pos/partials/fast_payment.vue',{localStorage:{getItem:key=>values[key] ?? null}});
 const ctx={getLocalStoragePayment:component.methods.getLocalStoragePayment};
 await component.methods.initLStoPayment.call(ctx);
 assert.equal(ctx.amount,'0.30');assert.equal(ctx.enter_amount,'0.30');assert.equal(ctx.difference,'0');
 assert.equal(component.methods.money('86.206896551724'),'86.21');
 assert.equal(component.methods.money('13.793103448276'),'13.79');
});
test('fast checkout clears the previous receipt after a successful sale',async()=>{
 const component=load('resources/js/views/tenant/pos/partials/fast_payment.vue');
 const ctx={...component.data(),all_series:[{id:1,document_type_id:'01',number:''}],
  form:{document_type_id:'01',series_id:null,date_of_issue:moment().format('YYYY-MM-DD'),items:[],payments:[{payment:'100'}]},
  amount:'100',enter_amount:'100',difference:'0',payments:[{payment:'100'}],businessTurns:{active:false},config:{},rowsItems:1,
  prepareCalculationSubmission:form=>form,handleCalculationChanged:()=>false,
  asignPlateNumberToItems:async()=>{},ensureFiscalPaymentsReady:async()=>true,saveCashDocument:()=>{},cleanLocalStoragePayment:()=>{},
  $http:{post:async(url,form)=>{
   assert.equal(url,'/documents');assert.equal(form.series_id,1);
   return {data:{success:true,data:{id:1,response:{}}}};
  }},$eventHub:{$emit:()=>{}},$message:{error:message=>assert.fail(message)}};
 component.methods.filterSeries.call(ctx);
 await component.methods.clickPayment.call(ctx);
 assert.equal(ctx.amount,0);assert.equal(ctx.enter_amount,0);assert.equal(ctx.difference,0);assert.equal(ctx.payments.length,0);
});
test('POS shows a rejected line error and keeps its draft available for correction',async()=>{
 const component=load('resources/js/views/tenant/pos/partials/payment.vue'),messages=[];
 const form={date_of_issue:moment().format('YYYY-MM-DD'),series_id:1,document_type_id:'01',total:'1',items:[{quantity:'3'}],payments:[{payment:'1'}]};
 const ctx={form,config:{},enabledPointSystem:false,singleAmountInvalid:false,locked_submit:false,loading_submit:false,
  validateRestrictSaleItemsCpe:()=>({success:true}),validateRestrictSellerDiscount:()=>({success:true}),
  prepareCalculationSubmission:form=>form,handleCalculationChanged:()=>false,
  asignPlateNumberToItems:async()=>{},ensureFiscalPaymentsReady:async()=>true,
  $http:{post:async()=>{throw {response:{status:422,data:{'items.price_input.amount':['Precio inválido']}}}}},
  $message:{error:m=>messages.push(m),warning:m=>messages.push(m)}};
 ctx.refreshScaleTotals=component.methods.refreshScaleTotals.bind(ctx);
 await component.methods.clickPayment.call(ctx);
 assert.deepEqual(messages,['Precio inválido']);assert.equal(ctx.form,form);assert.equal(form.items[0].quantity,'3');
 assert.equal(ctx.locked_submit,false);assert.equal(ctx.loading_submit,false);
});

test('POS preserves the invoice payment condition when there are no receipt rows', async () => {
    const component = load('resources/js/views/tenant/pos/partials/payment.vue')
    const summary = require('./helpers/load-module.cjs')(path.resolve('resources/js/mixins/payment-settlement-summary.js')).default
    for (const [condition, total, balance, allowed] of [['01','0.004','0.00',true], ['01','1','1.00',false], ['02','1','1.00',true]]) {
        const sent = [], warnings = []
        const form = {document_type_id: '01', currency_type_id: 'VES', payment_condition_id: condition,
            total, items: [{quantity: '1'}], payments: [], series_id: 1, date_of_issue: moment().format('YYYY-MM-DD')}
        const context = {form, localInvoiceSettlement: true, localQuoteReady: true, igtfQuote: {balance},
            get settlementPaymentBlocked() { return summary.computed.settlementPaymentBlocked.call(this) },
            refreshScaleTotals: () => true, validateRestrictSaleItemsCpe: () => ({success: true}),
            validateRestrictSellerDiscount: () => ({success: true}), asignPlateNumberToItems: async () => {},
            ensureFiscalPaymentsReady: async () => true, prepareCalculationSubmission: payload => payload,
            $message: {error() {}, warning: message => warnings.push(message)},
            $http: {post: async (url, payload) => { sent.push([url, payload.payment_condition_id]); return {data: {success: false}} }},
        }
        await component.methods.clickPayment.call(context)
        assert.equal(form.payment_condition_id, condition)
        assert.deepEqual(sent, allowed ? [['/documents', condition]] : [])
        assert.equal(warnings.length, allowed ? 0 : 1)
    }
})

test('Garage displays the exact IVA rate for VES invoices', () => {
    const component = load('resources/js/views/tenant/pos/partials/fast_payment_garage.vue')
    const form = {document_type_id: '01', currency_type_id: 'VES'}
    for (const [percentageIgv, expected] of [['0', '0'], ['0.1625', '16.25'], ['0.16', '16'], [null, '16']]) {
        assert.equal(component.computed.ivaPercentageLabel.call({form, percentageIgv}), expected)
    }
    form.document_type_id = '80'
    assert.equal(component.computed.ivaPercentageLabel.call({form, percentageIgv: '0.1625'}), '16')
})

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
test('flat SAB AJAX validation maps the recipient error to the visible email field',async()=>{
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
  const requests=[],ctx={all_series:[{id:2,document_type_id:'01',number:''}],
   form:{document_type_id:'01',series_id:null,customer_id:10,date_of_issue:moment().format('YYYY-MM-DD'),items:[{quantity:1,unit_type_id:'UND'}],payments:[{payment:100}],total:100},
   config:{auto_send_pdf_email:true},configuration:{},businessTurns:{active:false},rowsItems:1,form_cash_document:{},hidePdfViewDocuments:false,
   $message:{success(){},warning:m=>assert.fail(m),error:m=>assert.fail(m)},$eventHub:{$emit(){}},
   $http:{post:async(url,form)=>{requests.push({url,form:JSON.parse(JSON.stringify(form))});return{data:{success:true,data:{id:44,fiscal_emission:{status:'uncertain'}}}}}}};
  for(const[name,fn]of Object.entries(payment.methods))ctx[name]=fn.bind(ctx);
  ctx.filterSeries();
  ctx.form.iva_retention_prevision={enabled:true,amount:12};ctx.form.islr_retention_prevision={enabled:true,amount:3};ctx.form.received_retentions=[{tax_kind:'IVA'}];
  for(const name of ['asignPlateNumberToItems','saveCashDocument','cleanLocalStoragePayment','initDataComponent'])ctx[name]=()=>{};
  ctx.ensureFiscalPaymentsReady=async()=>true;ctx.validateRestrictSaleItemsCpe=ctx.validateRestrictSellerDiscount=()=>({success:true});
  await ctx.clickPayment();assert.equal(requests.length,1);assert.equal(requests[0].url,'/documents');assert.equal(requests[0].form.series_id,2);assert.equal(ctx.documentNewId,44);assert.equal(ctx.showDialogOptions,true);assert.equal(ctx.loading_submit,false);
  for(const key of ['iva_retention_prevision','islr_retention_prevision','received_retentions'])assert.equal(Object.hasOwn(requests[0].form,key),false);
 });
 test(`${file} handles loss of the creation response without another sale`,async()=>{
  const messages=[],ctx={form:{document_type_id:'01',series_id:2,customer_id:10,date_of_issue:moment().format('YYYY-MM-DD'),items:[{quantity:1,unit_type_id:'UND'}],payments:[{payment:100}],total:100},config:{},configuration:{},businessTurns:{active:false},rowsItems:1,
   $message:{error:m=>messages.push(m),warning:m=>messages.push(m)},$http:{post:async()=>{throw Error('network')}},$eventHub:{$emit(){}}};
  for(const[name,fn]of Object.entries(payment.methods))ctx[name]=fn.bind(ctx);
  ctx.ensureFiscalPaymentsReady=async()=>true;ctx.validateRestrictSaleItemsCpe=ctx.validateRestrictSellerDiscount=()=>({success:true});ctx.asignPlateNumberToItems=()=>{};
  await ctx.clickPayment();assert.match(messages[0],/Consulte el listado/);assert.equal(ctx.loading_submit,false);
 });
}
test('POS credit clears suggested cash and keeps partial real collections enabled',()=>{
 const payment=load('resources/js/views/tenant/pos/partials/payment.vue');
 const events=[],ctx={form:{payment_condition_id:'01',payments:[{payment:100}]},payments:[{payment:100}],amount:100,enter_amount:100,payment_method_type_id:'01',
  $set:(o,k,v)=>o[k]=v,$eventHub:{$emit:(...args)=>events.push(args)},getTotal:()=>100};
 for(const[name,fn]of Object.entries(payment.methods))ctx[name]=fn.bind(ctx);
 ctx.getTotal=()=>100;
 ctx.changeFiscalPaymentCondition('02');
 assert.equal(ctx.form.payment_condition_id,'02');assert.equal(ctx.form.payments.length,0);assert.equal(ctx.payments.length,0);assert.equal(ctx.enter_amount,0);assert.equal(ctx.button_payment,false);
 ctx.form.payments=[{payment:20}];ctx.setDifference(-80);assert.equal(ctx.button_payment,false);assert.equal(ctx.form.payments[0].payment,20);
 ctx.changeFiscalPaymentCondition('01');assert.equal(ctx.button_payment,true);assert.equal(events.length,2);
});
test('POS does not treat a reclassified method 09 as credit',()=>{
 const payment=load('resources/js/views/tenant/pos/partials/payment.vue');
 const ctx={form:{payment_condition_id:'01'},payment_method_type_id:'09'};
 payment.methods.setDifference.call(ctx,-10);
 assert.equal(ctx.button_payment,true);
 ctx.form.payment_condition_id='02';
 payment.methods.setDifference.call(ctx,-10);
 assert.equal(ctx.button_payment,false);
});
for(const file of ['fast_payment.vue','fast_payment_garage.vue']) {
 test(`${file} preserves credit when reopening payments and changing real amounts`,async()=>{
  const payment=load('resources/js/views/tenant/pos/partials/'+file),events=[];
  const ctx={form:{payment_condition_id:'01',payments:[{payment:100}],total:100},payments:[{payment:100}],amount:100,enter_amount:100,
   $set:(o,k,v)=>o[k]=v,$eventHub:{$emit:(...args)=>events.push(args)},lStoPayment:()=>{}};
  for(const name of ['changeFiscalPaymentCondition','inputAmount','enterAmount'])ctx[name]=payment.methods[name].bind(ctx);
  ctx.changeFiscalPaymentCondition('02');assert.equal(ctx.enter_amount,0);assert.equal(ctx.form.payments.length,0);assert.equal(ctx.button_payment,false);
  await ctx.enterAmount();assert.equal(ctx.form.payments.length,0);
  ctx.amount=20;ctx.inputAmount();assert.equal(ctx.button_payment,false);assert.equal(events.at(-1)[1].payment_condition_id,'02');
  ctx.changeFiscalPaymentCondition('01');assert.equal(ctx.button_payment,true);
 });
}
for(const file of ['multiple_payment.vue','multiple_payment_garage.vue']) {
 test(`${file} opens unpaid credit without inventing payments and preserves real partial amounts`,()=>{
  const component=load('resources/js/views/tenant/pos/partials/'+file);
  const ctx={...component.data(),payments:[],showDialog:true,total:100,paymentConditionId:'02',$set:(o,k,v)=>o[k]=v,$emit:()=>{}};
  for(const[name,fn]of Object.entries(component.methods))ctx[name]=fn.bind(ctx);
  ctx.create();assert.equal(ctx.draftPayments.length,0);
  ctx.pushDraftPayment(20);ctx.pushDraftPayment(0);assert.equal(ctx.draftPayments[0].payment,20);assert.equal(ctx.draftPayments[1].payment,0);
  ctx.payments=[{payment:30}];ctx.create();assert.equal(ctx.draftPayments[0].payment,30);ctx.clickCancel(0);ctx.close();assert.equal(ctx.payments[0].payment,30);
 });
}
for(const file of ['multiple_payment.vue','multiple_payment_garage.vue']) {
 test(`${file} defaults to the current classification and a compatible destination`,()=>{
  const component=load('resources/js/views/tenant/pos/partials/'+file)
  const ctx={currencyTypeId:'USD',payment_method_types:[{id:'01',collection_kind:'account',currency_type_id:'USD',is_active:true},{id:'13',collection_kind:'cash',currency_type_id:'VES',is_active:true}],payment_destinations:[{id:'cash'},{id:2,currency_type_id:'USD'}]}
  const missing=component.methods.buildPaymentRow.call(ctx,'10');assert.equal(missing.payment_method_type_id,null);assert.equal(missing.payment_destination_id,null)
  ctx.payment_method_types.push({id:'10',collection_kind:'cash',currency_type_id:'USD',is_active:true},{id:'04',collection_kind:'cash',currency_type_id:'USD',is_active:true})
  const cash=component.methods.buildPaymentRow.call(ctx,'10');assert.equal(cash.payment_method_type_id,'04');assert.equal(cash.payment_destination_id,'cash')
  ctx.currencyTypeId='VES';assert.equal(component.methods.buildPaymentRow.call(ctx,'10').payment_method_type_id,'13')
 })
}
for(const file of ['payment.vue','fast_payment.vue','fast_payment_garage.vue']) {
 test(`${file} initializes the default using the document currency`,()=>{
  const component=load('resources/js/views/tenant/pos/partials/'+file)
  const ctx={form:{currency_type_id:'USD',total:'100'}, collectionTarget:'100',
   payment_method_types:[{id:'01',is_active:true,collection_kind:'account',currency_type_id:'USD'},
    {id:'04',is_active:true,collection_kind:'cash',currency_type_id:'USD'},
    {id:'07',is_active:true,collection_kind:'cash',currency_type_id:'VES'}],getTotal:()=> '100',initFormCashDocument:()=>{}}
  component.methods.initFormPayment.call(ctx);assert.equal(ctx.form_payment.payment_method_type_id,'04')
  ctx.form.currency_type_id='VES';component.methods.initFormPayment.call(ctx);assert.equal(ctx.form_payment.payment_method_type_id,'07')
  ctx.form.currency_type_id='EUR';component.methods.initFormPayment.call(ctx);assert.equal(ctx.form_payment.payment_method_type_id,null)
 })
}

test('POS normal conserva cobros y condición vigentes ante almacenamiento obsoleto', async () => {
 const receipts=[{id:17,payment:'0.33',calculation_snapshot:{version:1,values:{payment:{n:'1',d:'3'}}}}]
 const component=load('resources/js/views/tenant/pos/partials/payment.vue',{localStorage:{getItem:()=>{throw Error('no debe leer el borrador otra vez')}}})
 const initial={form:{currency_type_id:'VES',payments:JSON.parse(JSON.stringify(receipts))}}
 const initialData=component.data.call(initial)
 assert.deepEqual(JSON.parse(JSON.stringify(initialData.payments)),receipts)
 assert.notEqual(initialData.payments,initial.form.payments)
 for(const condition of ['01','02']) {
  const form={payment_condition_id:condition,payments:JSON.parse(JSON.stringify(receipts))}
  const ctx={form,amount:0,enter_amount:0,inputAmount(){},useLocalCalculations:false}
  component.methods.getFormPosLocalStorage.call(ctx)
  assert.equal(form.payment_condition_id,condition)
  assert.deepEqual(JSON.parse(JSON.stringify(form.payments)),receipts)
  assert.deepEqual(JSON.parse(JSON.stringify(ctx.payments)),receipts)
 }
 const accepted={form:{payments:[]},payments:[],setAmount(){}}
 component.methods.addRow.call(accepted,receipts)
 assert.equal(accepted.form.payments,accepted.payments)
 const dialog=load('resources/js/views/tenant/pos/partials/multiple_payment.vue')
 let release, reads=0
 const ctx={parentOwnsPayments:true,$http:{get:()=>new Promise(resolve=>{release=resolve})},
  getFormPosLocalStorage(){reads++},events(){}}
 const pending=dialog.created.call(ctx)
 release({data:{payment_method_types:[],payment_destinations:[]}})
 await pending
 assert.equal(reads,0)
 for(const scenario of ['actualizado','credito','vacio','sin_pagos','moneda','desmontado','reemplazado','cerrado','dialogo']) {
  let finish;const events=[]
  let form={payment_condition_id:'01',payments:[],total:'12',currency_type_id:'VES'}
  let dialogOpen=false
  const state={parentOwnsPayments:true,showDialog:false,paymentState:()=>form,paymentTotal:()=>form.total,canSuggestPayment:()=>!dialogOpen,total:'4',
   $http:{get:()=>new Promise(resolve=>{finish=resolve})},events(){},buildPaymentRow:(total,currency)=>({payment:total,currency}),
   $emit:(name,value)=>events.push([name,value]),getFormPosLocalStorage:()=>assert.fail('no debe leer almacenamiento')}
  const request=dialog.created.call(state)
  if(scenario==='sin_pagos')delete form.payments
  finish({data:{payment_method_types:[],payment_destinations:[]}})
  if(scenario==='actualizado')form.payments=[{id:17,payment:'3'}]
  if(scenario==='credito')form.payment_condition_id='02'
  if(scenario==='moneda'){form.currency_type_id='USD';form.total='35'}
  if(scenario==='desmontado')state._paymentDisposed=true
  if(scenario==='reemplazado')form={payment_condition_id:'01',payments:[]}
  if(scenario==='cerrado')form=null
  if(scenario==='dialogo')dialogOpen=true
  await request
  assert.equal(events.length,['vacio','sin_pagos','moneda'].includes(scenario)?1:0,scenario)
  if(scenario==='vacio'){assert.equal(events[0][1][0].payment,'12');assert.equal(events[0][1][0].currency,'VES')}
  if(scenario==='moneda'){assert.equal(events[0][1][0].payment,'35');assert.equal(events[0][1][0].currency,'USD')}
 }
 for(const scenario of ['desmontado','reemplazado']) {
  let finish;let current={series_id:17,payments:[{id:9,payment:'3'}]};const original=current
  const state={form:original,paymentState:()=>current,resource:'pos',$http:{get:()=>new Promise(resolve=>{finish=resolve})},
   filterSeries:()=>assert.fail('no debe alterar la serie'),setConfigGlobalDiscountType:()=>assert.fail('no debe continuar'),setDefaultDocumentType:()=>assert.fail('no debe continuar')}
  const request=component.methods.getTables.call(state)
  if(scenario==='desmontado')state._paymentDisposed=true
  else current={series_id:44,payments:[]}
  finish({data:{series:[],payment_method_types:[],global_discount_types:[]}})
  await request
  assert.equal(original.series_id,17)
  assert.equal(original.payments[0].payment,'3')
 }
})

test('el diálogo no aplica cobros de un borrador sustituido o modificado', () => {
 const component=load('resources/js/views/tenant/pos/partials/multiple_payment.vue')
 for (const change of ['reemplazo','cobro','condicion','moneda','total','tasa','cliente','tipo','cierre']) {
  let form={payments:[{id:9,payment:'3'}],payment_condition_id:'01',currency_type_id:'VES',total:'10'}
  const events=[],warnings=[]
  const ctx={...component.methods,parentOwnsPayments:true,paymentState:()=>form,paymentTotal:()=>form.total,
   payments:[{id:9,payment:'3'}],$emit:(...args)=>events.push(args),$message:{warning:message=>warnings.push(message)}}
  ctx.create()
  if(change==='reemplazo')form={payments:[{id:12,payment:'8'}],payment_condition_id:'01',currency_type_id:'VES',total:'10'}
  if(change==='cobro')form.payments=[{id:12,payment:'8'}]
  if(change==='condicion')form.payment_condition_id='02'
  if(change==='moneda')form.currency_type_id='USD'
  if(change==='total')form.total='20'
  if(change==='tasa')form.exchange_rate_sale='40'
  if(change==='cliente')form.customer_id=42
  if(change==='tipo')form.document_type_id='80'
  if(change==='cierre')form=null
  ctx.accept()
  assert.equal(events.some(([name])=>name==='add'),false,change)
  assert.equal(warnings.length,1,change)
  assert.deepEqual(ctx.payments,[{id:9,payment:'3'}],change)
 }
 const current={payments:[{id:'nuevo',payment:'20'}],payment_condition_id:'01',currency_type_id:'VES',total:'20'}
 const events=[]
 const ctx={...component.methods,parentOwnsPayments:true,paymentState:()=>current,paymentTotal:()=>current.total,
  payments:[{id:'viejo',payment:'10'}],$emit:(...args)=>events.push(args),$message:{warning:()=>assert.fail('el borrador vigente es válido')}}
 ctx.create();ctx.accept()
 assert.deepEqual(JSON.parse(JSON.stringify(events.find(([name])=>name==='add')[1])),current.payments)
 assert.equal(ctx.payments[0].id,'viejo')
 current.payments=[];current.payment_condition_id='02';ctx.paymentConditionId='01'
 ctx.create();assert.equal(ctx.draftPayments.length,0)
 current.payments={invalid:true};events.length=0
 ctx.create();assert.equal(events.some(([name])=>name==='add'),false)
 assert.deepEqual(current.payments,{invalid:true})
 current.payments=[{id:'nuevo',payment:'20'}];current.payment_condition_id='01';ctx.create()
 let captured;ctx.showDialog=true;ctx.currencyTypeId='USD';ctx.pushDraftPayment=(amount,currency)=>{captured=[amount,currency]}
 ctx.clickAddPayment('1');assert.deepEqual(captured,['1','VES'])
})

test('POS rápido conserva pagos vivos y descarta la respuesta del catálogo de otra venta', async () => {
 const component=load('resources/js/views/tenant/pos/partials/fast_payment.vue',{localStorage:{getItem:()=>{throw Error('no debe leer otra copia del borrador')}}})
 const saved=[{id:7,payment:'99',currency_type_id:'VES'}]
 let current={payment_condition_id:'01',payments:JSON.parse(JSON.stringify(saved))}
 const state={form:current,payments:[],inputAmount(){},useLocalCalculations:false}
 component.methods.getFormPosLocalStorage.call(state)
 assert.deepEqual(JSON.parse(JSON.stringify(state.form.payments)),saved)
 assert.equal(state.form.payment_condition_id,'01')
 let resolve, filtered=0
 const ctx={form:current,paymentState:()=>current,resource:'pos',$http:{get:()=>new Promise(done=>{resolve=done})},filterSeries(){filtered++}}
 const pending=component.methods.getTables.call(ctx)
 current={payment_condition_id:'02',payments:[{id:8,payment:'5'}]}
 resolve({data:{series:[],payment_method_types:[],payment_destinations:[]}})
 await pending
 assert.equal(filtered,0);assert.equal(current.payment_condition_id,'02');assert.equal(current.payments[0].payment,'5')
 assert.equal(component.methods.events,undefined)
 const view=load('resources/js/views/tenant/pos/fast.vue')
 const parent={draftRestored:true,is_payment:false,form:current}
 ctx.form=current;ctx.paymentState=()=>view.methods.getCurrentPaymentState.call(parent)
 const closing=component.methods.getTables.call(ctx)
 parent.is_payment=true
 resolve({data:{series:[],payment_method_types:[],payment_destinations:[]}})
 await closing
 assert.equal(filtered,0)
})

test('POS rápido conserva el diálogo del comprobante al iniciar la venta siguiente', async () => {
 const view=load('resources/js/views/tenant/pos/fast.vue')
 const panel=load('resources/js/views/tenant/pos/partials/fast_payment.vue')
 const handlers={},form={payments:[{payment:'7'}]},result={showDialogOptions:true,documentNewId:42,printTicketUrl:'/ticket/42',payments:[],form,all_series:[]}
 const ctx={form,establishment:{id:1},paymentPanelGeneration:5,$eventHub:{$on:(name,handler)=>{handlers[name]=handler}},
  initForm(remount=true){if(remount)this.paymentPanelGeneration++;this.form={payments:[]}},
  getTables:async()=>{},selectDefaultCustomer(){},setFormPosLocalStorage(){}}
 await view.methods.events.call(ctx)
 await handlers.saleSuccess()
 assert.equal(ctx.paymentPanelGeneration,5)
 result.form=ctx.form
 panel.watch.form.call(result)
 assert.equal(result.showDialogOptions,true);assert.equal(result.documentNewId,42);assert.equal(result.printTicketUrl,'/ticket/42')
 assert.equal(result.payments.length,0)
 result.form.currency_type_id='VES';result.enter_amount='3';result.singleAmountLocked=false
 result.payment_method_types=[{id:'14',is_active:true,collection_kind:'cash',currency_type_id:'VES'}]
 result.payment_destinations=[{id:'cash'}];result.$message={warning:()=>assert.fail('Debe crear el cobro')};result.inputAmount=()=>{}
 await panel.methods.enterAmount.call(result)
 assert.equal(result.form.payments.length,1);assert.equal(result.form.payments[0].payment,'3')
 assert.equal(result.showDialogOptions,true);assert.equal(result.documentNewId,42)
})
for(const file of ['payment.vue','fast_payment.vue']) {
 test(`${file} waits for the payment catalog before initializing`,async()=>{
  const component=load('resources/js/views/tenant/pos/partials/'+file);let resolve
  const ctx={resource:'pos',$http:{get:()=>new Promise(done=>{resolve=done})},filterSeries(){},setConfigGlobalDiscountType(){},setDefaultDocumentType(){}}
  const pending=component.methods.getTables.call(ctx)
  assert.equal(typeof pending.then,'function')
  resolve({data:{series:[],payment_method_types:[{id:'13',collection_kind:'account'}],global_discount_types:[]}})
  await pending;assert.equal(ctx.payment_method_types[0].id,'13')
 })
}
for(const file of ['payment.vue','fast_payment.vue','fast_payment_garage.vue']) {
 test(`${file} restores credit amounts from actual draft receipts instead of the invoice total`,()=>{
  const draft={payment_condition_id:'02',payments:[{payment:20}]};
  const component=load('resources/js/views/tenant/pos/partials/'+file,{localStorage:{getItem:()=>JSON.stringify(draft)}});
  const ctx={form:file!=='fast_payment_garage.vue'?{total:100,...draft}:{total:100},amount:100,enter_amount:100,$set:(o,k,v)=>o[k]=v,inputAmount:()=>{}};
  component.methods.getFormPosLocalStorage.call(ctx);
  assert.equal(ctx.form.payment_condition_id,'02');assert.equal(ctx.amount,'20');assert.equal(ctx.enter_amount,'20');assert.equal(ctx.form.payments[0].payment,20);
 });
}

// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
for (const file of ['payment.vue','fast_payment.vue','fast_payment_garage.vue']) {
 test(`${file} updates its balance when a currency component changes the applied receipt`, async()=>{
  const Vue=require('vue')
  const summary=require('./helpers/load-module.cjs')(path.resolve('resources/js/mixins/payment-settlement-summary.js')).default
  const component=load('resources/js/views/tenant/pos/partials/'+file)
  const source=fs.readFileSync('resources/js/views/tenant/pos/partials/'+file,'utf8')
  assert.match(source,/mixins:\s*\[paymentSettlementSummary,/)
  const instance=new Vue({mixins:[summary],data:()=>({form:{payment_condition_id:'01',currency_type_id:'VES',total:'100',payments:[{payment:'100',currency_type_id:'USD',original_amount:'33.33',exchange_rate:'3'}]},
    amount:'100',enter_amount:'100',difference:'0',button_payment:false}),
   methods:{inputAmount:component.methods.inputAmount,setDifference:component.methods.setDifference || (()=>{}),
    getTotal:()=>100,lStoPayment:()=>{}}})
  instance.$eventHub={$emit:()=>{}}
  const fields=load('resources/js/views/tenant/documents/partials/igtf_fields.vue')
  const fieldContext={payment:instance.form.payments[0],documentCurrency:'VES',error:'',lastApplied:null,evaluate:()=>{}}
  fields.methods.convertPrincipal.call(fieldContext)
  await Vue.nextTick()
  assert.equal(instance.amount,'99.99')
  assert.equal(instance.difference,'-0.01')
  assert.equal(instance.button_payment,true)
  assert.equal(instance.enter_amount,'100')
  instance.form.payments.push({payment:'0.01'})
  await Vue.nextTick()
  assert.equal(instance.amount,'100')
  assert.equal(instance.difference,'0')
  assert.equal(instance.button_payment,false)
  instance.form.payment_condition_id='02'
  instance.form.payments=[{payment:'20'}]
  await Vue.nextTick()
  assert.equal(instance.amount,'20')
  assert.equal(instance.difference,'-80')
  assert.equal(instance.button_payment,false)
  instance.$destroy()
 })
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########

test('invoice checkout restores the original receipt and guards pending cash quotes', async () => {
 const Vue=require('vue')
 const summary=require('./helpers/load-module.cjs')(path.resolve('resources/js/mixins/payment-settlement-summary.js')).default
 const payment={id:1,payment:'0.335',calculation_snapshot:{version:1,values:{payment:{n:'3349999999999',d:'10000000000000'}}}}
 for (const file of ['payment.vue','fast_payment.vue','fast_payment_garage.vue']) {
  const component=load('resources/js/views/tenant/pos/partials/'+file,{localStorage:{getItem:()=>JSON.stringify({payments:[payment]})}})
  const instance=new Vue({mixins:[summary],data:()=>({
   form:{document_type_id:'01',payment_condition_id:'01',payments:file!=='fast_payment_garage.vue'?[payment]:[]},
   useLocalCalculations:true,igtfQuote:null,igtfQuoteError:null,igtfQuoteLoading:false,button_payment:false,
  }),methods:{getFormPosLocalStorage:component.methods.getFormPosLocalStorage,inputAmount(){ this.button_payment=false }}})
  instance.getFormPosLocalStorage()
  await Vue.nextTick()
  assert.equal(instance.amount,'0.33')
  assert.equal(instance.enter_amount,'0.33')
  assert.deepEqual(JSON.parse(JSON.stringify(instance.form.payments[0])),payment)
  assert.equal(instance.settlementPaymentBlocked,true)
  instance.igtfQuote={balance:'0.00',balance_exact:{n:'1',d:'1000'}}
  assert.equal(instance.settlementPaymentBlocked,false)
  instance.igtfQuoteLoading=true
  assert.equal(instance.settlementPaymentBlocked,true)
  instance.igtfQuoteLoading=false
  instance.igtfQuote={balance:'0.01'}
  assert.equal(instance.settlementPaymentBlocked,true)
  if (file === 'payment.vue') assert.equal(component.computed.differenceText.call(instance),'0.01')
  instance.igtfQuote=null
  instance.igtfQuoteError='Falló la cotización'
  assert.equal(instance.settlementPaymentBlocked,true)
  instance.form.payment_condition_id='02'
  assert.equal(instance.settlementPaymentBlocked,false)
  await Vue.nextTick()
  instance.$destroy()
 }
})

test('POS does not describe bank or unclassified excess as cash change', () => {
  const summary = require('./helpers/load-module.cjs')(path.resolve('resources/js/mixins/payment-settlement-summary.js')).default
  const label = summary.computed.settlementDifferenceLabel
  const ctx = {difference:'10', form:{currency_type_id:'VES',payments:[{payment_method_type_id:'01',payment:'110',currency_type_id:'USD'}]},
    payment_method_types:[{id:'01',collection_kind:'account',currency_type_id:'USD'},{id:'04',collection_kind:'cash',currency_type_id:'VES'}]}
  assert.equal(label.call(ctx),'Exceso')
  ctx.form.payments=[{payment_method_type_id:null,payment:'110'}]
  assert.equal(label.call(ctx),'Exceso')
  ctx.form.payments=[{payment_method_type_id:'04',payment:'10',currency_type_id:'VES'}]
  assert.equal(label.call(ctx),'Vuelto')
  ctx.difference='-5'
  assert.equal(label.call(ctx),'Faltante')
})

test('POS previews exact cash change in each received currency in server allocation order', () => {
  const preview = require('./helpers/load-module.cjs')(path.resolve('resources/js/mixins/payment-settlement-summary.js')).previewSettlementDifference
  const methods = [{id:'10',collection_kind:'cash',currency_type_id:'USD'}, {id:'04',collection_kind:'cash',currency_type_id:'VES'}, {id:'01',collection_kind:'account',currency_type_id:'USD'}]
  const form = {currency_type_id:'VES',payments:[{payment_method_type_id:'10',currency_type_id:'USD',payment:'200',original_amount:'2',exchange_rate:'100'}]}
  assert.equal(preview('100',form,methods,'Bs.').text, 'USD 1.00')
  assert.equal(preview('100',form,methods,'Bs.').label, 'Vuelto')
  assert.equal(preview('68',{currency_type_id:'VES',payments:[{payment_method_type_id:'10',currency_type_id:'USD',payment:'300',original_amount:'30',exchange_rate:'10'}]},methods,'Bs.').text,'USD 6.80')
  assert.equal(preview('1', {...form,payments:[{...form.payments[0],exchange_rate:'300'}]},methods,'Bs.').label,'Exceso')
  form.payments.push({payment_method_type_id:'04',currency_type_id:'VES',payment:'10',original_amount:'10',exchange_rate:'100'})
  assert.equal(preview('205',form,methods,'Bs.').text, 'USD 2.00 · Bs. 5.00')
  assert.equal(preview('211',form,methods,'Bs.').label,'Exceso')
  assert.equal(preview('-5',form,methods,'Bs.').text,'Bs. 5.00')
  const inverse={currency_type_id:'USD',payments:[{payment_method_type_id:'04',currency_type_id:'VES',payment:'2',original_amount:'200',exchange_rate:'100'}]}
  assert.equal(preview('1',inverse,methods,'$').text,'Bs. 100.00')
})

test('POS never previews change from a previously recorded or reused cash receipt', () => {
  const preview = require('./helpers/load-module.cjs')(path.resolve('resources/js/mixins/payment-settlement-summary.js')).previewSettlementDifference
  const methods = [{id:'04',collection_kind:'cash',currency_type_id:'VES'}, {id:'01',collection_kind:'account',currency_type_id:'USD'}]
  const cash={payment_method_type_id:'04',currency_type_id:'VES',payment:'20',original_amount:'20',exchange_rate:'10'}
  const bank={payment_method_type_id:'01',currency_type_id:'USD',payment:'100',original_amount:'10',exchange_rate:'10'}
  for (const origin of [{id:8},{receipt_parent_id:8},{receipt_origin:{id:8}},{payment_method_snapshot:{receipt_origin:{id:8},collection_kind:'cash'}},
    {source_sale_note_payment_id:8},{source_quotation_payment_id:8},{source_technical_service_payment_id:8},{_reused_receipt:true}]) {
    const form={currency_type_id:'VES',payments:[{...cash,...origin},bank]}
    assert.equal(preview('10',form,methods,'Bs.').label,'Exceso',JSON.stringify(origin))
    form.payments.push({...cash,payment:'10',original_amount:'10'})
    assert.equal(preview('10',form,methods,'Bs.').text,'Bs. 10.00',JSON.stringify(origin))
  }
})

test('Garage restores multiple decimal receipts without floating point drift', () => {
  const garage=load('resources/js/views/tenant/pos/partials/fast_payment_garage.vue')
  let total
  const ctx={collectionTarget:'0.30',form:{items:[{}],payment_condition_id:'01',payments:[{payment:'0.10'},{payment:'0.20'}]},
    inputDiscountAmount(){},setAmount(value){total=value}}
  garage.methods.checkPaymentGarage.call(ctx)
  assert.equal(total,'0.3')
  assert.equal(ctx.form.payments.length,2)
})

for (const file of ['multiple_payment.vue','multiple_payment_garage.vue']) {
 test(`${file} preserves registered and reused receipts in the payment dialog`,()=>{
  const component=load('resources/js/views/tenant/pos/partials/'+file)
  const origins=[{id:5},{receipt_parent_id:5},{receipt_origin:{id:5}},
   {payment_method_snapshot:{receipt_origin:{id:5}}},{source_sale_note_payment_id:5},
   {source_quotation_payment_id:5},{source_technical_service_payment_id:5},{_reused_receipt:true}]
  for (const origin of origins) {
   const row={payment:'10',...origin}; const ctx={draftPayments:[row],calculatePayments(){throw Error('historical receipt was removed')}}
   component.methods.clickCancel.call(ctx,0)
   assert.equal(ctx.draftPayments.length,1)
   assert.equal(ctx.draftPayments[0],row)
  }
  const source=fs.readFileSync('resources/js/views/tenant/pos/partials/'+file,'utf8')
  assert.match(source,/<span v-if="isRecordedReceipt\(row\)">/)
  assert.match(source,/:disabled="isRecordedReceipt\(row\)"/)
  assert.match(source,/v-if="!isRecordedReceipt\(row\)" type="button"/)
 })
}

for (const [panel, dialogFile, storageKey, eventName] of [
 ['payment.vue','multiple_payment.vue','form_pos','localSPayments'],
 ['fast_payment.vue','multiple_payment.vue','form_pos','localSPayments'],
 ['fast_payment_garage.vue','multiple_payment_garage.vue','form_pos_garage','localSPaymentsGarage'],
]) {
 test(`${panel} restores an origin-only receipt into the multiple-payment dialog`,()=>{
  const historical={source_quotation_payment_id:42,payment_method_type_id:'01',payment:'100',original_amount:'2.50',
   currency_type_id:'USD',exchange_rate:'40',payment_destination_id:91,payment_destination_description:'Cuenta histórica USD',
   payment_method_snapshot:{id:'01',description:'Método anterior',collection_kind:'account',currency_type_id:'USD'}}
  const localStorage={getItem:key=>{assert.equal(key,storageKey);return JSON.stringify({payment_condition_id:'01',payments:[historical]})}}
  const component=load('resources/js/views/tenant/pos/partials/'+panel,{localStorage})
  let recalculated=0
  const ctx={form:{payment_condition_id:'01',payments:panel!=='fast_payment_garage.vue'?[historical]:[]},$set:(obj,key,value)=>obj[key]=value,inputAmount:()=>{recalculated++}}
  component.methods.getFormPosLocalStorage.call(ctx)
  assert.deepEqual(JSON.parse(JSON.stringify(ctx.form.payments)),[historical])
  assert.equal(String(ctx.amount),'100');assert.equal(recalculated,1)
  const dialogComponent=load('resources/js/views/tenant/pos/partials/'+dialogFile,{localStorage})
  const events=[];const dialogCtx={total:'200',$eventHub:{$emit:(name,value)=>events.push([name,value])},$emit:(name,value)=>events.push([name,value]),
   clickAddPayment:()=>assert.fail('Debe conservar el cobro existente')}
  dialogComponent.methods.getFormPosLocalStorage.call(dialogCtx)
  assert.equal(events[0][0],eventName);assert.equal(events[1][0],'add')
  assert.deepEqual(JSON.parse(JSON.stringify(events[1][1])),[historical])
  dialogCtx.payments=events[1][1];dialogCtx.paymentConditionId='01'
  dialogCtx.clonePayments=dialogComponent.methods.clonePayments
  dialogComponent.methods.create.call(dialogCtx)
  dialogComponent.methods.clickCancel.call(dialogCtx,0)
  assert.deepEqual(JSON.parse(JSON.stringify(dialogCtx.draftPayments)),[historical])
 })
}

// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
for (const file of ['payment.vue','fast_payment.vue','fast_payment_garage.vue']) {
 test(`${file} protects multiple or recorded receipts while accepting a restored single amount`, async()=>{
  const Vue=require('vue')
  const summary=require('./helpers/load-module.cjs')(path.resolve('resources/js/mixins/payment-settlement-summary.js')).default
  const component=load('resources/js/views/tenant/pos/partials/'+file)
  const instance=new Vue({mixins:[summary],data:()=>({form:{payment_condition_id:'01',total:'100',payments:[{payment:'30'}]},
    payments:[],amount:'30',enter_amount:'45.50',difference:'-70',button_payment:true}),
   methods:{enterAmount:component.methods.enterAmount,clickPayment:component.methods.clickPayment,
    ...(component.methods.refreshScaleTotals ? {refreshScaleTotals:component.methods.refreshScaleTotals} : {}),
    inputAmount:component.methods.inputAmount,setDifference:component.methods.setDifference || (()=>{}),
    getTotal:()=>100,lStoPayment:()=>{}}})
  const messages=[];instance.$eventHub={$emit:()=>{}};instance.$message={warning:m=>messages.push(m)}
  assert.equal(instance.singleAmountLocked,false)
  await instance.enterAmount()
  assert.equal(instance.form.payments[0].payment,'45.5')
  assert.equal(instance.amount,'45.5')
  assert.equal(instance.payments[0].payment,'45.5')
  instance.enter_amount='-12'
  assert.equal(instance.singleAmountInvalid,true)
  await instance.enterAmount()
  assert.equal(instance.form.payments[0].payment,'45.5')
  await instance.clickPayment()
  assert.equal(messages[0],'Indique un monto válido.')
  instance.form.payments=[{payment:'40'},{payment:'60'}];instance.enter_amount='80'
  assert.equal(instance.singleAmountLocked,true)
  await instance.enterAmount()
  assert.deepEqual(instance.form.payments.map(row=>row.payment),['40','60'])
  if (file === 'payment.vue') {
   component.methods.setExactAmount.call(instance)
   assert.equal(instance.enter_amount,'80')
   assert.deepEqual(instance.form.payments.map(row=>row.payment),['40','60'])
  }
  instance.form.payments=[{id:77,payment:'40'}]
  assert.equal(instance.singleAmountLocked,true)
  await instance.enterAmount()
  assert.equal(instance.form.payments[0].payment,'40')
  instance.form.payments=[{source_sale_note_payment_id:9,payment:'40'}]
  assert.equal(instance.singleAmountLocked,true)
  await instance.enterAmount()
  assert.equal(instance.form.payments[0].payment,'40')
  for (const origin of [{source_quotation_payment_id:9},{source_technical_service_payment_id:9},{receipt_origin:{id:9}},{receipt_parent_id:9},
    {payment_method_snapshot:{receipt_origin:{id:9}}},{_reused_receipt:true}]) {
   instance.form.payments=[{...origin,payment:'40'}]
   assert.equal(instance.singleAmountLocked,true,JSON.stringify(origin))
   await instance.enterAmount()
   assert.equal(instance.form.payments[0].payment,'40')
  }
  instance.$destroy()
 })
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########
