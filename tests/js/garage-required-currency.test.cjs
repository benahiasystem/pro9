const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm')
const compiler=require('vue-template-compiler'),parser=require('@babel/parser'),generate=require('@babel/generator').default
const vue=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/pos/garage.vue','utf8'))
const ast=parser.parse(vue.script.content,{sourceType:'module'})
const component=ast.program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration
const method=component.properties.find(n=>n.key?.name==='methods').value.properties.find(n=>n.key?.name==='initCurrencyType')
const computed=component.properties.find(n=>n.key?.name==='computed').value.properties.find(n=>n.key?.name==='posCurrencyUnavailable')
const paymentBlocked=component.properties.find(n=>n.key?.name==='computed').value.properties.find(n=>n.key?.name==='posPaymentBlocked')
const activationWatch=component.properties.find(n=>n.key?.name==='watch').value.properties.find(n=>n.key?.name==='posPaymentBlocked')
function runtime(onlyVes) {
    return vm.runInNewContext(`({${generate(method).code},${generate(computed).code},${generate(paymentBlocked).code}})`,{window:{salesDocumentsVes:onlyVes},isValidPosRate:()=>true})
}
test('Garage no sustituye VES por USD cuando VES no está activo',()=>{
    const fn=runtime(true), draft={currency_type_id:'USD',items:[{currency_type_id:'USD'}],exchange_rate_sale:'3'}
    const ctx={form:draft,currency_types:[{id:'USD'}],changeCurrencyType(){throw Error('no debe convertir el borrador')}}
    assert.equal(fn.posCurrencyUnavailable.call(ctx),true);fn.initCurrencyType.call(ctx)
    assert.equal(ctx.form.currency_type_id,'USD');assert.equal(ctx.form.items[0].currency_type_id,'USD')
    assert.match(vue.template.content,/v-if="posCurrencyUnavailable"/)
    assert.match(vue.template.content,/v-if="show_fast_payment_garage && paymentPanelActivated"/)
    assert.match(vue.template.content,/v-show="!posCurrencyUnavailable"/)
})
test('Garage conserva un objeto de presentación cuando la moneda del borrador no está activa',()=>{
    const fn=runtime(true),ctx={form:{currency_type_id:'VES',items:[{currency_type_id:'VES'}],exchange_rate_sale:'3'},currency_types:[{id:'USD'}],changeCurrencyType(){throw Error('no debe convertir')}}
    fn.initCurrencyType.call(ctx)
    assert.equal(ctx.form.currency_type_id,'VES')
    assert.equal(ctx.currency_type.symbol,undefined)
    assert.equal(fn.posCurrencyUnavailable.call(ctx),true)
})
test('Garage sigue convirtiendo a VES cuando está disponible y admite USD si no se exige VES',()=>{
    const calls=[],ctx={form:{currency_type_id:'USD',items:[],exchange_rate_sale:'3'},currency_types:[{id:'USD'},{id:'VES'}],changeCurrencyType(c){calls.push(c)}}
    const required=runtime(true);assert.equal(required.posCurrencyUnavailable.call(ctx),false);required.initCurrencyType.call(ctx);assert.deepEqual(calls,['VES'])
    const flexible=runtime(false);calls.length=0;flexible.initCurrencyType.call(ctx);assert.deepEqual(calls,[])
})
test('Garage bloquea partidas con moneda distinta aun si se permiten USD y VES',()=>{
    const fn=runtime(false),ctx={posCurrencyUnavailable:false,posRateUnavailable:false,currency_types:[{id:'VES'},{id:'USD'}],
        form:{currency_type_id:'VES',items:[{currency_type_id:'USD'}]}}
    assert.equal(fn.posPaymentBlocked.call(ctx),true)
})
test('bloquea el pago si el borrador o una línea aún no están convertidos',()=>{
    const fn=runtime(true),ctx={posCurrencyUnavailable:false,posRateUnavailable:true,paymentPanelActivated:false,draftRestored:false,currency_types:[{id:'USD'},{id:'VES'}],form:{currency_type_id:'USD',items:[{currency_type_id:'USD'}],payments:[]}}
    const watch=vm.runInNewContext(`({${generate(activationWatch).code}}).posPaymentBlocked`)
    watch.call(ctx,false);assert.equal(ctx.paymentPanelActivated,false)
    ctx.draftRestored=true
    assert.equal(fn.posPaymentBlocked.call(ctx),true)
    watch.call(ctx,true);assert.equal(ctx.paymentPanelActivated,false);assert.equal(ctx.form.payments.length,0)
    ctx.posRateUnavailable=false;assert.equal(fn.posPaymentBlocked.call(ctx),true)
    ctx.currency_types=[{id:'VES'}];ctx.changeCurrencyType=()=>false;fn.initCurrencyType.call(ctx)
    assert.equal(ctx.form.currency_type_id,'USD');assert.equal(fn.posPaymentBlocked.call(ctx),true)
    ctx.form.currency_type_id='VES';assert.equal(fn.posPaymentBlocked.call(ctx),true)
    ctx.form.items[0].currency_type_id='VES';assert.equal(fn.posPaymentBlocked.call(ctx),false)
    watch.call(ctx,false);assert.equal(ctx.paymentPanelActivated,true);assert.equal(ctx.form.payments.length,0)
    watch.call(ctx,true);assert.equal(ctx.paymentPanelActivated,true)
})
test('una tasa temprana no monta el panel antes de restaurar el borrador USD',async()=>{
    const fn=runtime(true),created=component.properties.find(n=>n.key?.name==='created')
    const start=vm.runInNewContext(`({${generate(created).code}}).created`)
    const watch=vm.runInNewContext(`({${generate(activationWatch).code}}).posPaymentBlocked`)
    let release
    const ctx={draftRestored:false,paymentPanelActivated:false,posCurrencyUnavailable:false,posRateUnavailable:false,currency_types:[{id:'VES'},{id:'USD'}],
        form:{currency_type_id:'VES',items:[],payments:[]},establishment:{id:1},
        loadConfiguration(){},initForm:async()=>{},getTables:async()=>{},getPercentageIgv:()=>new Promise(resolve=>{release=resolve}),events(){},
        getFormPosLocalStorage:async function(){this.form={currency_type_id:'USD',items:[{currency_type_id:'USD'}],payments:[]}},
        initCurrencyType:async()=>false,getLocalStorageIndex:async()=>null,selectDefaultCustomer:async()=>{},enabledSearchItemByBarcode(){}}
    Object.defineProperty(ctx,'posPaymentBlocked',{get(){return fn.posPaymentBlocked.call(ctx)}})
    const pending=start.call(ctx);await new Promise(resolve=>setImmediate(resolve))
    watch.call(ctx,false);assert.equal(ctx.paymentPanelActivated,false)
    release();await pending
    assert.equal(ctx.form.currency_type_id,'USD');assert.equal(ctx.form.payments.length,0);assert.equal(ctx.paymentPanelActivated,false)
    ctx.form.currency_type_id='VES';ctx.form.items[0].currency_type_id='VES';watch.call(ctx,false)
    assert.equal(ctx.paymentPanelActivated,true)
})
test('el panel comprueba la moneda otra vez tras esperas y al destruirse',async()=>{
    const src=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/pos/partials/fast_payment_garage.vue','utf8'))
    const tree=parser.parse(src.script.content,{sourceType:'module'}), obj=tree.program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration
    const click=obj.properties.find(n=>n.key?.name==='methods').value.properties.find(n=>n.key?.name==='clickPayment')
    const run=vm.runInNewContext(`({${generate(click).code}}).clickPayment`,{moment:require('moment'),posInvoiceQuantityError:()=>null,withoutDraftRetentions:x=>x})
    let release,blocked=true,posts=0,warnings=[]
    const ctx={form:{document_type_id:'01',payment_condition_id:'02',currency_type_id:'USD',items:[{quantity:1,currency_type_id:'USD'}],payments:[],customer_id:1,series_id:1,date_of_issue:require('moment')().format('YYYY-MM-DD')},rowsItems:1,businessTurns:{active:false},configuration:{},currencyBlockedCheck:()=>blocked,$message:{warning:m=>warnings.push(m),error(){}},$http:{post:async()=>{posts++;return {data:{success:false}}}},prepareCalculationSubmission:x=>x,asignPlateNumberToItems:async()=>{},ensureFiscalPaymentsReady:()=>new Promise(resolve=>{release=resolve})}
    await run.call(ctx);assert.equal(posts,0);assert.equal(ctx.form.currency_type_id,'USD')
    blocked=false;const pending=run.call(ctx);await new Promise(resolve=>setImmediate(resolve));blocked=true;release(true);await pending;assert.equal(posts,0)
    blocked=false;const pendingDestroyed=run.call(ctx);await new Promise(resolve=>setImmediate(resolve));ctx._paymentDisposed=true;release(true);await pendingDestroyed;assert.equal(posts,0)
    ctx._paymentDisposed=false;ctx.form.currency_type_id='VES';ctx.form.items[0].currency_type_id='VES';const allowed=run.call(ctx);await new Promise(resolve=>setImmediate(resolve));release(true);await allowed;assert.equal(posts,1)
    assert.ok(warnings.length>=2)
})
test('al destruir el panel elimina solo sus listeners y no los instala tardíamente',async()=>{
    const src=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/pos/partials/fast_payment_garage.vue','utf8'))
    const tree=parser.parse(src.script.content,{sourceType:'module'}),obj=tree.program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration
    const events=obj.properties.find(n=>n.key?.name==='methods').value.properties.find(n=>n.key?.name==='events')
    const destroyed=obj.properties.find(n=>n.key?.name==='beforeDestroy'),created=obj.properties.find(n=>n.key?.name==='created')
    const actual=vm.runInNewContext(`({${generate(events).code},${generate(destroyed).code},${generate(created).code}})`)
    const hub=new (require('vue'))();let writes=0,other=0
    hub.$on('reloadDataPersons',()=>other++)
    const ctx={$eventHub:hub,reloadDataCustomers(){writes++},setFormPosLocalStorage(){writes++},initInputPerson(){},checkPaymentGarage(){}}
    actual.events.call(ctx);hub.$emit('reloadDataPersons',1);assert.equal(writes,2)
    actual.beforeDestroy.call(ctx);hub.$emit('reloadDataPersons',1);assert.equal(writes,2);assert.equal(other,2)
    let release;const late={$eventHub:hub,initLStoPayment:()=>new Promise(resolve=>{release=resolve}),getTables(){throw Error('no debe continuar')}}
    const pending=actual.created.call(late);actual.beforeDestroy.call(late);release();await pending
    assert.equal(late._garageListeners.length,0)
})
test('una consulta de clientes iniciada antes del cierre no restaura el borrador',async()=>{
    const src=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/pos/partials/fast_payment_garage.vue','utf8'))
    const tree=parser.parse(src.script.content,{sourceType:'module'}),obj=tree.program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration
    const reload=obj.properties.find(n=>n.key?.name==='methods').value.properties.find(n=>n.key?.name==='reloadDataCustomers')
    const run=vm.runInNewContext(`({${generate(reload).code}}).reloadDataCustomers`)
    let release,writes=0;const ctx={resource:'pos',form:{customer_id:3},$http:{get:()=>new Promise(resolve=>{release=resolve})},changeCustomer(){writes++}}
    run.call(ctx,7);ctx._paymentDisposed=true;release({data:[{id:7}]});await new Promise(resolve=>setImmediate(resolve))
    assert.equal(ctx.form.customer_id,3);assert.equal(writes,0);assert.equal(ctx.all_customers,undefined)
})
