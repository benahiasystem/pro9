const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync('resources/js/views/tenant/dispatches/partials/fiscal-status.vue','utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
const sandbox={module:{exports:{}}};vm.runInNewContext(source.replace('export default','module.exports ='),sandbox);
const component=sandbox.module.exports;
function context(http) {
    return {busy:false,record:{id:9},$http:http,$set:(o,k,v)=>o[k]=v,$message:{info(){},error(message){this.last=message;}},$emit(){}};
}
test('fiscal action posts once and refreshes emission and PDF availability',async()=>{
    const calls=[];const record={fiscal_emission:{status:'confirmed',description:'Confirmado'},pdf_downloads:{hka_a4:{available:true}}};
    const ctx=context({post:async url=>calls.push(url),get:async url=>{calls.push(url);return {data:{data:record}};}});
    await component.methods.perform.call(ctx,'query-hka');
    assert.deepEqual(calls,['/dispatches/9/query-hka','/dispatches/record/9']);assert.equal(ctx.record.fiscal_emission.status,'confirmed');assert.equal(ctx.record.pdf_downloads.hka_a4.available,true);assert.equal(ctx.busy,false);
});
test('double clicks and unsupported actions do not repeat mutations',async()=>{
    let release;let calls=0;const ctx=context({post:()=>{calls++;return new Promise(r=>release=r);},get:async()=>({data:{fiscal_emission:{description:'Pendiente'},pdf_downloads:{}}})});
    const first=component.methods.perform.call(ctx,'send-hka');await component.methods.perform.call(ctx,'send-hka');assert.equal(calls,1);release();await first;
    await component.methods.perform.call(ctx,'anular');assert.equal(calls,1);
});
test('network failures preserve the record and release the action guard',async()=>{
    const ctx=context({post:async()=>{throw new Error('Offline');}});const record=ctx.record;
    await component.methods.perform.call(ctx,'relate-hka');assert.equal(ctx.record,record);assert.equal(ctx.busy,false);assert.match(ctx.$message.last,/orden sigue guardada/);
});

test('the list and finish dialog mount the fiscal status control in their compiled templates',()=>{
    const compiler=require('vue-template-compiler');
    for (const file of ['resources/js/views/tenant/dispatches/index.vue','resources/js/views/tenant/dispatches/partials/finish.vue']) {
        const parsed=compiler.parseComponent(fs.readFileSync(file,'utf8'));const result=compiler.compile(parsed.template.content);
        assert.deepEqual(result.errors,[]);assert.match(result.render,/dispatch-fiscal-status/);
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
