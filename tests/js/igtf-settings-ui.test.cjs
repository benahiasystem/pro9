const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const compiler = require('vue-template-compiler');
const babel = require('@babel/core');
const Vue = require('vue');

function runtime() {
    const cache = new Map();
    function load(file) {
        file = path.resolve(file);
        if (cache.has(file)) return cache.get(file);
        let source = fs.readFileSync(file, 'utf8');
        if (file.endsWith('.vue')) source = compiler.parseComponent(source).script.content;
        const exports = {};
        cache.set(file, exports);
        vm.runInNewContext(babel.transformSync(source, {configFile:false, babelrc:false,
            plugins:['@babel/plugin-transform-modules-commonjs']}).code, {exports, headers_token:{}, moment:require('moment'), require(name) {
            if (name === 'vue') return Vue;
            if (name === 'vuex') return {mapActions:()=>({}),mapState:()=>({})};
            if (name === '@mixins/fiscal-payment-settings') return load('resources/js/mixins/fiscal-payment-settings.js');
            if (name.includes('exchange-rate-math')) return load('resources/js/helpers/exchange-rate-math.js');
            if (name.includes('payment-classification')) return load('resources/js/helpers/payment-classification.js');
            if (name.includes('tendered-receipt')) return load('resources/js/helpers/tendered-receipt.js');
            if (name.startsWith('.') && /igtf-settings|IgtfSettingsStatus/.test(name)) {
                return load(path.resolve(path.dirname(file), /\.(vue|js)$/.test(name) ? name : name + '.js'));
            }
            return {};
        }});
        return exports;
    }
    return {load, settings:load('resources/js/helpers/igtf-settings.js')};
}
const deferred = () => {let resolve, reject; const promise = new Promise((yes,no) => {resolve=yes;reject=no}); return {promise,resolve,reject}};

test('a positive draft waits for a payment method before requesting a fiscal quote', async () => {
    const {load,settings}=runtime(), mixin=load('resources/js/mixins/fiscal-payment-settings.js').default;
    settings.setIgtfSettings({enabled:true,rate:'3.00'});
    const row={date_of_payment:'2026-11-14',payment_method_type_id:null,payment:'250',currency_type_id:'VES',original_amount:'250',exchange_rate:'10',errors:{}};
    let calls=0;
    const ctx={...mixin.data(),fiscalPaymentsApplicable:true,showDialog:true,documentId:31,records:[row],
        document:{currency_type_id:'VES',exchange_rate_sale:'10',total_difference:'250'},$set:(o,k,v)=>o[k]=v,
        $http:{post:async()=>{calls++;return {data:{data:{payments:[{igtf_status:'not_applicable',tax_amount:'0.00'}]}}}}}};
    for(const [key,fn] of Object.entries(mixin.methods))ctx[key]=fn.bind(ctx);
    assert.equal(ctx.fiscalQuoteRequest([row]),null);
    assert.equal(await ctx.refreshFiscalQuote([row]),null);
    assert.equal(calls,0); assert.equal(ctx.igtfQuoteError,'');
    const complete={...row,payment_method_type_id:'01'};
    assert.equal(ctx.fiscalQuoteRequest([complete,row]),null,'do not quote only the completed part of a mixed group');
    row.payment_method_type_id='01';
    assert.ok(await ctx.refreshFiscalQuote([row]));
    assert.equal(calls,1); assert.equal(row.quoted_tax_amount,'0.00');
});

test('document payment without a method prompts for selection without sending a request', async () => {
    const {load}=runtime(), component=load('resources/js/views/tenant/documents/partials/payments.vue').default;
    const row={payment_method_type_id:null,payment:'250',errors:{},loading:false};
    const ctx={records:[row],documentId:31,$set:(o,k,v)=>o[k]=v,
        ensureFiscalPaymentsReady:()=>assert.fail('must not evaluate without a method')};
    await component.methods.clickSubmit.call(ctx,0);
    assert.equal(row.errors.payment_method_type_id[0],'Seleccione un método de pago.');
    assert.equal(row.loading,false);
});

test('order conversion quotes the actual customer, invoice total and draft receipts', () => {
    const {load}=runtime();
    const mixin=load('resources/js/mixins/fiscal-payment-settings.js').default;
    const receipt={id:null,payment:'50.00',payment_method_type_id:'01',financial_intermediation:'none'};
    const ctx={form:{order_note:{total:'100.00'}},document:{customer_id:42,currency_type_id:'VES',exchange_rate_sale:'40',total:'100.00',payments:[receipt]}};
    Object.defineProperty(ctx,'fiscalQuoteRows',{get:()=>mixin.computed.fiscalQuoteRows.call(ctx)});
    const quote=mixin.methods.fiscalQuoteRequest.call(ctx,ctx.fiscalQuoteRows);
    assert.equal(quote.customer_id,42);
    assert.equal(quote.principal_due,'100.00');
    assert.equal(quote.currency_type_id,'VES');
    assert.equal(quote.payments[0].payment,'50.00');
    ctx.document.payments=[{...receipt,payment:'25.00'}];
    assert.equal(ctx.fiscalQuoteRows[0].payment,'25.00');
});

test('rows and overlapping dialogs share one request, while reopening refreshes the setting', async () => {
    const {settings:s} = runtime(), response = deferred(); let calls=0;
    const http={get(url) {assert.equal(url,'/companies/igtf'); calls++; return response.promise}};
    const first=s.loadIgtfSettings(http), second=s.loadIgtfSettings(http);
    assert.equal(first,second);
    response.resolve({data:{data:{enabled:true,rate:'3.00'}}}); await first;
    assert.equal(calls,1); assert.equal(s.igtfSettings.enabled,true); assert.equal(s.igtfSettings.loading,false);
    await s.loadIgtfSettings({get:async()=>{calls++;return {data:{data:{enabled:false,rate:'3.00'}}}}});
    assert.equal(calls,2); assert.equal(s.igtfSettings.enabled,false);
});

