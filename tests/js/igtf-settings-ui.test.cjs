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
            plugins:['@babel/plugin-transform-modules-commonjs']}).code, {exports, headers_token:{}, require(name) {
            if (name === 'vue') return Vue;
            if (name === 'vuex') return {mapActions:()=>({}),mapState:()=>({})};
            if (name === '@mixins/fiscal-payment-settings') return load('resources/js/mixins/fiscal-payment-settings.js');
            if (name.includes('exchange-rate-math')) return load('resources/js/helpers/exchange-rate-math.js');
            if (name.includes('payment-classification')) return load('resources/js/helpers/payment-classification.js');
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
        assert.match(s.fiscalPaymentProblem([row]),/borrador conserva datos IGTF/);
        assert.equal(JSON.stringify(row),before);
        assert.equal(s.fiscalPaymentProblem([{...row,id:12}]),'');
    }
    assert.equal(s.fiscalPaymentProblem([{payment:'100',igtf_status:'not_applicable'}]),'');
    for (const origin of [{receipt_parent_id:5},{receipt_origin:{id:5}}, {payment_method_snapshot:{receipt_origin:{id:5}}},
        {source_sale_note_payment_id:5},{source_quotation_payment_id:5},{source_technical_service_payment_id:5},{_reused_receipt:true}]) {
        assert.equal(s.fiscalPaymentProblem([{payment:'100',igtf_status:'subject',...origin}]),'');
    }
});

test('the rendered editor hides only IGTF controls, keeping currency and intermediation', () => {
    const {load}=runtime(), file='resources/js/views/tenant/documents/partials/igtf_fields.vue';
    const component=load(file).default, parsed=compiler.parseComponent(fs.readFileSync(file,'utf8'));
    const render=compiler.compileToFunctions(parsed.template.content);
    function texts(node) {return (node.text||'')+(node.children||[]).map(texts).join('')}
    const instance=new Vue({...component,...render,propsData:{payment:{payment:'100',igtf_status:'exempt'},igtfEnabled:false}});
    const hidden=texts(instance._render());
    assert.match(hidden,/Moneda efectivamente recibida/); assert.match(hidden,/Intermediación del cobro/);
    assert.doesNotMatch(hidden,/Clasificación IGTF|Excepción con autorización administrativa|Propuesta:/);
    instance.igtfEnabled=true;
    const visible=texts(instance._render());
    assert.match(visible,/Clasificación IGTF/);
    // Text in option labels and exception placeholders is carried by VNode attributes.
    function controls(node) {return [node,...(node.children||[]).flatMap(controls)]}
    assert.ok(controls(instance._render()).some(node=>node.data?.attrs?.placeholder==='Fundamento de excepción'));
    instance.$destroy();
});

test('reopening refreshes the proposal even when the enabled switch has not changed', async () => {
    const {load}=runtime(), component=load('resources/js/views/tenant/documents/partials/igtf_fields.vue').default;
    let evaluations=0;
    const instance=new Vue({...component,methods:{...component.methods,evaluate:()=>evaluations++},
        propsData:{payment:{payment:'100'},igtfEnabled:true,settingsVersion:1}});
    instance.settingsVersion=2;await Vue.nextTick();assert.equal(evaluations,1);
    instance.igtfEnabled=false;instance.settingsVersion=3;await Vue.nextTick();assert.equal(evaluations,2);
    instance.$destroy();
});

test('disabled IGTF does not silently classify a payment as subject; enabled IGTF still uses the server proposal', async () => {
    const {load}=runtime(), component=load('resources/js/views/tenant/documents/partials/igtf_fields.vue').default;
    const ctx={payment:{payment:'100',igtf_status:'not_applicable'},documentCurrency:'USD',igtfEnabled:false,sequence:0,
        $set:(o,k,v)=>o[k]=v,$http:{post:async()=>({data:{data:{payment:{igtf_status:'subject'}}}})}};
    await component.methods.evaluate.call(ctx); assert.equal(ctx.payment.igtf_status,'not_applicable');
    ctx.igtfEnabled=true; await component.methods.evaluate.call(ctx); assert.equal(ctx.payment.igtf_status,'subject');
});

test('a same-currency amount stays current even while its proposal request is pending', async () => {
    const {load}=runtime(), component=load('resources/js/views/tenant/documents/partials/igtf_fields.vue').default,response=deferred();
    const ctx={payment:{payment:'100',original_amount:'100',currency_type_id:'USD'},documentCurrency:'USD',sequence:0,lastApplied:null,
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
        component.methods.changeCurrency.call(ctx,'USD');
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

test('a proposal for another document or removed payment cannot change the current payment', async () => {
    const {load}=runtime(), component=load('resources/js/views/tenant/documents/partials/igtf_fields.vue').default, response=deferred();
    const old={payment:'100',igtf_status:'not_applicable'},ctx={payment:old,documentCurrency:'USD',igtfEnabled:true,sequence:0,
        $set:(o,k,v)=>o[k]=v,$http:{post:()=>response.promise}};
    const request=component.methods.evaluate.call(ctx);
    ctx.payment={payment:'50',igtf_status:'not_applicable'};
    response.resolve({data:{data:{payment:{igtf_status:'subject'}}}});await request;
    assert.equal(ctx.payment.igtf_status,'not_applicable');assert.equal(old.igtf_status,'not_applicable');
});

test('a changed document context invalidates a proposal before Vue flushes its watchers', async () => {
    const {load}=runtime(), component=load('resources/js/views/tenant/documents/partials/igtf_fields.vue').default,response=deferred();
    const ctx={payment:{payment:'100',igtf_status:'not_applicable'},documentId:1,documentCurrency:'USD',igtfEnabled:true,sequence:0,
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
    const row={payment:'100',currency_type_id:'USD',original_amount:'10',exchange_rate:'10.00000000',exchange_rate_source:'manual',
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
