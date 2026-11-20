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
vm.runInNewContext(babel.transformSync(parsed.script.content,{configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']}).code,{exports:exportsObject, require:name => name.includes('payment-classification') || name.includes('tendered-receipt') ? require('./helpers/load-module.cjs')(require('node:path').resolve('resources/js/helpers/'+(name.includes('tendered-receipt') ? 'tendered-receipt' : 'payment-classification')+'.js')) : mathExports, crypto:{randomUUID:()=> 'operation-uuid'}});
const component = exportsObject.default;
test('total tendered includes IGTF and preserves the customer entry through reactive recalculations', async () => {
    const Vue=require('vue');
    const currencies = process.env.PRO9_TEST_INVOICES_VES_ONLY === '1' ? ['VES'] : ['USD', 'VES'];
    for (const documentCurrency of currencies) {
        const payment=Vue.observable({payment:documentCurrency==='USD' ? '60' : '600',original_amount:'60',
            currency_type_id:'USD',payment_method_type_id:'usd',exchange_rate:'10',igtf_status:'subject'});
        const view=new Vue({...component,propsData:{payment,documentCurrency,documentRate:'10',useDocumentRate:true,
            totalTendered:true,igtfEnabled:true,igtfRate:'3',paymentMethods:[{id:'usd',collection_kind:'cash',currency_type_id:'USD'}]}});
        const uid=view._uid;
        await Vue.nextTick();assert.equal(payment.tendered_amount,'61.80');
        Vue.set(payment,'_tendered_edited',true);payment.tendered_amount='70';
        await Vue.nextTick();await Vue.nextTick();
        assert.equal(payment.original_amount,'67.96');
        assert.equal(payment.payment,documentCurrency==='USD' ? '67.96' : '679.60');
        assert.equal(payment.tendered_amount,'70');
        payment.tendered_amount='61.80';await Vue.nextTick();await Vue.nextTick();
        assert.equal(payment.original_amount,'60.00');
        payment.tendered_amount='30.90';await Vue.nextTick();await Vue.nextTick();
        assert.equal(payment.original_amount,'30.00');
        payment.igtf_status='not_applicable';await Vue.nextTick();await Vue.nextTick();
        assert.equal(payment.original_amount,'30.90');assert.equal(payment.tendered_amount,'30.90');
        payment.igtf_status='subject';view.igtfRate='4';await Vue.nextTick();await Vue.nextTick();
        assert.equal(payment.original_amount,'29.71');assert.equal(payment.tendered_amount,'30.90');
        assert.equal(view._uid,uid);view.$destroy();
    }
});

test('inclusive cash allocates exact tax and principal once without losing a delivered cent', () => {
    const {principalFromTendered,splitTendered}=require('./helpers/load-module.cjs')(require('node:path').resolve('resources/js/helpers/tendered-receipt.js'));
    for (const [gross,net,tax] of [['40','38.83','1.17'],['70','67.96','2.04'],['61.80','60.00','1.80'],['30.90','30.00','0.90'],['0.17','0.17','0.00'],['0.10','0.10','0.00']]) {
        assert.equal(principalFromTendered(gross,'3'),net);
        const split=splitTendered(gross,'3');
        assert.equal(split.tax,tax);
        assert.equal(mathExports.exactAmount(split.principal).plus(split.tax).compare(gross),0);
        assert.equal(mathExports.exactAmount(split.principalExact).times('3').dividedBy('100').compare(split.taxExact),0);
    }
    for (const rate of ['3','4','100']) {
        for (let cents=1;cents<=206;cents++) {
            const gross=mathExports.exactAmount(String(cents)).dividedBy('100').final(2), split=splitTendered(gross,rate);
            assert.equal(mathExports.exactAmount(split.principal).plus(split.tax).compare(gross),0);
            assert.equal(split.tax,mathExports.exactAmount(split.taxExact).final(2),'tax half-cent ties remain HALF_UP');
        }
    }
    assert.throws(()=>principalFromTendered('-1','3'));
});

test('changing the payment currency converts the delivered total and reevaluates its included IGTF', async () => {
    const Vue=require('vue');
    const payment=Vue.observable({payment:'600',original_amount:'60',currency_type_id:'USD',exchange_rate:'10',
        payment_method_type_id:'usd',igtf_status:'subject'});
    const view=new Vue({...component,propsData:{payment,documentCurrency:'VES',documentRate:'10',useDocumentRate:true,
        totalTendered:true,igtfEnabled:true,igtfRate:'3',paymentMethods:[
            {id:'usd',collection_kind:'cash',currency_type_id:'USD'}, {id:'ves',collection_kind:'cash',currency_type_id:'VES'}]},
        beforeCreate() { this.$http={post:async (_,input)=>({data:{data:{payment:{igtf_status:input.currency_type_id==='USD' ? 'subject' : 'not_applicable'}}}})}; }});
    Vue.set(payment,'_tendered_edited',true);payment.tendered_amount='70';
    for (let n=0;n<4;n++) await Vue.nextTick();
    payment.payment_method_type_id='ves';
    for (let n=0;n<6;n++) await Vue.nextTick();
    assert.equal(payment.tendered_amount,'700.00');assert.equal(payment.original_amount,'700.00');
    assert.equal(payment.igtf_status,'not_applicable');assert.equal(payment.payment,'700.00');
    payment.payment_method_type_id='usd';
    for (let n=0;n<6;n++) await Vue.nextTick();
    assert.equal(payment.tendered_amount,'70.00');assert.equal(payment.original_amount,'67.96');
    assert.equal(payment.igtf_status,'subject');assert.equal(payment.payment,'679.60');view.$destroy();
});
// ######## INICIO RETIRADA DE EXCEPCION ADMINISTRATIVA IGTF ########
test('old exception drafts are reevaluated automatically while their receipt identity stays unchanged', async () => {
    const Vue = require('vue');
    const payment = Vue.observable({payment:'400.00', original_amount:'10.00', currency_type_id:'USD', exchange_rate:'40',
        operation_key:'old-key', igtf_status:'exempt', exemption_reason:'Motivo', exception_basis:'Fundamento', quoted_tax_amount:'0.00'});
    const instance = new Vue({...component, propsData:{payment, igtfEnabled:true, documentCurrency:'VES'},
        beforeCreate() { this.$http = {post:async () => ({data:{data:{payment:{igtf_status:'subject'}}}})}; }});
    await Promise.resolve(); await Vue.nextTick();
    assert.equal(payment.igtf_status, 'subject');
    assert.equal(payment.exception_basis, undefined); assert.equal(payment.exemption_reason, undefined);
    assert.equal(payment.quoted_tax_amount, undefined); assert.equal(payment.operation_key, 'old-key');
    assert.equal(payment.original_amount, '10.00'); assert.equal(payment.payment, '400.00');
    assert.equal(instance.setExemption, undefined);
    instance.$destroy();
});

test('destroying a fiscal controller prevents its pending response from changing the receipt', async () => {
    const Vue = require('vue');
    let resolve;
    const pending = new Promise(done => { resolve = done; });
    const payment = Vue.observable({payment:'400', original_amount:'10', currency_type_id:'USD', exchange_rate:'40', igtf_status:'not_applicable'});
    const instance = new Vue({...component, propsData:{payment, igtfEnabled:true, documentCurrency:'VES'},
        beforeCreate() { this.$http = {post:() => pending}; }});
    instance.$destroy();
    resolve({data:{data:{issuer_spe:true, payment:{igtf_status:'subject'}}}});
    await Promise.resolve(); await Vue.nextTick();
    assert.equal(payment.igtf_status, 'not_applicable'); assert.equal(instance.proposal, null);
});
// ######## FIN RETIRADA DE EXCEPCION ADMINISTRATIVA IGTF ########
// ######## INICIO CAMPOS DE COBRO SIMPLIFICADOS ########
test('method changes in the same currency update intermediation without replacing received money', async () => {
    const Vue=require('vue')
    const payment=Vue.observable({payment:'100',payment_method_type_id:'cash-usd',currency_type_id:'USD',original_amount:'10',exchange_rate:'10'})
    const methods=[{id:'cash-usd',collection_kind:'cash',currency_type_id:'USD'},{id:'bank-usd',collection_kind:'account',currency_type_id:'USD'}]
    const instance=new Vue({...component,propsData:{payment,paymentMethods:methods,documentCurrency:'VES',documentRate:'10'}})
    assert.equal(payment.financial_intermediation,'none')
    payment.payment_method_type_id='bank-usd'; await Vue.nextTick()
    assert.equal(payment.financial_intermediation,'financial_institution')
    assert.equal(payment.original_amount,'10');assert.equal(payment.payment,'100.00')
    payment.payment_method_type_id='cash-usd'; await Vue.nextTick()
    assert.equal(payment.financial_intermediation,'none')
    instance.$destroy()
})

test('late method catalogs determine receipt currency and intermediation', async () => {
    const Vue=require('vue'),payment={payment:'100',payment_method_type_id:'custom-usd'}
    const instance=new Vue({...component,propsData:{payment,paymentMethods:[],documentCurrency:'VES',documentRate:'10'}})
    instance.paymentMethods=[{id:'custom-usd',collection_kind:'account',currency_type_id:'USD'}]
    await Vue.nextTick()
    assert.equal(payment.currency_type_id,'USD');assert.equal(payment.original_amount,'10.00')
    assert.equal(payment.financial_intermediation,'financial_institution')
    instance.$destroy()
})

test('a new POS receipt uses the invoice BCV rate when switching from VES to USD', async () => {
    const Vue = require('vue')
    for (const panel of ['payment.vue', 'fast_payment.vue', 'fast_payment_garage.vue', 'multiple_payment.vue', 'multiple_payment_garage.vue']) {
        const template = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/pos/partials/' + panel, 'utf8')).template.content
        const field = template.match(/<igtf-payment-fields\b[\s\S]*?\/>/)?.[0]
        assert.match(field, /:document-rate="/, panel)
        assert.match(field, /:total-tendered="true"/, panel)
    }
    const rate = '10.12345678'
    const payment = Vue.observable({payment: '101.23', payment_method_type_id: 'ves'})
    const paymentMethods = [
        {id: 'ves', currency_type_id: 'VES', collection_kind: 'cash'},
        {id: 'usd', currency_type_id: 'USD', collection_kind: 'cash'},
        {id: 'usd-bank', currency_type_id: 'USD', collection_kind: 'account'},
    ]
    const instance = new Vue({...component, propsData: {payment, paymentMethods,
        documentCurrency: 'VES', documentRate: rate, totalTendered: true}})
    await Vue.nextTick()
    assert.equal(payment.currency_type_id, 'VES')
    payment.payment_method_type_id = 'usd'
    await Vue.nextTick()
    await Vue.nextTick()
    assert.equal(payment.exchange_rate, rate)
    assert.equal(payment.original_amount, '10.00')
    assert.equal(payment.payment, '101.23')

    payment.payment_method_type_id = 'usd-bank'
    await Vue.nextTick()
    assert.equal(payment.currency_type_id, 'USD')
    assert.equal(payment.exchange_rate, rate)
    assert.equal(payment.original_amount, '10.00')
    assert.equal(payment.payment, '101.23')
    instance.$destroy()
})

test('invoice receipts follow the header rate while retaining the received amount', async () => {
    const Vue=require('vue'),payment={payment:'50',payment_method_type_id:'cash',currency_type_id:'USD',original_amount:'10',exchange_rate:'5'}
    const instance=new Vue({...component,propsData:{payment,useDocumentRate:true,
        paymentMethods:[{id:'cash',collection_kind:'cash',currency_type_id:'USD'}],documentCurrency:'VES',documentRate:'10'}})
    await Vue.nextTick()
    assert.equal(payment.exchange_rate,'10');assert.equal(payment.original_amount,'10');assert.equal(payment.payment,'100.00')
    instance.documentRate='20'; await Vue.nextTick()
    assert.equal(payment.exchange_rate,'20');assert.equal(payment.original_amount,'10');assert.equal(payment.payment,'200.00')
    instance.documentRate='0';await Vue.nextTick()
    assert.match(instance.error,/tasa VES por USD válida/)
    assert.equal(payment.exchange_rate,'0');assert.equal(payment.original_amount,'10')
    assert.match(parsed.template.content,/v-if="!useDocumentRate/)
    assert.match(fs.readFileSync('resources/js/views/tenant/documents/invoice_generate.vue','utf8'),/:use-document-rate="true"/)
    instance.$destroy()
})

test('a proposal includes the method and cannot overwrite a different same-currency method', async () => {
    let resolve,payload
    const payment={payment:'100',currency_type_id:'USD',original_amount:'10',exchange_rate:'10',payment_method_type_id:'cash',financial_intermediation:'none'}
    const ctx={payment,sequence:0,documentCurrency:'VES',igtfEnabled:true,$set:(o,k,v)=>o[k]=v,
        $http:{post:(_url,body)=>{payload=body;return new Promise(done=>{resolve=done})}}}
    const pending=component.methods.evaluate.call(ctx)
    assert.equal(payload.payment_method_type_id,'cash')
    payment.payment_method_type_id='bank'
    resolve({data:{data:{payment:{igtf_status:'subject'}}}});await pending
    assert.equal(payment.igtf_status,undefined)
})

test('an invalid header rate cannot be cleared by a successful profile proposal', async () => {
    let requests=0
    const ctx={payment:{payment:'10'},useDocumentRate:true,hasDocumentRate:false,sequence:0,
        $http:{post:async()=>{requests++;return {data:{data:{}}}}}}
    await component.methods.evaluate.call(ctx)
    assert.equal(requests,0);assert.match(ctx.error,/tasa VES por USD válida/)
})

test('the rate input is hidden only when the receipt follows the invoice header', () => {
    const Vue=require('vue'),render=compiler.compileToFunctions(parsed.template.content)
    const text=node=>(node.text || '')+(node.children || []).map(text).join('')
    for (const useDocumentRate of [false,true]) {
        const instance=new Vue({...component,...render,propsData:{useDocumentRate,documentCurrency:'VES',documentRate:'10',
            payment:{payment:'100',currency_type_id:'USD',original_amount:'10',exchange_rate:'10'}}})
        const visible=text(instance._render())
        assert.equal(visible.includes('Tasa VES por USD'),!useDocumentRate)
        assert.match(visible,/sin IGTF \(USD\)/)
        assert.doesNotMatch(visible,/Moneda efectivamente recibida|Intermediación del cobro/)
        instance.$destroy()
    }
})

test('all original-receipt conversion forms compile without an intermediation selector', () => {
    for (const path of ['resources/js/views/tenant/documents/invoice_generate.vue',
        'resources/js/views/tenant/sale_notes/partials/option_documents.vue','resources/js/views/tenant/quotations/partials/options.vue',
        'modules/Sale/Resources/assets/js/views/technical-services/partials/options.vue',
        'modules/Suscription/Resources/assets/js/payment_receipt/partials/option_documents.vue',
        'modules/FullSuscription/Resources/assets/js/payment_receipt/partials/option_documents.vue']) {
        const view=compiler.parseComponent(fs.readFileSync(path,'utf8'))
        assert.deepEqual(compiler.compile(view.template.content).errors,[],path)
        assert.doesNotMatch(view.template.content,/Intermediación del cobro original|v-model="row.financial_intermediation"/,path)
    }
})
// ######## FIN CAMPOS DE COBRO SIMPLIFICADOS ########
test('fiscal payment exposes a rate when the document lacks one',()=>{
    assert.equal(component.computed.hasDocumentRate.call({documentRate:null}),false);
    assert.equal(component.computed.hasDocumentRate.call({documentRate:'0'}),false);
    assert.equal(component.computed.hasDocumentRate.call({documentRate:'400'}),true);
    assert.match(parsed.template.content,/payment.currency_type_id !== documentCurrency \|\| !hasDocumentRate/);
});
function initialize(payment) {const ctx={...component.methods,payment,paymentMethods:[],$set:(obj,key,value)=>{obj[key]=value}};component.created.call(ctx);return payment;}
test('legacy payment rows default to no IGTF without adding a currency selector',()=>{
    const payment = initialize({payment:100});
    assert.equal(payment.igtf_status,'not_applicable');assert.equal(payment.exemption_reason,undefined);
    assert.equal(Object.hasOwn(payment,'currency_type_id'),false);
});
test('an unregistered old exception is retired before automatic evaluation',()=>{
    const payment = initialize({payment:100,igtf_status:'exempt',exemption_reason:'Comprobante aportado'});
    assert.equal(payment.igtf_status,'not_applicable');assert.equal(payment.exemption_reason,undefined);
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
    payment.original_amount='1.005';payment.exchange_rate='2'
    component.methods.convertPrincipal.call(ctx)
    assert.equal(payment.payment,'2.02')
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
    const row={payment_method_type_id:'10',payment:'10',original_amount:'1',currency_type_id:'USD',exchange_rate:'10',errors:{}}
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
    const row={payment_method_type_id:'14',payment:'10.01',original_amount:'10.006',currency_type_id:'VES',exchange_rate:'1',errors:{}}
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
