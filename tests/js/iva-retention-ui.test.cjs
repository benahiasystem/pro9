const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs'),vm=require('node:vm');
const compiler=require('vue-template-compiler'),babel=require('@babel/core');
function load(path,runtime={}){
    let source=fs.readFileSync(path,'utf8');if(path.endsWith('.vue'))source=compiler.parseComponent(source).script.content;
    const exports={};
    vm.runInNewContext(babel.transformSync(source,{configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']}).code,{exports,require:()=>({default:{}}),...runtime});
    return exports.default;
}
const fields=load('resources/js/views/tenant/documents/partials/iva_retention_fields.vue');
const mixin=load('resources/js/mixins/iva-retention.js');
test('forecast changes the cash target while leaving invoice total and real receipts intact',()=>{
    const form={document_type_id:'01',total:116,iva_retention_prevision:{enabled:true,amount:12},payments:[{payment:20,original_amount:20}]};
    assert.equal(mixin.computed.ivaCollectionTarget.call({form}),104);
    assert.equal(form.total,116);assert.equal(form.payments[0].payment,20);
    form.document_type_id='80';assert.equal(mixin.computed.ivaCollectionTarget.call({form}),116);
});
test('selecting the suggested net keeps tax totals intact and preserves multiple existing receipts',()=>{
    let amount;
    const form={total:116,currency_type_id:'VES',payments:[{payment:116}]};
    const ctx={form,proposal:{amount:12},$set:(o,k,v)=>o[k]=v,$emit:(event,value)=>{amount=value},$message:{info:()=>{}}};
    fields.methods.useNet.call(ctx);assert.equal(amount,104);assert.equal(form.payments[0].original_amount,104);assert.equal(form.total,116);
    form.payments.push({payment:10});amount=null;fields.methods.useNet.call(ctx);
    assert.equal(amount,null);assert.equal(form.payments[1].payment,10);
});
test('a stale forecast cannot replace the current invoice suggestion',async()=>{
    let resolve;
    const form={document_type_id:'01',customer_id:1,date_of_issue:'2026-10-12',currency_type_id:'VES',total_igv:16,total_taxed:100,iva_retention_prevision:{enabled:true}};
    const ctx={form,enabled:true,sequence:0,context:{},$set:(o,k,v)=>o[k]=v,$http:{post:()=>new Promise(r=>resolve=r)}};
    const pending=fields.methods.evaluate.call(ctx);ctx.sequence++;
    resolve({data:{data:{eligible:true,amount:'12.00',percentage:75}}});await pending;
    assert.equal(form.iva_retention_prevision.amount,undefined);
});
test('reopening POS payments restores entered receipts instead of suggesting the gross total',async()=>{
    for(const file of ['payment','fast_payment','fast_payment_garage']) {
        const component=load(`resources/js/views/tenant/pos/partials/${file}.vue`);
        const rows=[{payment:20,original_amount:20},{payment:84,original_amount:84}];
        const input={focus(){},select(){}};
        const ctx={form:{total:116,payments:rows},ivaCollectionTarget:104,getTotal:()=>104,$nextTick:async()=>{},
            $refs:{enter_amount:{$el:{getElementsByTagName:()=>[input],querySelector:()=>input}}}};
        await component.methods.setInitialAmount.call(ctx);
        assert.equal(ctx.amount,104,file);assert.equal(ctx.enter_amount,104,file);assert.deepEqual(ctx.form.payments,rows);
        const restore={form:{total:116,payments:[]},$set:(o,k,v)=>o[k]=v,inputAmount(){this.recalculated=true}};
        const key=file==='fast_payment_garage'?'form_pos_garage':'form_pos';
        const restored=load(`resources/js/views/tenant/pos/partials/${file}.vue`,{localStorage:{getItem:requested=>requested===key?JSON.stringify({payment_condition_id:'01',payments:rows}):null}});
        restored.methods.getFormPosLocalStorage.call(restore);
        assert.equal(restore.amount,104,file);assert.equal(restore.enter_amount,104,file);assert.equal(restore.recalculated,true);
    }
});
test('garage refresh preserves one receipt and multiple receipts when the forecast changes',()=>{
    const garage=load('resources/js/views/tenant/pos/partials/fast_payment_garage.vue');
    for(const rows of [[{payment:50,original_amount:50}],[{payment:20},{payment:84}]]) {
        let displayed;
        const snapshot=JSON.stringify(rows);
        const ctx={form:{items:[{}],payments:rows,payment_condition_id:'01',total:116},ivaCollectionTarget:104,
            inputDiscountAmount(){},setAmount:amount=>displayed=amount,$refs:{componentMultiplePaymentGarage:{clickAddPayment(){assert.fail('Cannot add another receipt')}}}};
        garage.methods.checkPaymentGarage.call(ctx);
        assert.equal(JSON.stringify(rows),snapshot);assert.equal(displayed,rows.reduce((sum,row)=>sum+row.payment,0));
    }
});
test('voucher validation details remain visible for both Web and API error envelopes',async()=>{
    const retention=load('resources/js/views/tenant/documents/partials/retention.vue');
    for(const body of [{invoice_control:['La referencia fiscal no coincide.']},{success:false,message:{operation_key:['Clave reutilizada.']}},{message:'Adjunto vencido.'}]) {
        const ctx={form:{},requestError:retention.methods.requestError,$http:{post:async()=>{throw {response:{data:body}}}}};
        await retention.methods.submit.call(ctx);
        assert.ok(ctx.error && !ctx.error.startsWith('No se pudo registrar'));assert.equal(ctx.loading,false);
    }
});
test('adding a method in order invoicing preserves real collections and suggests only the remaining net',()=>{
    const component=load('modules/Order/Resources/assets/js/views/order_notes/partials/options.vue',{_:{round:(n,p)=>Math.round(n*10**p)/10**p}});
    const document={document_type_id:'01',total:116,iva_retention_prevision:{enabled:true,amount:12},payments:[{payment:50},{payment:0}]};
    component.methods.calculatePayments.call({document});
    assert.equal(document.payments[0].payment,50);assert.equal(document.payments[1].payment,54);assert.equal(document.total,116);
});
test('all creation and collection entries compile with the IVA dialogue',()=>{
    const paths=['documents/invoice_generate','documents/partials/options','documents/partials/payments','documents/partials/retention','documents/partials/iva_retention_fields','pos/partials/payment','pos/partials/fast_payment','pos/partials/fast_payment_garage','pos/partials/options'];
    for(const name of paths){const file=compiler.parseComponent(fs.readFileSync(`resources/js/views/tenant/${name}.vue`,'utf8'));assert.deepEqual(compiler.compile(file.template.content).errors,[],name);}
    for(const path of ['Hotel/Resources/assets/js/views/rooms/Checkout','Sale/Resources/assets/js/views/technical-services/partials/options','Order/Resources/assets/js/views/order_notes/partials/options','Suscription/Resources/assets/js/payment_receipt/partials/option_documents','FullSuscription/Resources/assets/js/payment_receipt/partials/option_documents']){
        const file=compiler.parseComponent(fs.readFileSync(`modules/${path}.vue`,'utf8'));assert.deepEqual(compiler.compile(file.template.content).errors,[],path);
    }
});
test('combined IVA and ISLR suggestions preserve invoice taxes and partial receipts',()=>{
    const form={document_type_id:'01',total:116,currency_type_id:'VES',iva_retention_prevision:{enabled:true,amount:12},islr_retention_prevision:{enabled:true,amount:3},payments:[{payment:116}]};
    assert.equal(mixin.computed.ivaCollectionTarget.call({form}),101);
    let amount;fields.methods.useNet.call({form,proposal:{amount:12},$set:(o,k,v)=>o[k]=v,$emit:(event,v)=>amount=v,$message:{info(){}}});assert.equal(amount,101);assert.equal(form.total,116);
    const islr=load('resources/js/views/tenant/documents/partials/islr_retention_fields.vue');
    islr.methods.useNet.call({form,proposal:{amount:3},$set:(o,k,v)=>o[k]=v,$emit:(event,v)=>amount=v,$message:{info(){}}});assert.equal(amount,101);
    form.payments.push({payment:10});amount=null;islr.methods.useNet.call({form,proposal:{amount:3},$set:(o,k,v)=>o[k]=v,$emit:(event,v)=>amount=v,$message:{info(){}}});assert.equal(amount,null);assert.equal(form.payments[1].payment,10);
});
test('draft vouchers retain the other tax kind and ISLR templates compile',async()=>{
    const retention=load('resources/js/views/tenant/documents/partials/retention.vue');
    const draft={received_retentions:[{tax_kind:'IVA',amount:12}]};let closed=false;
    await retention.methods.submit.call({draftForm:draft,form:{tax_kind:'ISLR',concept_id:'055',amount:3},$set:(o,k,v)=>o[k]=v,close(){closed=true}});
    assert.equal(draft.received_retentions.length,2);assert.equal(draft.received_retentions[0].tax_kind,'IVA');assert.equal(draft.received_retentions[1].tax_kind,'ISLR');assert.equal(closed,true);
    const file=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/partials/islr_retention_fields.vue','utf8'));assert.deepEqual(compiler.compile(file.template.content).errors,[]);
});