test('saving advanced settings wins over a delayed earlier read', async () => {
    const {settings:s} = runtime(), response=deferred();
    const first=s.loadIgtfSettings({get:()=>response.promise});
    s.setIgtfSettings({enabled:true,rate:4});
    response.resolve({data:{data:{enabled:false,rate:3}}}); await first;
    assert.equal(s.igtfSettings.enabled,true); assert.equal(s.igtfSettings.rate,4);
});

test('advanced configuration publishes only an acknowledged save to payment forms', async () => {
    const {load,settings:s}=runtime(), component=load('resources/js/views/tenant/configurations/form.vue').default;
    s.setIgtfSettings({enabled:false,rate:3});
    const ctx={igtf:{enabled:true,rate:3},igtfLoaded:true,typeUser:'admin',
        $http:{post:async()=>{throw Error('network')}},$message:{success(){}}};
    await component.methods.saveIgtf.call(ctx);assert.equal(s.igtfSettings.enabled,false);
    ctx.$http.post=async()=>({data:{message:'Guardado'}});
    await component.methods.saveIgtf.call(ctx);assert.equal(s.igtfSettings.enabled,true);
});

test('advanced IGTF proposes 3% only when no rate is saved and preserves the saved rate', async () => {
    const {load, settings:s} = runtime();
    const component = load('resources/js/views/tenant/configurations/form.vue').default;
    for (const [stored, expected] of [[null, 3], ['4.000000000000', 4]]) {
        const ctx = {igtfLoading:false, igtfSaving:false, $http:{get:async () => ({data:{data:{enabled:false, rate:stored}}})}};
        await component.methods.loadIgtf.call(ctx);
        assert.equal(ctx.igtf.rate, expected);
        assert.equal(ctx.igtf.enabled, false);
        assert.equal(s.igtfSettings.rate, stored, 'the proposal does not claim to be a saved setting');
    }
});

test('enabling IGTF saves the proposed 3% rate', async () => {
    const {load, settings:s} = runtime();
    const component = load('resources/js/views/tenant/configurations/form.vue').default;
    let sent;
    const ctx = {igtf:{enabled:true, rate:3}, igtfLoaded:true, typeUser:'admin',
        $http:{post:async (url, payload) => {sent={url, payload}; return {data:{message:'Guardado'}}}},
        $message:{success(){}}};
    await component.methods.saveIgtf.call(ctx);
    assert.equal(sent.url, '/companies/igtf');
    assert.equal(sent.payload.enabled, true);
    assert.equal(sent.payload.rate, 3);
    assert.equal(s.igtfSettings.rate, 3);
});

test('failed settings block new collections, support retry, and leave credit without payments usable', async () => {
    const {settings:s} = runtime();
    await s.loadIgtfSettings({get:async()=>{throw Error('network')}});
    assert.equal(s.igtfSettings.loaded,false); assert.equal(s.igtfSettings.loading,false);
    assert.match(s.fiscalPaymentProblem([{payment:'100'}]),/Reintente/);
    assert.equal(s.fiscalPaymentProblem([]),'');
    await s.loadIgtfSettings({get:async()=>({data:{data:{enabled:true,rate:3}}})});
    assert.equal(s.fiscalPaymentProblem([{payment:'100',igtf_status:'subject'}]),'');
});

test('disabled settings preserve and reject hidden draft tax while leaving historical receipts intact', () => {
    const {settings:s} = runtime(); s.setIgtfSettings({enabled:false,rate:3});
    for (const details of [{igtf_status:'subject'},{igtf_status:'exempt'},{tax_amount:'0.01'}]) {
        const row={payment:'100',...details}, before=JSON.stringify(row);
        assert.match(s.fiscalPaymentProblem([row]),details.igtf_status==='exempt' ? /excepción de IGTF retirada/ : /borrador conserva datos IGTF/);
        assert.equal(JSON.stringify(row),before);
        assert.equal(s.fiscalPaymentProblem([{...row,id:12}]),'');
    }
    assert.equal(s.fiscalPaymentProblem([{payment:'100',igtf_status:'not_applicable'}]),'');
    for (const origin of [{receipt_parent_id:5},{receipt_origin:{id:5}}, {payment_method_snapshot:{receipt_origin:{id:5}}},
        {source_sale_note_payment_id:5},{source_quotation_payment_id:5},{source_technical_service_payment_id:5},{_reused_receipt:true}]) {
        assert.equal(s.fiscalPaymentProblem([{payment:'100',igtf_status:'subject',...origin}]),'');
    }
});

test('the quote displays a cross-currency settlement adjustment before confirmation', () => {
    const file='resources/js/components/IgtfQuoteSummary.vue', parsed=compiler.parseComponent(fs.readFileSync(file,'utf8'));
    const component=runtime().load(file).default, render=compiler.compileToFunctions(parsed.template.content);
    const instance=new Vue({...component,...render,propsData:{currency:'VES',quote:{igtf_enabled:true,igtf_rate:'3',
        payments:[{reason:'Cobro sujeto a IGTF.',tax_amount:'0.02',currency_type_id:'USD',change:'0.00',settlement_adjustment:'-2.62'}],
        received_by_currency:{USD:'0.58'},tendered_by_currency:{USD:'0.58'}}}});
    function texts(node) {return (node.text||'')+(node.children||[]).map(texts).join('')}
    assert.match(texts(instance._render()),/Ajuste por liquidación en centavos: -2\.62 VES/);
    instance.$destroy();
});

