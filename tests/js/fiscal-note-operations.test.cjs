const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const babel=require('@babel/core');
const compiler=require('vue-template-compiler');
const path=require('node:path');
const load=require('./helpers/load-module.cjs');
const fiscalNote=load(path.resolve('resources/js/helpers/fiscal-note.js'));
const documentSeries=load(path.resolve('resources/js/helpers/document-series.js'));
const exactMath=load(path.resolve('resources/js/helpers/exchange-rate-math.js'));
const {calculateRowItem}=load(path.resolve('resources/js/helpers/functions.js'));
let key=0;
function component(file) {
    const parsed=compiler.parseComponent(fs.readFileSync(file,'utf8'));
    assert.deepEqual(compiler.compile(parsed.template.content).errors,[]);
    const module={exports:{}};
    vm.runInNewContext(babel.transformSync(parsed.script.content,{configFile:false,babelrc:false,
        plugins:['@babel/plugin-transform-modules-commonjs']}).code,{module,exports:module.exports,
        moment:()=>({format:()=> '2026-10-13'}),require:name=>{
            if(name.includes('fiscal-note')) return {...fiscalNote,noteRequestId:()=>`request-${++key}`};
            if(name==='@helpers/document-series') return documentSeries;
            if(name.endsWith('/exchange-rate-math')) return exactMath;
            return {};
        }});
    return module.exports.default;
}
const note=component('resources/js/views/tenant/documents/note.vue');
const operations=component('resources/js/views/tenant/documents/partials/note-operations.vue');
function bind(c,data) { for(const [name,method] of Object.entries(c.methods))if(typeof method==='function')data[name]=method.bind(data);return data; }
function deferred() {let resolve,reject;const promise=new Promise((a,b)=>{resolve=a;reject=b;});return {promise,resolve,reject};}

test('credit and debit notes select their blank series automatically and show mixed choices', () => {
    const credit = {id: 7, document_type_id: '07', establishment_id: 1, number: ''};
    const debit = {id: 8, document_type_id: '08', establishment_id: 1, number: ''};
    const state = bind(note, {
        document: {establishment_id: 1},
        tables: {series: [credit, debit, {...credit, id: 9, establishment_id: 2, number: 'NC02'}]},
        form: {document_type_id: '07'},
    });
    state.changeDate = () => {};
    Object.defineProperties(state, {
        series: {get: note.computed.series.bind(state)},
        credit: {get: note.computed.credit.bind(state)},
    });

    state.changeType();
    assert.equal(state.form.series_id, 7);
    assert.equal(note.computed.showSeriesSelector.call(state), false);

    state.form.document_type_id = '08';
    state.changeType();
    assert.equal(state.form.series_id, 8);
    assert.equal(note.computed.showSeriesSelector.call(state), false);

    state.tables.series.push({...debit, id: 10, number: 'ND01'});
    assert.equal(note.computed.showSeriesSelector.call(state), true);
    assert.equal(state.form.series_id, 8);

    state.tables.series = [credit];
    state.changeType();
    assert.equal(state.form.series_id, null);
    assert.equal(note.computed.showSeriesSelector.call(state), false);
});

