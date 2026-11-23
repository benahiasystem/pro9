const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const compiler = require('vue-template-compiler')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default

for (const file of ['payment.vue', 'fast_payment.vue', 'fast_payment_garage.vue']) {
    const component = compiler.parseComponent(fs.readFileSync(`resources/js/views/tenant/pos/partials/${file}`, 'utf8'))
    const ast = parser.parse(component.script.content, {sourceType:'module'})
    const methods = ast.program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration.properties.find(node => node.key?.name === 'methods').value.properties
    const method = methods.find(node => node.key?.name === 'clickPayment')
    const click = vm.runInNewContext(`({${generate(method).code}}).clickPayment`, {
        moment: () => ({format: () => '2026-11-21', isSame: () => true}),
        exactValue: () => ({compare: () => 1}), posInvoiceQuantityError: () => null,
        withoutDraftRetentions: value => value, withoutPosScalePrices: value => value,
    })

    test(`${file} impide dos envíos mientras espera la evaluación fiscal`, async () => {
        let release, evaluations = 0, posts = 0
        const quote = new Promise(resolve => { release = resolve })
        const ctx = {
            locked_submit:false, form:{document_type_id:'01', total:'1', items:[{}], payments:[], series_id:1, customer_id:1},
            businessTurns:{active:false}, rowsItems:1, config:{}, configuration:{},
            refreshScaleTotals:()=>true, validateRestrictSaleItemsCpe:()=>({success:true}),
            validateRestrictSellerDiscount:()=>({success:true}), asignPlateNumberToItems:async()=>{},
            ensureFiscalPaymentsReady:()=>{ evaluations++; return quote },
            prepareCalculationSubmission:value=>value,
            $message:{warning(){}, error(){}}, $http:{post:async()=>{posts++; return {data:{success:false}}}},
        }
        const first = click.call(ctx)
        await new Promise(resolve => setImmediate(resolve))
        assert.equal(ctx.locked_submit, true)
        await click.call(ctx)
        assert.equal(evaluations, 1)
        release(true)
        await first
        assert.equal(posts, 1)
        assert.equal(ctx.locked_submit, false)
        ctx._paymentDisposed = false
        let currencyBlocked = false, releaseCurrency
        ctx.currencyBlockedCheck = () => currencyBlocked
        ctx.ensureFiscalPaymentsReady = () => new Promise(resolve => { releaseCurrency = resolve })
        const changingCurrency = click.call(ctx)
        await new Promise(resolve => setImmediate(resolve))
        currencyBlocked = true
        releaseCurrency(true)
        await changingCurrency
        assert.equal(posts, 1)
        assert.equal(ctx.locked_submit, false)
        currencyBlocked = false
        ctx.stockBlocked = true
        await click.call(ctx)
        assert.equal(ctx.locked_submit, false)
        assert.equal(posts, 1)
        ctx.stockBlocked = false
        ctx.ensureFiscalPaymentsReady = async () => { throw new Error('fallo de evaluación') }
        await assert.rejects(click.call(ctx), /fallo de evaluación/)
        assert.equal(ctx.locked_submit, false)
        let releaseAfterDestroy
        ctx.ensureFiscalPaymentsReady = () => new Promise(resolve => { releaseAfterDestroy = resolve })
        const pending = click.call(ctx)
        await new Promise(resolve => setImmediate(resolve))
        ctx._paymentDisposed = true
        releaseAfterDestroy(true)
        await pending
        assert.equal(posts, 1)
        assert.equal(ctx.locked_submit, false)
    })

    for (const type of ['01','80']) test(`${file} no vincula otra caja ni reinicia la venta nueva ante respuesta tardía de ${type}`, async () => {
        let resolvePost, current, cleanups=0
        const cash=[],events=[],warnings=[]
        const initial={document_type_id:type,total:'1',items:[{quantity:'1',unit_type_id:'UND'}],payments:[],series_id:1,customer_id:1,payment_condition_id:'01'}
        current=initial
        const ctx={locked_submit:false,form:initial,paymentState:()=>current,businessTurns:{active:false},rowsItems:1,config:{},configuration:{},
            refreshScaleTotals:()=>true,validateRestrictSaleItemsCpe:()=>({success:true}),validateRestrictSellerDiscount:()=>({success:true}),
            asignPlateNumberToItems:async()=>{},ensureFiscalPaymentsReady:async()=>true,prepareCalculationSubmission:value=>value,
            saveCashDocument:value=>cash.push(value),cleanLocalStoragePayment:()=>{cleanups++},
            $eventHub:{$emit:(...args)=>events.push(args)},$message:{warning:message=>warnings.push(message),error(){}},
            $http:{post:()=>new Promise(resolve=>{resolvePost=resolve})}}
        const pending=click.call(ctx)
        await new Promise(resolve=>setImmediate(resolve))
        assert.equal(typeof resolvePost,'function')
        current={document_type_id:type==='01'?'80':'01',payments:[{payment:'9'}]};ctx.form=current
        resolvePost({data:{success:true,data:{id:7,number_full:'DEMO-7'}}})
        await pending
        assert.equal(cash.length,0)
        assert.equal(cleanups,0);assert.equal(events.length,0);assert.equal(current.payments[0].payment,'9')
        assert.ok(warnings.some(message=>message.includes('DEMO-7')))
    })
    test(`${file} confirma una venta vigente una sola vez`, async () => {
        const cash=[],events=[],warnings=[]
        const form={document_type_id:'01',total:'1',items:[{quantity:'1',unit_type_id:'UND'}],payments:[],series_id:1,customer_id:1,payment_condition_id:'01'}
        const ctx={locked_submit:false,form,paymentState:()=>form,businessTurns:{active:false},rowsItems:1,config:{},configuration:{},
            refreshScaleTotals:()=>true,validateRestrictSaleItemsCpe:()=>({success:true}),validateRestrictSellerDiscount:()=>({success:true}),
            asignPlateNumberToItems:async()=>{},ensureFiscalPaymentsReady:async()=>true,prepareCalculationSubmission:value=>value,
            autoSendPdfMail(){},showOptionsDialog(){},saveCashDocument:()=>cash.push(ctx.form_cash_document),cleanLocalStoragePayment(){},initDataComponent(){},
            $eventHub:{$emit:(...args)=>events.push(args)},$message:{warning:message=>warnings.push(message),error(){}},
            $http:{post:async()=>({data:{success:true,data:{id:7,number_full:'DEMO-7'},links:{print_ticket:'/ticket/7'}}})}}
        await click.call(ctx)
        assert.equal(cash.length,1);assert.equal(cash[0].document_id,7);assert.equal(cash[0].sale_note_id,null);assert.equal(cash[0].require_existing_cash_link,true)
        assert.equal(events.length,1);assert.equal(events[0][0],'saleSuccess');assert.equal(events[0][1],form)
        assert.equal(ctx.locked_submit,false);assert.equal(warnings.length,0)
    })
    for(const change of ['edicion','desmontaje']) test(`${file} conserva el borrador si hay ${change} durante el POST`,async()=>{
        let resolvePost,cleanups=0,cash=0;const events=[],warnings=[]
        const form={document_type_id:'01',total:'1',items:[{quantity:'1',unit_type_id:'UND'}],payments:[],series_id:1,customer_id:1,payment_condition_id:'01'}
        const ctx={locked_submit:false,form,paymentState:()=>form,businessTurns:{active:false},rowsItems:1,config:{},configuration:{},
            refreshScaleTotals:()=>true,validateRestrictSaleItemsCpe:()=>({success:true}),validateRestrictSellerDiscount:()=>({success:true}),
            asignPlateNumberToItems:async()=>{},ensureFiscalPaymentsReady:async()=>true,prepareCalculationSubmission:value=>value,
            saveCashDocument:()=>{cash++},cleanLocalStoragePayment:()=>{cleanups++},$eventHub:{$emit:(...args)=>events.push(args)},
            $message:{warning:message=>warnings.push(message),error(){}},$http:{post:()=>new Promise(resolve=>{resolvePost=resolve})}}
        const pending=click.call(ctx);await new Promise(resolve=>setImmediate(resolve))
        if(change==='edicion')form.payments.push({payment:'9'});else ctx._paymentDisposed=true
        resolvePost({data:{success:true,data:{id:7,number_full:'DEMO-7'}}});await pending
        assert.equal(cleanups,0);assert.equal(cash,0);assert.equal(events.length,0);assert.ok(warnings.some(message=>message.includes('DEMO-7')))
        if(change==='edicion')assert.equal(form.payments[0].payment,'9')
    })
}