test('the quote renders the exact rounding adjustment without hiding subprecision fractions', () => {
    const file='resources/js/components/IgtfQuoteSummary.vue', parsed=compiler.parseComponent(fs.readFileSync(file,'utf8'));
    const component=runtime().load(file).default, render=compiler.compileToFunctions(parsed.template.content);
    const row={reason:'Cobro sujeto a IGTF.',tax_amount:'0.17',currency_type_id:'USD',change:'0.00',
        settlement_adjustment:'0.71',settlement_adjustment_exact:{n:'711669',d:'1000000'}};
    const instance=new Vue({...component,...render,propsData:{currency:'VES',quote:{igtf_enabled:true,igtf_rate:'3',
        payments:[row],received_by_currency:{USD:'5.74'},tendered_by_currency:{USD:'5.74'}}}});
    function texts(node) {return (node.text||'')+(node.children||[]).map(texts).join('')}
    assert.match(texts(instance._render()),/0\.71 VES \(detalle exacto: 0\.711669 VES\)/);
    assert.equal(component.methods.adjustmentDetail({settlement_adjustment:'0.00',settlement_adjustment_exact:{n:'1',d:'10000000000000'}}),'1/10000000000000');
    instance.$destroy();
});

test('applied payment uses only a current quote and never replaces the received-dollar inputs', () => {
    const {load}=runtime(), mixin=load('resources/js/mixins/fiscal-payment-settings.js').default;
    const zero={payment:'0'}, row={payment:'4999.29',original_amount:'5.57',currency_type_id:'USD',exchange_rate:'897.53830000'};
    const ctx={fiscalQuoteRows:[zero,row],igtfQuote:{payments:[{payment:'5000'}]},igtfQuoteKey:'current',fiscalQuoteKey:'current'};
    const before=JSON.stringify(row);
    assert.equal(mixin.methods.fiscalAppliedPayment.call(ctx,row),'5000');
    assert.equal(JSON.stringify(row),before);
    ctx.fiscalQuoteKey='new-amount';
    assert.equal(mixin.methods.fiscalAppliedPayment.call(ctx,row),'4999.29');
    ctx.fiscalQuoteKey='current';ctx.igtfQuoteLoading=true;
    assert.equal(mixin.methods.fiscalAppliedPayment.call(ctx,row),'4999.29');
    const source=fs.readFileSync('resources/js/views/tenant/documents/invoice_generate.vue','utf8');
    assert.match(source,/:value="fiscalPaymentsApplicable && !isRecordedReceipt\(row\) \? row.tendered_amount : fiscalAppliedPayment\(row\)"/);
    assert.match(source,/@input="fiscalPaymentsApplicable \? updateTenderedPayment\(row, \$event\) : updatePaymentPrincipal\(row, \$event\)"/);
});

test('the rendered editor keeps automatic classification without administrative exception controls', () => {
    const {load}=runtime(), file='resources/js/views/tenant/documents/partials/igtf_fields.vue';
    const component=load(file).default, parsed=compiler.parseComponent(fs.readFileSync(file,'utf8'));
    const render=compiler.compileToFunctions(parsed.template.content);
    function texts(node) {return (node.text||'')+(node.children||[]).map(texts).join('')}
    const instance=new Vue({...component,...render,propsData:{payment:{payment:'100',igtf_status:'exempt'},igtfEnabled:false}});
    const hidden=texts(instance._render());
    assert.doesNotMatch(hidden,/Moneda efectivamente recibida|Intermediación del cobro/);
    assert.doesNotMatch(hidden,/Clasificación IGTF|Excepción con autorización administrativa|Propuesta:/);
    instance.igtfEnabled=true;
    const visible=texts(instance._render());
    assert.match(visible,/Clasificación IGTF/);
    // Text in option labels and exception placeholders is carried by VNode attributes.
    function controls(node) {return [node,...(node.children||[]).flatMap(controls)]}
    assert.ok(!controls(instance._render()).some(node=>/excepción/i.test(node.data?.attrs?.placeholder || '')));
    assert.equal(component.methods.setExemption,undefined);
    instance.$destroy();
});

test('reopening refreshes the proposal even when the enabled switch has not changed', async () => {
    const {load}=runtime(), component=load('resources/js/views/tenant/documents/partials/igtf_fields.vue').default;
    let evaluations=0;
    const instance=new Vue({...component,methods:{...component.methods,evaluate:()=>evaluations++},
        propsData:{payment:{payment:'100'},igtfEnabled:true,settingsVersion:1}});
    const initialEvaluations=evaluations;
    instance.settingsVersion=2;await Vue.nextTick();assert.equal(evaluations,initialEvaluations+1);
    instance.igtfEnabled=false;instance.settingsVersion=3;await Vue.nextTick();assert.equal(evaluations,initialEvaluations+2);
    instance.$destroy();
});

test('disabled IGTF does not silently classify a payment as subject; enabled IGTF still uses the server proposal', async () => {
    const {load}=runtime(), component=load('resources/js/views/tenant/documents/partials/igtf_fields.vue').default;
    const ctx={payment:{payment:'100',igtf_status:'not_applicable'},documentCurrency:'VES',igtfEnabled:false,sequence:0,
        $set:(o,k,v)=>o[k]=v,$http:{post:async()=>({data:{data:{payment:{igtf_status:'subject'}}}})}};
    await component.methods.evaluate.call(ctx); assert.equal(ctx.payment.igtf_status,'not_applicable');
    ctx.igtfEnabled=true; await component.methods.evaluate.call(ctx); assert.equal(ctx.payment.igtf_status,'subject');
});