test('double click and a lost save response preserve the creation UUID',async()=>{
    const network=deferred();let calls=0;
    const state=bind(note,{form:{note_creation_key:'original-uuid'},busy:false,errors:{},$message:{error(){}},
        $http:{post:()=>{calls++;return network.promise;}}});
    const first=state.submit();await state.submit();assert.equal(calls,1);
    network.reject(new Error('response lost'));await first;
    assert.equal(state.form.note_creation_key,'original-uuid');assert.equal(state.busy,false);
});
test('a refund timeout preserves its UUID and releases the action',async()=>{
    const state=bind(operations,{busy:false,sequence:1,showDialog:true,refundForm:{operation_key:'refund-uuid'},documentId:4,
        $http:{post:()=>Promise.reject(new Error('response lost'))}});
    await state.refund();assert.equal(state.refundForm.operation_key,'refund-uuid');assert.equal(state.busy,false);
});
test('a response for a previous note cannot overwrite the newly selected note',async()=>{
    const network=deferred();let updates=0;
    const state=bind(operations,{busy:false,sequence:1,showDialog:true,documentId:4,refunds:{marker:'new note'},$http:{post:()=>network.promise},
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

// ######## INICIO CONSULTAS VALIDAS DE DEVOLUCIONES NC ########
test('refunds never load or mutate a missing document or a closed dialog',async()=>{
    for(const [documentId,showDialog] of [[null,true],[undefined,true],[4,false]]){
        const state=bind(operations,{...operations.data(),documentId,showDialog,
            $http:{get:()=>assert.fail('Invalid refund GET'),post:()=>assert.fail('Invalid refund POST')}});
        await state.load();
        assert.equal(await state.mutate(`/documents/${documentId}/refunds`,{}),false);
        assert.equal(state.busy,false);
        assert.equal(state.error,'');
    }
});
test('model and server failures show a readable message without internal details',async()=>{
    for(const status of [404,500]){
        const state=bind(operations,{...operations.data(),documentId:4,showDialog:true,
            $http:{get:async()=>{throw {response:{status,data:{message:'No query results for model [App\\Models\\Tenant\\Document] null',trace:['private trace']}}};}}});
        await state.load();
        assert.ok(state.error);
        assert.doesNotMatch(state.error,/query results|Models|Document|private trace/);
        assert.equal(state.busy,false);
    }
});
test('refund errors keep business validation messages in both response formats',()=>{
    const state=bind(operations,{});
    for(const data of [{errors:{original_amount:['La devolución supera el importe disponible.']}},{original_amount:['La devolución supera el importe disponible.']}]){
        assert.equal(state.message({response:{status:422,data}}),'La devolución supera el importe disponible.');
    }
    assert.equal(state.message({response:{status:409,data:{message:'La clave pertenece a otra operación.'}}}),'La clave pertenece a otra operación.');
});
test('a registered refund stays visible after notifying the parent',async()=>{
    let changed=0;
    const state=bind(operations,{...operations.data(),showDialog:true,documentId:4,
        $http:{post:async()=>({data:{invoice_id:1,refunds:{records:[{id:9,amount:'400'}]},adjustments:{}}})},
        $eventHub:{$emit(){}},$emit(event){if(event==='changed')changed++;}});
    assert.equal(await state.mutate('/documents/4/refunds',{operation_key:'stable'}),true);
    assert.equal(state.refunds.records[0].id,9);
    assert.equal(state.documentId,4);
    assert.equal(state.error,'');
    assert.equal(changed,1);
});
// ######## FIN CONSULTAS VALIDAS DE DEVOLUCIONES NC ########

test('debit creation has no payment selector or initial collection component',()=>{
    const template=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/note.vue','utf8')).template.content;
    assert.doesNotMatch(template,/Condición de pago|note-payments|Agregar cobro real/);
});
test('switching note types resets initial payments and selects credit for debits',()=>{
    for(const credit of [false,true,false]) {
        const state=bind(note,{credit,form:{payments:[{payment:'10'}]},series:[{id:5}],changeDate(){}});
        state.changeDate=()=>{};
        state.changeType();
        assert.equal(state.form.payment_condition_id,credit?null:'02');
        assert.equal(state.form.payments.length,0);
    }
});
test('submit always posts debits on credit without stale initial collections',async()=>{
    let posted;
    const state=bind(note,{credit:false,busy:false,form:{note_creation_key:'stable',payment_condition_id:'01',payments:[{payment:'10'}]},
        $message:{error(){}},$http:{post:async(url,data)=>{posted=JSON.parse(JSON.stringify(data));throw new Error('response lost');}}});
    await state.submit();
    assert.equal(posted.payment_condition_id,'02');assert.deepEqual(posted.payments,[]);
    assert.equal(state.form.note_creation_key,'stable');assert.equal(state.busy,false);
});

test('debit service uses the calculated amount of all units and sends one fiscal unit', async () => {
    let posted;
    const state=bind(note,{credit:false,busy:false,form:{document_type_id:'08',items:[]},
        $message:{error(){}},$http:{post:async(url,data)=>{posted=JSON.parse(JSON.stringify(data));throw new Error('response lost');}}});
    const row=calculateRowItem({quantity:'3',item:{id:1,unit_type_id:'SERV',currency_type_id:'VES',unit_price:'10'},
        affectation_igv_type_id:'20',discounts:[],charges:[]},'VES','1');
    state.addService(row);
    await state.submit();
    assert.equal(posted.items[0].adjustment_amount,'30');
    assert.equal(posted.items[0].quantity,1);
    assert.equal(posted.items[0].item_id,row.item_id);
});

test('debit service includes the selected charge in the adjustment amount', () => {
    const state=bind(note,{form:{document_type_id:'08',items:[]},$message:{error:message=>assert.fail(message)}});
    const row=calculateRowItem({quantity:'1',item:{id:1,unit_type_id:'SERV',currency_type_id:'VES',unit_price:'116'},
        affectation_igv_type_id:'10',discounts:[],charges:[{charge_type_id:'50',factor:'0.1'}]},'VES','1','0.16');
    assert.equal(row.total_igv,'16');
    state.addService(row);
    assert.equal(state.form.items[0].adjustment_amount,'126');
});

test('debit service submits the original fraction until the amount is edited', async () => {
    const posts=[];
    const state=bind(note,{credit:false,busy:false,form:{document_type_id:'08',items:[]},
        $message:{error(){}},$http:{post:async(url,data)=>{posts.push(JSON.parse(JSON.stringify(data)));throw new Error('response lost');}}});
    const row=calculateRowItem({quantity:'1',item:{id:1,unit_type_id:'SERV',currency_type_id:'VES',unit_price:'1'},
        affectation_igv_type_id:'20',discounts:[],charges:[]},'USD','3');
    state.addService(row);
    const adjustment=state.form.items[0];
    await state.submit();
    assert.equal(posts[0].items[0].adjustment_amount,'0.333333333333');
    assert.deepEqual(posts[0].items[0].adjustment_amount_exact,{n:'1',d:'3'});

    state.changeAdjustmentAmount(adjustment,'0.50');
    await state.submit();
    assert.equal(posts[1].items[0].adjustment_amount,'0.50');
    assert.equal(posts[1].items[0].adjustment_amount_exact,null);

    state.changeAdjustmentAmount(adjustment,'0.333333333333');
    await state.submit();
    assert.equal(posts[2].items[0].adjustment_amount_exact,null);
});
