const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const babel=require('@babel/core');
const compiler=require('vue-template-compiler');
let key=0;
function component(file) {
    const parsed=compiler.parseComponent(fs.readFileSync(file,'utf8'));
    assert.deepEqual(compiler.compile(parsed.template.content).errors,[]);
    const module={exports:{}};
    vm.runInNewContext(babel.transformSync(parsed.script.content,{configFile:false,babelrc:false,
        plugins:['@babel/plugin-transform-modules-commonjs']}).code,{module,exports:module.exports,
        moment:()=>({format:()=> '2026-10-13'}),require:path=>path.includes('fiscal-note') ? {noteRequestId:()=>`request-${++key}`} : {}});
    return module.exports.default;
}
const note=component('resources/js/views/tenant/documents/note.vue');
const operations=component('resources/js/views/tenant/documents/partials/note-operations.vue');
function bind(c,data) { for(const [name,method] of Object.entries(c.methods))if(typeof method==='function')data[name]=method.bind(data);return data; }
function deferred() {let resolve,reject;const promise=new Promise((a,b)=>{resolve=a;reject=b;});return {promise,resolve,reject};}
test('double click and a lost save response preserve the creation UUID',async()=>{
    const network=deferred();let calls=0;
    const state=bind(note,{form:{note_creation_key:'original-uuid'},busy:false,errors:{},$message:{error(){}},
        $http:{post:()=>{calls++;return network.promise;}}});
    const first=state.submit();await state.submit();assert.equal(calls,1);
    network.reject(new Error('response lost'));await first;
    assert.equal(state.form.note_creation_key,'original-uuid');assert.equal(state.busy,false);
});
test('a refund timeout preserves its UUID and releases the action',async()=>{
    const state=bind(operations,{busy:false,sequence:1,refundForm:{operation_key:'refund-uuid'},documentId:4,
        $http:{post:()=>Promise.reject(new Error('response lost'))}});
    await state.refund();assert.equal(state.refundForm.operation_key,'refund-uuid');assert.equal(state.busy,false);
});
test('a response for a previous note cannot overwrite the newly selected note',async()=>{
    const network=deferred();let updates=0;
    const state=bind(operations,{busy:false,sequence:1,refunds:{marker:'new note'},$http:{post:()=>network.promise},
        $eventHub:{$emit(){updates++;}},$emit(){updates++;}});
    const first=state.mutate('/documents/4/refunds',{operation_key:'stable-uuid'});state.sequence=2;
    network.resolve({data:{refunds:{marker:'old note'},adjustments:{},invoice_id:1}});
    assert.equal(await first,false);assert.equal(state.refunds.marker,'new note');assert.equal(updates,0);
});
test('switching notes clears the previous receipt, adjustment and reversal keys',()=>{
    const state={sequence:1,showDialog:false,refundForm:{operation_key:'old'},adjustmentForm:{operation_key:'old'},
        reverseKeys:{1:'old'},selectedSource:'IVA-1',showRetention:true,replacementEndpoint:'old'};
    operations.watch.documentId.call(state);
    assert.equal(Object.keys(state.refundForm).length,0);assert.equal(Object.keys(state.reverseKeys).length,0);
    assert.equal(state.replacementEndpoint,null);assert.equal(state.showRetention,false);
});

const cashPayments=component('resources/js/views/tenant/documents/partials/note-payments.vue');
test('cash debit payment rows start empty and require a permitted explicit collection',()=>{
    const state=bind(cashPayments,{payments:[],canRegister:false,currency:'VES',rate:'50.12345678',date:'2026-10-13'});
    state.add();assert.equal(state.payments.length,0);state.canRegister=true;state.add();
    assert.equal(state.payments.length,1);assert.equal(state.payments[0].payment,'');assert.equal(state.payments[0].payment_destination_id,null);
    assert.equal(state.payments[0].exchange_rate,'50.12345678');assert.ok(state.payments[0].operation_key);
    const payment=state.payments[0];payment.payment='10.50';state.setPrincipal(payment);assert.equal(payment.original_amount,'10.50');
    payment.currency_type_id='USD';payment.original_amount='1.25';state.setPrincipal(payment);assert.equal(payment.original_amount,'1.25');
});
test('cash collections exclude credit declarations and changing condition clears unsaved cash rows',()=>{
    assert.deepEqual(Array.from(cashPayments.computed.realMethods.call({methods:[{id:'09',is_credit:1},{id:'01',is_credit:0}]}),r=>r.id),['01']);
    const state={form:{payments:[{operation_key:'cash-row'}]}};note.watch['form.payment_condition_id'].call(state,'02','01');assert.equal(state.form.payments.length,0);
});