test('retiring an old draft preserves its money and UUID while leaving recorded receipts intact', () => {
    const {load}=runtime(), {retireDraftIgtfException}=load('resources/js/helpers/payment-classification.js');
    const row={igtf_status:'exempt',exemption_reason:'Motivo',exception_basis:'Fundamento',quoted_tax_amount:'0.00',
        operation_key:'original-uuid',currency_type_id:'USD',original_amount:'10.00',payment:'100.00',exchange_rate:'10'};
    const stored={...row,id:12}, before=JSON.stringify(stored);
    assert.equal(retireDraftIgtfException(row),true);
    assert.equal(row.igtf_status,'not_applicable'); assert.equal(row.exemption_reason,undefined);
    assert.equal(row.exception_basis,undefined); assert.equal(row.quoted_tax_amount,undefined);
    assert.equal(row.operation_key,'original-uuid');assert.equal(row.original_amount,'10.00');assert.equal(row.exchange_rate,'10');
    assert.equal(retireDraftIgtfException(stored),false);assert.equal(JSON.stringify(stored),before);
});

// ######## INICIO RETIRADA DE EXCEPCION ADMINISTRATIVA IGTF ########
test('restoring retired exception drafts clears a cached quote and requests a current evaluation', () => {
    const {load}=runtime(), mixin=load('resources/js/mixins/fiscal-payment-settings.js').default;
    const rows=[{igtf_status:'exempt',payment:'10',exemption_reason:'Anterior'},
        {igtf_status:'exempt',payment:'20',exception_basis:'Anterior'}, {id:12,igtf_status:'exempt',exemption_reason:'Histórico'}];
    const before=JSON.stringify(rows[2]);let refreshes=0;
    const ctx={igtfQuote:{tax_in_document_currency:'0.00'},igtfQuoteKey:'old',igtfQuoteSequence:3,
        igtfQuoteLoading:true,igtfQuoteError:'old',refreshFiscalQuote(){refreshes++;assert.equal(this.igtfQuote,null)}};
    mixin.watch.fiscalQuoteRows.handler.call(ctx,rows);
    assert.equal(refreshes,1);assert.equal(ctx.igtfQuoteKey,null);assert.equal(ctx.igtfQuoteSequence,4);
    assert.equal(ctx.igtfQuoteLoading,false);assert.equal(ctx.igtfQuoteError,'');
    assert.equal(rows[0].igtf_status,'not_applicable');assert.equal(rows[1].igtf_status,'not_applicable');
    assert.equal(JSON.stringify(rows[2]),before);
    mixin.watch.fiscalQuoteRows.handler.call(ctx,rows);assert.equal(refreshes,1);
});
// ######## FIN RETIRADA DE EXCEPCION ADMINISTRATIVA IGTF ########

test('a same-currency amount stays current even while its proposal request is pending', async () => {
    const {load}=runtime(), component=load('resources/js/views/tenant/documents/partials/igtf_fields.vue').default,response=deferred();
    const ctx={payment:{payment:'100',original_amount:'100',currency_type_id:'VES'},documentCurrency:'VES',sequence:0,lastApplied:null,
        $set:(o,k,v)=>o[k]=v,$http:{post:()=>response.promise}};
    const request=component.methods.evaluate.call(ctx);
    ctx.payment.payment='50';
    component.methods.proposeReceived.call(ctx);
    assert.equal(ctx.payment.original_amount,'50');
    response.resolve({data:{data:{payment:{igtf_status:'not_applicable'}}}}); await request;
});

test('a reused receipt does not request a new fiscal proposal or change its historical amounts', async () => {
    const {load}=runtime(), component=load('resources/js/views/tenant/documents/partials/igtf_fields.vue').default;
    for (const origin of [{id:5},{receipt_parent_id:5},{receipt_origin:{id:5}},
        {payment_method_snapshot:{receipt_origin:{id:5}}},{source_sale_note_payment_id:5},
        {source_quotation_payment_id:5},{source_technical_service_payment_id:5},{_reused_receipt:true}]) {
        const payment={payment:'100',currency_type_id:'VES',original_amount:'100',exchange_rate:'10',...origin};
        const before=JSON.stringify(payment); let calls=0;
        const ctx={payment,documentCurrency:'VES',documentRate:'20',methodCurrency:'USD',sequence:0,
            $set:(o,k,v)=>o[k]=v,$http:{post:async()=>{calls++;return {data:{data:{}}}}}};
        component.created.call(ctx);
        component.methods.syncMethodCurrency.call(ctx);
        component.methods.proposeReceived.call(ctx);
        component.methods.convertPrincipal.call(ctx);
        await component.methods.evaluate.call(ctx);
        assert.equal(calls,0);
        assert.equal(JSON.stringify(payment),before,JSON.stringify(origin));
    }
});

test('a pending fiscal proposal cannot change a row that becomes a reused receipt', async () => {
    const {load}=runtime(), component=load('resources/js/views/tenant/documents/partials/igtf_fields.vue').default,response=deferred();
    const payment={payment:'100',igtf_status:'not_applicable'},ctx={payment,documentCurrency:'VES',igtfEnabled:true,sequence:0,
        $set:(o,k,v)=>o[k]=v,$http:{post:()=>response.promise}};
    const request=component.methods.evaluate.call(ctx);
    payment.source_quotation_payment_id=5;
    response.resolve({data:{data:{payment:{igtf_status:'subject'}}}});
    await request;
    assert.equal(payment.igtf_status,'not_applicable');
    assert.equal(ctx.proposal,null);
});