test('ocultar opciones no cancela la venta siguiente tras un éxito',()=>{
    const source=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/pos/partials/payment.vue','utf8'))
    const ast=parser.parse(source.script.content,{sourceType:'module'}),methods=ast.program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration.properties.find(n=>n.key?.name==='methods').value.properties
    const show=vm.runInNewContext(`({${generate(methods.find(n=>n.key?.name==='showOptionsDialog')).code}}).showOptionsDialog`)
    for(const isPrint of [true,false]){const events=[];let printed=0
        const ctx={hidePdfViewDocuments:true,isPrint,form:{document_type_id:'01'},responseForm:{data:{number_full:'DEMO-7'}},
            $message:{success(){}},$emit:(...args)=>events.push(args),autoPrint(){printed++},clickCancel(){assert.fail('no debe cancelar la siguiente venta')}}
        show.call(ctx,null);assert.equal(printed,isPrint?1:0);assert.equal(events.length,1);assert.equal(events[0][0],'update:is_payment');assert.equal(events[0][1],false)
    }
})

test('la cancelación rápida no limpia ni reinicia una venta reemplazada durante la espera', async () => {
    const component = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/pos/partials/fast_payment.vue', 'utf8'))
    const ast = parser.parse(component.script.content, {sourceType:'module'})
    const methods = ast.program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration.properties.find(node => node.key?.name === 'methods').value.properties
    const cancel = vm.runInNewContext(`({${generate(methods.find(node => node.key?.name === 'clickCancel')).code}}).clickCancel`)
    let release, current = {payments:[{payment:'5'}]}, cleaned = 0, emitted = []
    const ctx = {form:current,paymentState:()=>current,sleep:()=>new Promise(resolve=>{release=resolve}),
        cleanLocalStoragePayment:()=>{cleaned++},$eventHub:{$emit:(...args)=>emitted.push(args)}}
    const pending = cancel.call(ctx)
    current={payments:[{payment:'9'}]};release();await pending
    assert.equal(cleaned,0);assert.equal(emitted.length,0)
    ctx.form=current
    ctx.locked_submit=true;await cancel.call(ctx);assert.equal(cleaned,0)
    ctx.locked_submit=false
    const pendingPayment=cancel.call(ctx);ctx.locked_submit=true;release();await pendingPayment
    assert.equal(cleaned,0);assert.equal(emitted.length,0)
    ctx.locked_submit=false
    const valid = cancel.call(ctx);release();await valid
    assert.equal(cleaned,1);assert.equal(emitted[0][0],'cancelSale');assert.equal(emitted[0][1],current)
})

