const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync('resources/js/views/tenant/dispatches/partials/fiscal-status.vue','utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
const actionSandbox={module:{exports:{}}};
vm.runInNewContext(fs.readFileSync('resources/js/views/tenant/dispatches/partials/fiscal-action.js','utf8').replace('export default async function performFiscalAction','module.exports = async function performFiscalAction'),actionSandbox);
const sandbox={module:{exports:{}},performFiscalAction:actionSandbox.module.exports};vm.runInNewContext(source.replace(/^import .*;$/gm,'').replace('export default','module.exports ='),sandbox);
const component=sandbox.module.exports;
function context(http) {
    return {record:{id:9},$http:http,$set:(o,k,v)=>o[k]=v,$message:{info(){},error(message){this.last=message;}},$emit(){}};
}
test('fiscal action posts once and refreshes emission and PDF availability',async()=>{
    const calls=[];const record={fiscal_emission:{status:'confirmed',description:'Confirmado'},pdf_downloads:{hka_a4:{available:true}}};
    const ctx=context({post:async url=>calls.push(url),get:async url=>{calls.push(url);return {data:{data:record}};}});
    await component.methods.perform.call(ctx,'query-hka');
    assert.deepEqual(calls,['/dispatches/9/query-hka','/dispatches/record/9']);assert.equal(ctx.record.fiscal_emission.status,'confirmed');assert.equal(ctx.record.pdf_downloads.hka_a4.available,true);assert.equal(ctx.record.hka_action_busy,false);
});
test('double clicks and unsupported actions do not repeat mutations',async()=>{
    let release;let calls=0;const ctx=context({post:()=>{calls++;return new Promise(r=>release=r);},get:async()=>({data:{fiscal_emission:{description:'Pendiente'},pdf_downloads:{}}})});
    const first=component.methods.perform.call(ctx,'send-hka');
    assert.equal(component.computed.busy.call(ctx),true);
    await actionSandbox.module.exports.call({...ctx},ctx.record,'query-hka');
    await component.methods.perform.call(ctx,'send-hka');assert.equal(calls,1);release();await first;
    assert.equal(component.computed.busy.call(ctx),false);
    await component.methods.perform.call(ctx,'anular');assert.equal(calls,1);
});
test('network failures preserve the record and release the action guard',async()=>{
    const ctx=context({post:async()=>{throw new Error('Offline');}});const record=ctx.record;
    await component.methods.perform.call(ctx,'relate-hka');assert.equal(ctx.record,record);assert.equal(ctx.record.hka_action_busy,false);assert.match(ctx.$message.last,/orden sigue guardada/);
});

test('the list and finish dialog mount the fiscal status control in their compiled templates',()=>{
    const compiler=require('vue-template-compiler');
    for (const file of ['resources/js/views/tenant/dispatches/index.vue','resources/js/views/tenant/dispatches/partials/finish.vue','resources/js/views/tenant/dispatches/partials/fiscal-status.vue']) {
        const parsed=compiler.parseComponent(fs.readFileSync(file,'utf8'));const result=compiler.compile(parsed.template.content);
        assert.deepEqual(result.errors,[]);
        if (!file.endsWith('fiscal-status.vue')) assert.match(result.render,/dispatch-fiscal-status/);
    }
});

test('multiple-order conversion carries the authorized branch and correctly formatted references',async()=>{
    const stored={};const helper={};vm.runInNewContext(fs.readFileSync('resources/js/helpers/document-number.js','utf8').replace(/^export /gm,''),helper);
    const code=fs.readFileSync('resources/js/views/tenant/dispatches/ModalGenerateCPE.vue','utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
    const box={module:{exports:{}},documentNumberFull:helper.documentNumberFull,localStorage:{setItem:(k,v)=>stored[k]=v},window:{location:{}}};
    vm.runInNewContext(code.replace(/^import .*;$/gm,'').replace('export default','module.exports ='),box);
    const ctx={form:{selecteds:[9,10],client_id:5},dispatches:[{id:9,selected:true,series:'PRUEBA-OE',number:12},{id:10,selected:true,series:'',number:3}],clients:[{id:5,name:'Sintético'}],loading:false,
        $http:{post:async(url,data)=>{assert.equal(url,'/dispatches/items');assert.deepEqual(data.dispatches_id,[9,10]);return {data:{establishment_id:2,data:[{item_id:4,quantity:5}]}};}},onClose(){},axiosError(error){throw error;}};
    box.module.exports.methods.onFetchDispatchItems.call(ctx);await new Promise(resolve=>setImmediate(resolve));
    assert.deepEqual(JSON.parse(stored.dispatches),['PRUEBA-OE-00000012','00000003']);assert.equal(JSON.parse(stored.client).dispatch_establishment_id,2);
    assert.equal(JSON.parse(stored.items)[0].quantity,5);assert.equal(box.window.location.href,'/documents/create');assert.equal(ctx.loading,false);
});

test('the grouped delivery-order invoice keeps the original fraction and bigint identity', () => {
    const compiler = require('vue-template-compiler');
    const parser = require('@babel/parser');
    const generate = require('@babel/generator').default;
    const path = require('node:path');
    const load = require('./helpers/load-module.cjs');
    const math = load(path.resolve('resources/js/helpers/exchange-rate-math.js'));
    const functions = load(path.resolve('resources/js/helpers/functions.js'));
    const {recalculateInvoiceRow} = load(path.resolve('resources/js/helpers/invoice-row-edit.js'));
    const vue = fs.readFileSync('resources/js/views/tenant/documents/invoice_generate.vue', 'utf8');
    const script = compiler.parseComponent(vue).script.content;
    const component = parser.parse(script, {sourceType: 'module'}).program.body
        .find(node => node.type === 'ExportDefaultDeclaration').declaration;
    const methods = component.properties.find(node => node.key?.name === 'methods').value.properties;
    const relevant = methods.filter(node => ['calculateDispatchInvoiceRow', 'setItemFromResponse'].includes(node.key?.name));
    const invoice = vm.runInNewContext('({' + relevant.map(node => generate(node).code).join(',') + '})', {...math, ...functions});
    const id = '9007199254740993';
    const qty = {n: '2', d: '3'};
    const draft = invoice.calculateDispatchInvoiceRow.call({
        ...invoice, form: {currency_type_id: 'VES', exchange_rate_sale: '1'}, percentage_igv: '0.16',
    }, {id, sale_unit_price: '116', currency_type_id: 'VES', sale_affectation_igv_type_id: '10'}, [
        {item_id: '9007199254740992', quantity: '9'},
        {item_id: id, quantity: '0.666666666667', calculation_snapshot: math.exactSnapshot({quantity: qty})},
    ]);
    assert.equal(draft.item_id, id);
    assert.equal(draft.quantity, '0.666666666667');
    assert.equal(draft.quantity_input, null);
    assert.equal(math.exactValue(draft, 'quantity').compare(qty), 0);
    assert.equal(math.exactValue(draft, 'total').compare({n: '232', d: '3'}), 0);
    const currency = {currency_type_id: 'VES', exchange_rate_sale: '1', percentage_igv: '0.16'};
    const edited = recalculateInvoiceRow({...draft, quantity: '0.75'}, 'quantity', currency);
    assert.equal(math.exactValue(edited, 'quantity').compare({n: '3', d: '4'}), 0);
    const decimal = recalculateInvoiceRow({...edited, quantity: draft.quantity}, 'quantity', currency);
    assert.equal(decimal.quantity_input?.amount, '0.666666666667');
    assert.equal(decimal.quantity_input?.unit_price, '1');
    assert.equal(math.exactValue(decimal, 'quantity').compare('0.666666666667'), 0);
});

test('the actions menu keeps unavailable HKA options visible and prevents their requests',()=>{
    const Vue=require('vue');
    const compiler=require('vue-template-compiler');
    const list=fs.readFileSync('resources/js/views/tenant/dispatches/index.vue','utf8');
    const menu=list.match(/<el-dropdown-menu slot="dropdown">([\s\S]*?)<\/el-dropdown-menu>/)[1];
    const render=compiler.compileToFunctions(`<div>${menu}</div>`);
    const calls=[];
    const view=new Vue({
        ...render,
        data:()=>({row:{id:9,fiscal_emission:{status:'not_requested',can_query:false,diagnostic:'La conexión HKA no está configurada.'},pdf_downloads:{hka_a4:{available:false}}}}),
        beforeCreate(){this.$store={state:{config:{typeUser:'admin'}}};},
        methods:{performFiscalAction(...args){calls.push(args);}}
    });
    function nodes(node){return [node,...(node.children||[]).flatMap(nodes)];}
    const text=node=>node.text || (node.children||[]).map(text).join('');
    let items=nodes(view._render()).filter(node=>node.tag==='el-dropdown-item');
    for(const label of ['Consultar','Conciliar factura','PDF original']) {
        const item=items.find(node=>text(node).trim()===label);
        assert.ok(item,`${label} must remain visible`);
        assert.equal(item.data.attrs.disabled,true);
        if(item.data.nativeOn) item.data.nativeOn.click();
    }
    assert.deepEqual(calls,[]);
    assert.equal(nodes(items.find(node=>text(node).trim()==='PDF original')).some(node=>node.tag==='a'),false);
    view.row.fiscal_emission={status:'confirmed',can_query:true};
    view.row.reference_document_id=7;
    view.row.pdf_downloads.hka_a4={available:true,url:'/dispatches/test/download-hka-pdf/a4'};
    items=nodes(view._render()).filter(node=>node.tag==='el-dropdown-item');
    for(const label of ['Consultar','Conciliar factura','PDF original']) assert.equal(items.find(node=>text(node).trim()===label).data.attrs.disabled,false);
    view.$store.state.config.typeUser='seller';
    assert.equal(nodes(view._render()).some(node=>node.tag==='el-dropdown-item' && text(node).trim()==='Conciliar factura'),false);
});
