const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const compiler = require('vue-template-compiler');
const babel = require('@babel/core');
const source = fs.readFileSync('resources/js/views/tenant/establishments/partials/series.vue', 'utf8');
const parsed = compiler.parseComponent(source);
const exported = {};
vm.runInNewContext(babel.transformSync(parsed.script.content, {configFile:false, babelrc:false, plugins:['@babel/plugin-transform-modules-commonjs']}).code, {exports: exported});
const component = exported.default;
const methods = component.methods;
const types = [
    {key:'invoice', document_type_id:'01', prefix:'FF', category:'basic', sort_order:1},
    {key:'credit', document_type_id:'07', prefix:'FC', category:'basic', sort_order:2},
    {key:'dispatch', document_type_id:'09', prefix:'TT', category:'advanced', sort_order:3},
    {key:'sale_note', document_type_id:'80', prefix:'NV', category:'internal', sort_order:4},
];
function context() {
    const ctx = {establishmentId:1, seriesTypes:types, $message:{success(){},error(){}}, $emit(){}};
    for (const [name, fn] of Object.entries(methods)) ctx[name] = fn.bind(ctx);
    Object.assign(ctx, component.data.call(ctx));
    ctx.seriesTypes=types;
    for (const [name, fn] of Object.entries(component.computed)) Object.defineProperty(ctx,name,{get:() => fn.call(ctx)});
    return ctx;
}
test('six original sections filter the same series records and preserve catalog order', () => {
    for (const name of ['Todos','Básico','Avanzado','Interno','Dedicado','Contingencia']) assert.ok(source.includes('>'+name+'<') || source.includes('</span>'+name+'<'));
    const ctx = context();
    ctx.records = [{id:1, category:'basic', number:'FF01', sort_order:1}, {id:2, category:'advanced', number:'TT01', sort_order:3}, {id:3,category:'internal', number:'NV01', sort_order:4}, {id:4,category:'basic',number:'FF02',dedicated:true,sort_order:1},{id:5,category:'basic',number:'0001',contingency:true,sort_order:1}];
    for (const [filter,count] of [['all',5],['basic',2],['advanced',1],['internal',1],['dedicated',1],['contingency',1]]) {
        ctx.filter=filter;
        assert.equal(ctx.visibleRecords.length,count);
    }
});
test('create stays in same window, normalizes series and sends only local configuration', async () => {
    const ctx=context(); const requests=[];
    ctx.form={seriesTypeKey:'credit',number:'fc02',mode:'manual',correlative:100,emission:'normal',error:''}; ctx.creating=true;
    ctx.$http={post:async (url,data)=>{requests.push({url,data});return {data:{success:true,message:'Guardado'}}}, get:async url=>{requests.push({url});return {data:{data:[]}}}};
    await ctx.confirmCreate();
    assert.equal(requests[0].url,'/series');
    assert.equal(requests[0].data.number,'FC02');
    assert.equal(requests[0].data.correlative,100);
    assert.equal(requests[0].data.document_type_id,'07');
    assert.ok(requests.every(r=>r.url.startsWith('/series')));
    assert.equal(ctx.creating,false);
    assert.equal(ctx.saving,false);
});
test('cancel does not write and rejected creation keeps the draft', async () => {
    const ctx=context(); ctx.creating=true;
    ctx.form={seriesTypeKey:'invoice',number:'FF01',correlative:100,emission:'normal'};
    ctx.$http={post:async()=>{throw {response:{status:422,data:{errors:{number:['Serie duplicada']}}}}}};
    await ctx.confirmCreate();
    assert.equal(ctx.form.number,'FF01'); assert.equal(ctx.form.correlative,100);
    assert.equal(ctx.form.error,'Serie duplicada'); assert.equal(ctx.creating,true);
    ctx.cancelCreate(); assert.equal(ctx.creating,false);assert.equal(ctx.form.number,'');
});
test('dedicated section reloads groups and selectable series without HKA requests', async () => {
    const ctx=context(); const calls=[];
    ctx.$http={get:async url=>{calls.push(url);return {data:{data:[],available_series:[],modules:[]}}}};
    ctx.setFilter('dedicated');
    await Promise.resolve();
    assert.deepEqual(calls,['/series/groups/records/1','/series/groups/tables/1']);
    assert.equal(ctx.filter,'dedicated');
});
test('used series remain visibly locked and all modalities share the original dialog', () => {
    assert.ok(source.includes(':disabled="row.in_use"'));
    const parent=fs.readFileSync('resources/js/views/tenant/establishments/index.vue','utf8');
    assert.ok(parent.includes("'./partials/series.vue'"));
    assert.ok(!parent.includes('fiscal-numbering.vue'));
    assert.ok(!source.includes('fiscal_profiles') && !source.includes('Asignar controles'));
    assert.equal(compiler.compile(parsed.template.content).errors.length,0);
});

test('new series scrolls its inline editor into view without writes', () => {
    const ctx=context(); const calls=[];
    ctx.$nextTick=callback=>callback();
    ctx.$refs={seriesEditor:{scrollIntoView:options=>calls.push(options.block)}};
    ctx.refreshNumber=()=>{};
    ctx.clickNew();
    assert.equal(ctx.creating,true);
    assert.deepEqual(calls,['nearest']);
    assert.equal(ctx.form.seriesTypeKey,'invoice');
});