test('an invalid exchange rate identifies the missing rate and discards a pending proposal', async () => {
    const {load}=runtime(), component=load('resources/js/views/tenant/documents/partials/igtf_fields.vue').default, response=deferred();
    const payment={payment:'500',original_amount:'0.56',currency_type_id:'USD',exchange_rate:'897.53830000',igtf_status:'not_applicable'};
    const ctx={payment,documentCurrency:'VES',igtfEnabled:true,sequence:0,proposal:null,
        $set:(o,k,v)=>o[k]=v,$http:{post:()=>response.promise}};
    const pending=component.methods.evaluate.call(ctx);
    payment.exchange_rate='';
    component.methods.convertPrincipal.call(ctx);
    assert.match(ctx.error,/tasa VES por USD válida/);
    assert.equal(payment.payment,'500');
    response.resolve({data:{data:{payment:{igtf_status:'subject'}}}});
    await pending;
    assert.match(ctx.error,/tasa VES por USD válida/);
    assert.equal(ctx.proposal,null);
});

test('a proposal for another document or removed payment cannot change the current payment', async () => {
    const {load}=runtime(), component=load('resources/js/views/tenant/documents/partials/igtf_fields.vue').default, response=deferred();
    const old={payment:'100',igtf_status:'not_applicable'},ctx={payment:old,documentCurrency:'VES',igtfEnabled:true,sequence:0,
        $set:(o,k,v)=>o[k]=v,$http:{post:()=>response.promise}};
    const request=component.methods.evaluate.call(ctx);
    ctx.payment={payment:'50',igtf_status:'not_applicable'};
    response.resolve({data:{data:{payment:{igtf_status:'subject'}}}});await request;
    assert.equal(ctx.payment.igtf_status,'not_applicable');assert.equal(old.igtf_status,'not_applicable');
});

test('a changed document context invalidates a proposal before Vue flushes its watchers', async () => {
    const {load}=runtime(), component=load('resources/js/views/tenant/documents/partials/igtf_fields.vue').default,response=deferred();
    const ctx={payment:{payment:'100',igtf_status:'not_applicable'},documentId:1,documentCurrency:'VES',igtfEnabled:true,sequence:0,
        $set:(o,k,v)=>o[k]=v,$http:{post:()=>response.promise}};
    const request=component.methods.evaluate.call(ctx);ctx.documentId=2;
    response.resolve({data:{data:{payment:{igtf_status:'subject'}}}});await request;
    assert.equal(ctx.payment.igtf_status,'not_applicable');assert.equal(ctx.proposal,null);
});

test('payment entry lifecycle refreshes settings and excludes sale notes and credit notes', () => {
    const {load}=runtime(), mixin=load('resources/js/mixins/fiscal-payment-settings.js').default;
    for(const type of ['01','08','07','80']) assert.equal(mixin.computed.fiscalPaymentsApplicable.call({form:{document_type_id:type}}),['01','08'].includes(type));
    let calls=0;const ctx={fiscalPaymentsApplicable:true,refreshIgtfSettings:()=>calls++};
    mixin.created.call(ctx);mixin.watch.showDialog.call(ctx,true);mixin.watch.is_payment.call(ctx,true);
    assert.equal(calls,3);
    ctx.fiscalPaymentsApplicable=false;mixin.watch.showDialog.call(ctx,true);assert.equal(calls,3);
});

test('preflight prevents concurrent submissions and releases the form after a failed read', async () => {
    const {load}=runtime(), mixin=load('resources/js/mixins/fiscal-payment-settings.js').default,response=deferred();
    const messages=[],ctx={...mixin.data(),fiscalPaymentsApplicable:true,$http:{get:()=>response.promise},$message:{error:m=>messages.push(m)}};
    for(const[k,fn]of Object.entries(mixin.methods))ctx[k]=fn.bind(ctx);
    const first=ctx.ensureFiscalPaymentsReady([{payment:'100'}]);
    assert.equal(await ctx.ensureFiscalPaymentsReady([{payment:'100'}]),false);
    response.reject(Error('network')); assert.equal(await first,false);
    assert.equal(ctx.igtfPreparingSubmission,false);assert.match(messages[0],/Reintente/);
});

test('a preflight completing after closing or replacing the checkout cannot submit a different sale', async () => {
    for (const change of [ctx=>{ctx.showDialog=false},ctx=>{ctx.form={document_type_id:'01'}}]) {
        const {load}=runtime(), mixin=load('resources/js/mixins/fiscal-payment-settings.js').default,response=deferred();
        const ctx={...mixin.data(),form:{document_type_id:'01'},fiscalPaymentsApplicable:true,
            $http:{get:()=>response.promise},$message:{error:()=>assert.fail('No error for a closed form')}};
        for(const[k,fn]of Object.entries(mixin.methods))ctx[k]=fn.bind(ctx);
        const pending=ctx.ensureFiscalPaymentsReady([{payment:'100'}]);change(ctx);
        response.resolve({data:{data:{enabled:true,rate:3}}});assert.equal(await pending,false);
    }
});

test('later collections submit the entered fiscal context and preserve their draft on preflight failure', async () => {
    const {load}=runtime(), component=load('resources/js/views/tenant/documents/partials/payments.vue').default;
    const row={payment_method_type_id:'10',payment:'100',currency_type_id:'USD',original_amount:'10',exchange_rate:'10.00000000',exchange_rate_source:'manual',
        operation_key:'00000000-0000-4000-8000-000000000001',igtf_status:'subject',financial_intermediation:'none'};
    let sent,allow=false;
    const ctx={records:[row],document:{amount_payable:'100'},documentId:44,resource:'document_payments',
        ensureFiscalPaymentsReady:async()=>allow,$http:{post:async(url,form)=>{sent={url,form};return {data:{success:false,message:'Conservar borrador'}}}},
        $message:{error(){}},getRequestErrorMessage:()=>''};
    await component.methods.clickSubmit.call(ctx,0); assert.equal(sent,undefined);assert.equal(ctx.records[0],row);
    allow=true; await component.methods.clickSubmit.call(ctx,0);
    assert.equal(sent.url,'/document_payments');
    for(const key of Object.keys(row).filter(key=>key!=='loading'))assert.equal(sent.form[key],row[key],key);
});