for(const file of ['payment.vue','fast_payment_garage.vue']) test(`${file} no cancela durante un envío o tras reemplazar el borrador`,async()=>{
    const source=compiler.parseComponent(fs.readFileSync(`resources/js/views/tenant/pos/partials/${file}`,'utf8'))
    const ast=parser.parse(source.script.content,{sourceType:'module'}),methods=ast.program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration.properties.find(n=>n.key?.name==='methods').value.properties
    const cancel=vm.runInNewContext(`({${generate(methods.find(n=>n.key?.name==='clickCancel')).code}}).clickCancel`)
    let resolveSleep,current={payments:[]},clean=0;const events=[]
    const ctx={form:current,paymentState:()=>current,locked_submit:true,$confirm:async()=>true,sleep:()=>new Promise(resolve=>{resolveSleep=resolve}),
        cleanLocalStoragePayment:()=>{clean++},$eventHub:{$emit:(...args)=>events.push(args)},$message:{success(){}}}
    await cancel.call(ctx);assert.equal(clean,0)
    ctx.locked_submit=false;const pending=cancel.call(ctx);await new Promise(resolve=>setImmediate(resolve))
    current={payments:[{payment:'9'}]};resolveSleep();await pending
    assert.equal(clean,0);assert.equal(events.length,0)
    ctx.form=current;const sending=cancel.call(ctx);await new Promise(resolve=>setImmediate(resolve));ctx.locked_submit=true;resolveSleep();await sending
    assert.equal(clean,0);assert.equal(events.length,0)
})