test('historical receipt tax and derived receipts remain visible with IGTF disabled', () => {
    const {load}=runtime(), file='resources/js/views/tenant/documents/partials/payments.vue',component=load(file).default;
    const render=compiler.compileToFunctions(compiler.parseComponent(fs.readFileSync(file,'utf8')).template.content);
    // Render the read-only branch without running the dialog's HTTP lifecycle.
    const instance=new Vue({...component,...render,created:undefined,mixins:[],propsData:{showDialog:true,documentId:44},
        data:()=>({...component.data(),records:[{id:12,payment:'100',original_amount:'100',tax_amount:'3.00',currency_type_id:'USD'},
            {id:13,payment:'3.00',receipt_parent_id:12}],permissions:{},document:{},igtfEnabled:false})});
    function texts(node){return (node.text||'')+(node.children||[]).map(texts).join('')}
    const output=texts(instance._render());
    assert.match(output,/recibido; IGTF 3.00/);assert.match(output,/IGTF del cobro PAGO-12/);
    instance.$destroy();
});

test('all supported entrypoints pass the shared flag and real payment context', () => {
    for(const name of ['documents/invoice','documents/invoiceupdate','documents/invoice_generate','documents/partials/payments',
        'pos/partials/payment','pos/partials/fast_payment','pos/partials/fast_payment_garage','pos/partials/multiple_payment','pos/partials/multiple_payment_garage']) {
        const source=compiler.parseComponent(fs.readFileSync(`resources/js/views/tenant/${name}.vue`,'utf8'));
        const result=compiler.compile(source.template.content);assert.deepEqual(result.errors,[],name);
        const fields=[],seen=new Set();function visit(n){if(n.type!==1||seen.has(n))return;seen.add(n);if(n.tag==='igtf-payment-fields')fields.push(n);(n.children||[]).forEach(visit);(n.ifConditions||[]).forEach(branch=>visit(branch.block))}visit(result.ast);
        assert.equal(fields.length,1,name);const attrs=fields[0].attrsMap;
        assert.equal(attrs[':igtf-enabled'],'igtfEnabled',name);
        assert.ok(attrs[':document-currency'],name);assert.ok(attrs[':document-rate'],name);
        assert.ok(attrs[':customer-id']||attrs[':document-id'],name);
    }
});

test('checkout quotes the full receipt and requires review if the tax changes', async () => {
    const {load,settings}=runtime(), mixin=load('resources/js/mixins/fiscal-payment-settings.js').default;
    settings.setIgtfSettings({enabled:true,rate:'3.00'});
    const row={date_of_payment:'2026-09-10',payment_method_type_id:'10',currency_type_id:'USD',
        original_amount:'100',payment:'1000',exchange_rate:'10',financial_intermediation:'none'};
    const messages=[];let tax='3.00';
    const ctx={...mixin.data(), fiscalPaymentsApplicable:true, form:{document_type_id:'01',currency_type_id:'VES',
        exchange_rate_sale:'10',total_payable:'1000',payments:[row]},$set:(target,key,value)=>target[key]=value,
        $message:{error:value=>messages.push(value)},$http:{get:async()=>({data:{data:{enabled:true,rate:'3.00'}}}),
            post:async(url,body)=>{assert.equal(url,'/documents/payment-quote');assert.equal(body.payments[0].original_amount,'100');
                return {data:{data:{payments:[{igtf_status:'subject',tax_amount:tax}]}}}}}};
    for(const [key,fn] of Object.entries(mixin.methods)) ctx[key]=fn.bind(ctx);
    await ctx.refreshFiscalQuote(ctx.form.payments);
    assert.equal(row.quoted_tax_amount,'3.00');assert.equal(row.igtf_status,'subject');
    tax='4.00';assert.equal(await ctx.ensureFiscalPaymentsReady(ctx.form.payments),false);
    assert.match(messages.pop(),/Revise el IGTF actualizado/);assert.equal(row.quoted_tax_amount,'4.00');
    assert.equal(await ctx.ensureFiscalPaymentsReady(ctx.form.payments),true);
});

test('invoice quote displays the server validation for an unrepresentable cash change', async () => {
    const {load,settings}=runtime(), mixin=load('resources/js/mixins/fiscal-payment-settings.js').default;
    settings.setIgtfSettings({enabled:true,rate:'3'});
    const row={date_of_payment:'2026-11-08',payment_method_type_id:'06',currency_type_id:'USD',
        original_amount:'0.56',payment:'502.62',exchange_rate:'897.53830000',financial_intermediation:'none'};
    const message='El vuelto convertido requiere un importe liquidable en céntimos.';
    for (const data of [{payments:[message]}, {errors:{payments:[message]}}, {message:{payments:[message]}}]) {
        const ctx={...mixin.data(),fiscalPaymentsApplicable:true, form:{document_type_id:'01',currency_type_id:'VES',
            exchange_rate_sale:'897.53830000',total_payable:'500',payments:[row]},
            $http:{post:async()=>{throw {response:{status:422,data}}}}};
        for(const [key,fn] of Object.entries(mixin.methods)) ctx[key]=fn.bind(ctx);
        assert.equal(await ctx.refreshFiscalQuote(ctx.form.payments),null);
        assert.equal(ctx.igtfQuoteError,message);
        assert.equal(ctx.form.payments[0],row);
    }
});

test('inline Garage and fast checkouts quote and submit while other closed checkouts remain inactive', async () => {
    const {load,settings}=runtime(), mixin=load('resources/js/mixins/fiscal-payment-settings.js').default;
    settings.setIgtfSettings({enabled:false,rate:'3.00'});
    const row={date_of_payment:'2026-09-10',payment_method_type_id:'14',currency_type_id:'VES',original_amount:'100',payment:'100',exchange_rate:'10'};
    for (const file of ['fast_payment_garage.vue', 'fast_payment.vue']) {
    const component=load('resources/js/views/tenant/pos/partials/'+file).default;
    const ctx={...mixin.data(),...component.data(),fiscalPaymentsApplicable:true,is_payment:false,
        form:{document_type_id:'01',currency_type_id:'VES',exchange_rate_sale:'10',total:'100',payments:[row]},
        $set:(target,key,value)=>target[key]=value,$message:{error:()=>{}},
        $http:{get:async()=>({data:{data:{enabled:false,rate:'3.00'}}}),post:async()=>({data:{data:{payments:[{igtf_status:'not_applicable',tax_amount:'0.00'}]}}})}};
    for(const [key,fn] of Object.entries(mixin.methods)) ctx[key]=fn.bind(ctx);
    assert.ok(await ctx.refreshFiscalQuote(ctx.form.payments));
    assert.equal(await ctx.ensureFiscalPaymentsReady(ctx.form.payments),true);
    ctx.fiscalQuoteInline=false;
    assert.equal(await ctx.refreshFiscalQuote(ctx.form.payments),null);
    assert.equal(await ctx.ensureFiscalPaymentsReady(ctx.form.payments),false);
    }
});

test('fast checkout quotes and confirms the first directly entered cash receipt', async () => {
    const {load,settings}=runtime(), mixin=load('resources/js/mixins/fiscal-payment-settings.js').default;
    const component=load('resources/js/views/tenant/pos/partials/fast_payment.vue').default;
    settings.setIgtfSettings({enabled:false,rate:'3.00'});
    const requests=[], messages=[];
    const ctx={...mixin.data(),...component.data(),fiscalPaymentsApplicable:true,is_payment:false,
        form:{document_type_id:'01',currency_type_id:'VES',exchange_rate_sale:'40',total:'0.30',payments:[]},
        enter_amount:'0.30',singleAmountLocked:false,
        payment_method_types:[{id:'14',is_active:true,collection_kind:'cash',currency_type_id:'VES'}],
        payment_destinations:[{id:'cash'}],inputAmount:()=>{},$set:(target,key,value)=>target[key]=value,
        $message:{warning:m=>messages.push(m),error:m=>messages.push(m)},
        $http:{get:async()=>({data:{data:{enabled:false,rate:'3.00'}}}),post:async(url,body)=>{
            requests.push({url,body});return {data:{data:{payments:[{igtf_status:'not_applicable',tax_amount:'0.00'}]}}};
        }}};
    for(const [key,fn] of Object.entries(mixin.methods)) ctx[key]=fn.bind(ctx);
    await component.methods.enterAmount.call(ctx);
    assert.equal(ctx.form.payments[0].payment,'0.3');
    assert.ok(await ctx.refreshFiscalQuote(ctx.form.payments));
    assert.equal(requests[0].body.payments[0].payment,'0.3');
    assert.equal(requests[0].body.payments[0].original_amount,'0.3');
    assert.equal(await ctx.ensureFiscalPaymentsReady(ctx.form.payments),true);
    assert.deepEqual(messages,[]);
});

test('a credit invoice with a zero placeholder does not require a tax quote', async () => {
    const {load,settings}=runtime(), mixin=load('resources/js/mixins/fiscal-payment-settings.js').default;
    settings.setIgtfSettings({enabled:true,rate:'3.00'});
    const ctx={...mixin.data(),fiscalPaymentsApplicable:true,form:{document_type_id:'01',currency_type_id:'VES',
        exchange_rate_sale:'10',total_payable:'100'},$message:{error:()=>assert.fail('No error for zero receipt')},
        $http:{get:async()=>({data:{data:{enabled:true,rate:'3.00'}}}),post:()=>assert.fail('No quote without actual money')}};
    for(const [key,fn] of Object.entries(mixin.methods))ctx[key]=fn.bind(ctx);
    assert.equal(await ctx.ensureFiscalPaymentsReady([{payment:'0.00'}]),true);
});

test('a cross-currency quote does not invent a missing payment exchange rate', () => {
    const {load}=runtime(), mixin=load('resources/js/mixins/fiscal-payment-settings.js').default;
    const ctx={form:{currency_type_id:'VES',exchange_rate_sale:'40',total_payable:'100'}};
    const request=mixin.methods.fiscalQuoteRequest.call(ctx,[{date_of_payment:'2026-09-10',
        payment_method_type_id:'10',currency_type_id:'USD',original_amount:'2.50',payment:'100'}]);
    assert.equal(request.payments[0].exchange_rate,null);
});

test('a pending quote cannot update a payment after its amount changes', async () => {
    const {load,settings}=runtime(), mixin=load('resources/js/mixins/fiscal-payment-settings.js').default, response=deferred();
    settings.setIgtfSettings({enabled:true,rate:'3.00'});
    const row={date_of_payment:'2026-09-10',payment_method_type_id:'10',currency_type_id:'USD',original_amount:'100',payment:'1000',exchange_rate:'10'};
    const ctx={...mixin.data(),fiscalPaymentsApplicable:true,form:{document_type_id:'01',currency_type_id:'VES',
        exchange_rate_sale:'10',total_payable:'1000',payments:[row]},$set:(target,key,value)=>target[key]=value,
        $http:{post:()=>response.promise}};
    for(const [key,fn] of Object.entries(mixin.methods))ctx[key]=fn.bind(ctx);
    const pending=ctx.refreshFiscalQuote(ctx.form.payments);
    row.original_amount='50';row.payment='500';
    response.resolve({data:{data:{payments:[{igtf_status:'subject',tax_amount:'3.00'}]}}});
    assert.equal(await pending,null);assert.equal(row.quoted_tax_amount,undefined);assert.equal(ctx.igtfQuote,null);
});

test('a pending quote cannot update a checkout whose payment list was replaced', async () => {
    const {load,settings}=runtime(), mixin=load('resources/js/mixins/fiscal-payment-settings.js').default, response=deferred();
    settings.setIgtfSettings({enabled:true,rate:'3.00'});
    const old={date_of_payment:'2026-09-10',payment_method_type_id:'10',currency_type_id:'USD',original_amount:'100',payment:'1000',exchange_rate:'10'};
    const ctx={...mixin.data(),fiscalPaymentsApplicable:true,form:{document_type_id:'01',currency_type_id:'VES',
        exchange_rate_sale:'10',total_payable:'1000',payments:[old]},$set:(target,key,value)=>target[key]=value,
        $http:{post:()=>response.promise}};
    for(const [key,fn] of Object.entries(mixin.methods))ctx[key]=fn.bind(ctx);
    const pending=ctx.refreshFiscalQuote(ctx.form.payments);
    const current={...old};ctx.form.payments=[current];
    response.resolve({data:{data:{payments:[{igtf_status:'subject',tax_amount:'3.00'}]}}});
    assert.equal(await pending,null);assert.equal(old.quoted_tax_amount,undefined);
    assert.equal(current.quoted_tax_amount,undefined);assert.equal(ctx.igtfQuote,null);
});

test('a pending conversion quote cannot update a replaced document receipt list', async () => {
    const {load,settings}=runtime(), mixin=load('resources/js/mixins/fiscal-payment-settings.js').default, response=deferred();
    settings.setIgtfSettings({enabled:true,rate:'3.00'});
    const old={payment_method_type_id:'10',currency_type_id:'USD',original_amount:'50',payment:'500',exchange_rate:'10'};
    const ctx={...mixin.data(),fiscalPaymentsApplicable:true,form:{order_note:{}},
        document:{customer_id:42,currency_type_id:'VES',exchange_rate_sale:'10',total:'1000',payments:[old]},
        $set:(target,key,value)=>target[key]=value,$http:{post:()=>response.promise}};
    for(const [key,fn] of Object.entries(mixin.methods))ctx[key]=fn.bind(ctx);
    const pending=ctx.refreshFiscalQuote(ctx.document.payments);
    const current={...old};ctx.document.payments=[current];
    response.resolve({data:{data:{payments:[{igtf_status:'subject',tax_amount:'1.50'}]}}});
    assert.equal(await pending,null);assert.equal(old.quoted_tax_amount,undefined);
    assert.equal(current.quoted_tax_amount,undefined);assert.equal(ctx.igtfQuote,null);
});

test('closing the payment dialog discards a pending quote', async () => {
    const {load,settings}=runtime(), mixin=load('resources/js/mixins/fiscal-payment-settings.js').default, response=deferred();
    settings.setIgtfSettings({enabled:true,rate:'3.00'});
    const row={date_of_payment:'2026-09-10',payment_method_type_id:'10',currency_type_id:'USD',original_amount:'100',payment:'1000',exchange_rate:'10'};
    const ctx={...mixin.data(),fiscalPaymentsApplicable:true,showDialog:true,form:{document_type_id:'01',currency_type_id:'VES',
        exchange_rate_sale:'10',total_payable:'1000',payments:[row]},$set:(target,key,value)=>target[key]=value,
        $http:{post:()=>response.promise}};
    for(const [key,fn] of Object.entries(mixin.methods))ctx[key]=fn.bind(ctx);
    const pending=ctx.refreshFiscalQuote(ctx.form.payments);ctx.showDialog=false;
    response.resolve({data:{data:{payments:[{igtf_status:'subject',tax_amount:'3.00'}]}}});
    assert.equal(await pending,null);assert.equal(row.quoted_tax_amount,undefined);assert.equal(ctx.igtfQuote,null);
});

test('an already populated checkout displays a quote on creation', async () => {
    const {load,settings}=runtime(), mixin=load('resources/js/mixins/fiscal-payment-settings.js').default;
    settings.setIgtfSettings({enabled:true,rate:'3.00'});
    const row={date_of_payment:'2026-09-10',payment_method_type_id:'10',currency_type_id:'USD',original_amount:'100',payment:'1000',exchange_rate:'10'};
    let calls=0;
    const http={get:async()=>({data:{data:{enabled:true,rate:'3.00'}}}),post:async()=>{calls++;
        return {data:{data:{payments:[{igtf_status:'subject',tax_amount:'3.00'}]}}}}};
    const instance=new Vue({mixins:[mixin],beforeCreate(){this.$http=http},data:()=>({form:{document_type_id:'01',currency_type_id:'VES',
        exchange_rate_sale:'10',total_payable:'1000',payments:[row]}})});
    await new Promise(resolve=>setImmediate(resolve));await Vue.nextTick();
    assert.ok(calls>0);assert.equal(instance.igtfQuote.payments[0].tax_amount,'3.00');
    assert.equal(row.quoted_tax_amount,'3.00');instance.$destroy();
});
