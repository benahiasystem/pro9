const {test}=require('node:test')
const assert=require('node:assert/strict')
const fs=require('node:fs'),vm=require('node:vm')
const parser=require('@babel/parser'),generate=require('@babel/generator').default,compiler=require('vue-template-compiler')
const helper=require('./helpers/load-module.cjs')(require('node:path').resolve('resources/js/helpers/payment-classification.js'))

test('sale note credit installment date follows classification and configured days, not the method ID',()=>{
    const source=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/sale_notes/form.vue','utf8'))
    const tree=parser.parse(source.script.content,{sourceType:'module'})
    const methods=tree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties.find(node=>node.key.name==='methods').value
    const method=methods.properties.find(node=>node.key?.name==='isCreditFeeDateReadonly')
    const context={payment_method_types:[{id:'09',collection_kind:'account',number_days:null},
        {id:'06',collection_kind:'credit',number_days:null}, {id:'01',collection_kind:'credit',number_days:30},
        {id:'05',collection_kind:'credit',number_days:0}, {id:'14',collection_kind:'credit',number_days:'0'}]}
    const readonly=method ? vm.runInNewContext(`({${generate(method).code}}).isCreditFeeDateReadonly`).bind(context) : ()=>false
    const start=source.template.content.indexOf('v-for="option in credit_payment_metod"')
    const expression=source.template.content.slice(start).match(/:readonly="([\s\S]*?)"/)[1]
    const evaluate=id=>vm.runInNewContext(`Boolean(${expression})`,{row:{payment_method_type_id:id},isCreditFeeDateReadonly:readonly})
    assert.equal(evaluate('06'),false,'crédito personalizado sin plazo admite fecha manual')
    assert.equal(evaluate('05'),false,'crédito sin días no depende del ID')
    assert.equal(evaluate('14'),false,'cero en texto también permite fecha manual')
    assert.equal(evaluate('01'),true,'crédito con plazo calcula la fecha')
    assert.equal(evaluate('09'),true,'un método bancario no habilita una cuota de crédito')
    assert.equal(evaluate('99'),true,'un método desconocido no habilita la fecha')
    const change=vm.runInNewContext(`({${generate(methods.properties.find(node=>node.key?.name==='changePaymentMethodType')).code}}).changePaymentMethodType`,
        {_: {find:(rows,where)=>rows.find(row=>row.id===where.id)},moment:()=>({add:()=>({format:()=> '2026-11-01'})})})
    context.form={date_of_issue:'2026-10-01',fee:[{payment_method_type_id:'14',date:'2026-12-01'}],payments:[]}
    change.call(context,0,true)
    assert.equal(context.form.fee[0].date,'2026-12-01','cero en texto no reemplaza la fecha manual')
})

test('contract header uses current cash method instead of the old fixed USD cash ID',()=>{
    const source=compiler.parseComponent(fs.readFileSync('modules/Sale/Resources/assets/js/views/contracts/form.vue','utf8'))
    parser.parse(source.script.content,{sourceType:'module'})
    assert.doesNotMatch(source.script.content,/payment_method_type_id:\s*["']10["']/)
    assert.match(source.script.content,/this\.form\.payment_method_type_id\s*=\s*method\?\.id\s*\|\|\s*null/)
    assert.deepEqual(compiler.compile(source.template.content).errors,[])
})

test('contract editing reveals saved payments and uses their frozen descriptions',async()=>{
    const source=compiler.parseComponent(fs.readFileSync('modules/Sale/Resources/assets/js/views/contracts/form.vue','utf8'))
    const tree=parser.parse(source.script.content,{sourceType:'module'})
    const methods=tree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties.find(node=>node.key.name==='methods').value
    const entry=methods.properties.find(node=>node.key?.name==='isUpdate')
    const run=vm.runInNewContext(`({${generate(entry).code}}).isUpdate`)
    const contract={customer_id:4,items:[{}],quotation_id:7,payments:[{id:8,currency_type_id:'USD',original_amount:'1',exchange_rate:'100',payment_method_snapshot:{description:'Zelle'}}]}
    const ctx={id:9,resource:'contracts',showPayments:false,$http:{get:async()=>({data:{data:{contract}}})},reloadDataCustomers(){}}
    await run.call(ctx)
    assert.equal(ctx.showPayments,true)
    assert.equal(ctx.form.payments[0].original_amount,'1')
    assert.match(source.template.content,/payment_method_snapshot\?\.description/)
    assert.match(source.template.content,/payment_destination_description/)
    assert.match(source.template.content,/form\.payments\.some\(row => row\.id\)/)
    const computed=tree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties.find(node=>node.key.name==='computed').value
    const list=vm.runInNewContext(`({${generate(computed.properties.find(node=>node.key?.name==='receiptPaymentMethods')).code}}).receiptPaymentMethods`)
    const methodsList=[{id:'01',is_active:true,collection_kind:'account',is_credit:true},
        {id:'04',is_active:true,collection_kind:'cash'}, {id:'09',is_active:true,collection_kind:'credit'},
        {id:'14',is_active:false,collection_kind:'cash'}]
    assert.deepEqual(Array.from(list.call({payment_method_types:methodsList}),row=>row.id),['01','04'])
    assert.deepEqual(compiler.compile(source.template.content).errors,[])
})

test('purchase order conversion keeps credit out of payment rows and validates destination choices',()=>{
    const source=compiler.parseComponent(fs.readFileSync('modules/Purchase/Resources/assets/js/views/purchase-orders/partials/document_generate.vue','utf8'))
    const tree=parser.parse(source.script.content,{sourceType:'module'})
    const methods=tree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties.find(node=>node.key.name==='methods').value
    const entry=methods.properties.find(node=>node.key?.name==='applyOrderPaymentDefault')
    const run=vm.runInNewContext(`({${generate(entry).code}}).applyOrderPaymentDefault`,helper)
    const ctx={form:{currency_type_id:'VES',purchase_order:{payment_method_type_id:'09'},payments:[{payment_method_type_id:null}]},
      payment_method_types:[{id:'09',is_active:true,collection_kind:'credit'},{id:'04',is_active:true,collection_kind:'cash',currency_type_id:'VES'}, {id:'01',is_active:true,collection_kind:'account',currency_type_id:'USD'}], payment_destinations:[{id:'cash'}]}
    run.call(ctx);assert.equal(ctx.form.payments[0].payment_method_type_id,null)
    ctx.form.purchase_order.payment_method_type_id='04';run.call(ctx);assert.equal(ctx.form.payments[0].payment_method_type_id,'04');assert.equal(ctx.form.payments[0].payment_destination_id,'cash')
    ctx.form.payments[0].payment_method_type_id=null;ctx.form.purchase_order.payment_method_type_id='01';run.call(ctx);assert.equal(ctx.form.payments[0].payment_method_type_id,'01');assert.equal(ctx.form.payments[0].payment_destination_id,null)
    assert.deepEqual(compiler.compile(source.template.content).errors,[])
    assert.match(source.template.content,/classifiedPaymentDestinations\(payment_destinations, row\)/)
})

for(const filename of ['modules/Purchase/Resources/assets/js/views/purchase-orders/form.vue','modules/Purchase/Resources/assets/js/views/purchase-orders/generate.vue']) {
    test(`${filename} prepares a purchase-order method using the document currency`,()=>{
        const source=compiler.parseComponent(fs.readFileSync(filename,'utf8'))
        parser.parse(source.script.content,{sourceType:'module'})
        assert.deepEqual(compiler.compile(source.template.content).errors,[])
        assert.match(source.script.content,/defaultCashForCurrency\(this\.payment_method_types, this\.form\.currency_type_id\)/)
        assert.doesNotMatch(source.script.content,/payment_method_type_id:\s*['"]01['"]/) 
        assert.match(source.template.content,/No hay efectivo activo para la moneda del documento/)
    })
}

for(const filename of ['resources/js/views/tenant/purchases/form.vue','resources/js/views/tenant/purchases/form_edit.vue','resources/js/views/tenant/purchase-settlements/form.vue']) {
    const source=compiler.parseComponent(fs.readFileSync(filename,'utf8'))
    const tree=parser.parse(source.script.content,{sourceType:'module'})
    const component=tree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration
    const methods=component.properties.find(node=>node.key.name==='methods').value
    const computed=component.properties.find(node=>node.key.name==='computed').value
    const getter=name=>vm.runInNewContext(`({${generate(computed.properties.find(node=>node.key?.name===name)).code}})[${JSON.stringify(name)}]`)
    const method=methods.properties.find(node=>node.key?.name==='clickAddPayment')
    const add=vm.runInNewContext(`({${generate(method).code}}).clickAddPayment`,{...helper,moment:()=>({format:()=> '2026-10-01'})})
    const changeEntry=methods.properties.find(node=>node.key?.name==='changePaymentMethodType')
    const change=vm.runInNewContext(`({${generate(changeEntry).code}}).changePaymentMethodType`,{moment:()=>({add:()=>({format:()=> '2026-11-01'})})})
    const conditionEntry=methods.properties.find(node=>node.key?.name==='changePaymentCondition')
    const condition=vm.runInNewContext(`({${generate(conditionEntry).code}}).changePaymentCondition`)
    test(`${filename} proposes cash for the currency of the expense`,()=>{
        const catalog={payment_method_types:[{id:'01',is_active:true,collection_kind:'account',is_credit:true},
            {id:'04',is_active:true,collection_kind:'credit',is_credit:false},
            {id:'14',is_active:false,collection_kind:'cash'}, {id:'15',is_active:true,collection_kind:'unclassified'}]}
        assert.deepEqual(Array.from(getter('cashPaymentMethod').call(catalog),method=>method.id),['01'])
        assert.deepEqual(Array.from(getter('creditPaymentMethod').call(catalog),method=>method.id),['04'])
        const ctx={form:{currency_type_id:'VES',payments:[]},payment_method_types:[{id:'01',is_active:true,collection_kind:'account',currency_type_id:'VES'}, {id:'04',is_active:true,collection_kind:'cash',currency_type_id:'VES'}], payment_destinations:[{id:2}], calculatePayments:()=>{}, calculateFee:()=>{}}
        add.call(ctx);assert.equal(ctx.form.payments[0].payment_method_type_id,'04');assert.equal(ctx.form.payments[0].payment_destination_id,null)
        ctx.payment_destinations=[{id:'cash'}];add.call(ctx);assert.equal(ctx.form.payments[1].payment_destination_id,'cash')
        ctx.form.currency_type_id='USD';add.call(ctx);assert.equal(ctx.form.payments[2].payment_method_type_id,null);assert.equal(ctx.form.payments[2].payment_destination_id,null)
        ctx.form.payment_method_type_id='07';ctx.form.date_of_issue='2026-10-01';ctx.form.fee=[];ctx.form.payment_condition_id='01'
        ctx.payment_method_types.push({id:'07',number_days:30})
        change.call(ctx);assert.equal(ctx.form.date_of_due,'2026-11-01')
        ctx.form.payments[0].payment_method_type_id='04';change.call(ctx,0);assert.equal(ctx.form.date_of_due,'2026-10-01')
        change.call(ctx,99)
        const existing=ctx.form.payments[0];existing.payment='25'
        ctx.form.payment_condition_id='02';ctx.clickAddFeeNew=()=>{};ctx.clickAddFee=()=>{};ctx.initDataPaymentCondition=()=>{};ctx.clickAddPayment=()=>{throw Error('no debe crear otro pago')}
        condition.call(ctx);assert.equal(ctx.form.payments[0],existing);assert.equal(existing.payment,'25')
        ctx.form.payment_condition_id='01';condition.call(ctx);assert.equal(ctx.form.payments[0],existing);assert.equal(existing.payment,'25')
        assert.deepEqual(compiler.compile(source.template.content).errors,[])
    })
}

test('loan payment defaults to an active account method and only a matching currency destination',()=>{
    const source=compiler.parseComponent(fs.readFileSync('modules/Expense/Resources/assets/js/views/loan_payments/payments.vue','utf8'))
    const tree=parser.parse(source.script.content,{sourceType:'module'})
    const methods=tree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties.find(node=>node.key.name==='methods').value
    const method=methods.properties.find(node=>node.key?.name==='clickAddRow')
    const add=vm.runInNewContext(`({${generate(method).code}}).clickAddRow`,{moment:()=>({format:()=> '2026-10-01'})})
    const ctx={records:[],expense:{payment_destination_id:3,currency_type_id:'VES'},
        payment_method_types:[{id:'04',collection_kind:'cash',is_active:true},{id:'07',collection_kind:'account',is_active:true},{id:'01',collection_kind:'account',is_active:false}],
        payment_destinations:[{id:3,currency_type_id:'USD'}]}
    add.call(ctx);assert.equal(ctx.records[0].payment_method_type_id,'07');assert.equal(ctx.records[0].payment_destination_id,null)
    ctx.payment_destinations[0].currency_type_id='VES';add.call(ctx);assert.equal(ctx.records[1].payment_destination_id,3)
    ctx.payment_method_types=[];add.call(ctx);assert.equal(ctx.records[2].payment_method_type_id,null);assert.equal(ctx.records[2].payment_destination_id,null)
    assert.deepEqual(compiler.compile(source.template.content).errors,[])
})
test('loan method change keeps compatible accounts regardless of method ID',()=>{
    const source=compiler.parseComponent(fs.readFileSync('modules/Expense/Resources/assets/js/views/loan_payments/payments.vue','utf8'))
    const tree=parser.parse(source.script.content,{sourceType:'module'})
    const methods=tree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties.find(node=>node.key.name==='methods').value
    const entry=methods.properties.find(node=>node.key?.name==='changeExpenseMethodType')
    const change=vm.runInNewContext(`({${generate(entry).code}}).changeExpenseMethodType`)
    const ctx={records:[{payment_method_type_id:'01',payment_destination_id:2}],expense:{payment_destination_id:3},
        payment_method_types:[{id:'01',collection_kind:'account',is_active:true},{id:'04',collection_kind:'cash',is_active:true}],
        payment_destinations:[{id:2},{id:3}],classifiedPaymentDestinations:()=>[{id:2},{id:3}]}
    change.call(ctx,0);assert.equal(ctx.records[0].payment_destination_id,2)
    ctx.records[0].payment_destination_id=9;change.call(ctx,0);assert.equal(ctx.records[0].payment_destination_id,3)
    ctx.records[0].payment_method_type_id='04';change.call(ctx,0);assert.equal(ctx.records[0].payment_destination_id,null)
    assert.deepEqual(compiler.compile(source.template.content).errors,[])
})
for(const filename of ['modules/Finance/Resources/assets/js/views/income/form.vue','modules/Sale/Resources/assets/js/views/contracts/form.vue','resources/js/views/tenant/quotations/form_edit.vue']) {
    const source=compiler.parseComponent(fs.readFileSync(filename,'utf8'))
    const tree=parser.parse(source.script.content,{sourceType:'module'})
    const component=tree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration
    const methods=component.properties.find(node=>node.key.name==='methods').value
    const method=methods.properties.find(node=>node.key?.name==='clickAddPayment')
    const add=vm.runInNewContext(`({${generate(method).code}}).clickAddPayment`,{...helper,moment:()=>({format:()=> '2026-10-01'})})
    test(`${filename} chooses active cash in the document currency and leaves incompatible destinations empty`,()=>{
        const ctx={form:{payments:[],currency_type_id:'USD'},payment_method_types:[{id:'01',collection_kind:'account',currency_type_id:'USD',is_active:true},{id:'13',collection_kind:'cash',currency_type_id:'VES',is_active:true}],
            payment_destinations:[{id:'cash'},{id:2}],getPaymentDestinationId:()=> 'cash'}
        add.call(ctx)
        assert.equal(ctx.form.payments[0].payment_method_type_id,null)
        assert.equal(ctx.form.payments[0].payment_destination_id,null)
        ctx.payment_method_types.push({id:'04',collection_kind:'cash',currency_type_id:'USD',is_active:true})
        add.call(ctx)
        assert.equal(ctx.form.payments[1].payment_method_type_id,'04')
        assert.equal(ctx.form.payments[1].payment_destination_id,'cash')
        ctx.payment_destinations=[{id:2}]
        add.call(ctx)
        assert.equal(ctx.form.payments[2].payment_destination_id,null)
        assert.deepEqual(compiler.compile(source.template.content).errors,[])
    })
}

for(const [filename, target] of [
    ['resources/js/views/tenant/sale_notes/partials/option_documents.vue','document'],
    ['modules/Suscription/Resources/assets/js/payment_receipt/partials/option_documents.vue','document'],
    ['modules/FullSuscription/Resources/assets/js/payment_receipt/partials/option_documents.vue','document'],
    ['modules/Sale/Resources/assets/js/views/technical-services/partials/options.vue','form'],
]) {
    const source=compiler.parseComponent(fs.readFileSync(filename,'utf8'))
    const tree=parser.parse(source.script.content,{sourceType:'module'})
    const methods=tree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties.find(node=>node.key.name==='methods').value
    const method=methods.properties.find(node=>node.key?.name==='clickAddPayment')
    const add=vm.runInNewContext(`({${generate(method).code}}).clickAddPayment`,{...helper,moment:()=>({format:()=> '2026-10-01'}),suggestRemainingPayment:()=>{}})
    test(`${filename} proposes only cash in the document currency`,()=>{
        const ctx={payment_method_types:[{id:'01',is_active:true,collection_kind:'account',currency_type_id:'USD'}, {id:'04',is_active:true,collection_kind:'cash',currency_type_id:'USD'}], [target]:{currency_type_id:'USD',payments:[]}}
        add.call(ctx);assert.equal(ctx[target].payments[0].payment_method_type_id,'04')
        ctx[target].currency_type_id='VES';add.call(ctx);assert.equal(ctx[target].payments[1].payment_method_type_id,null)
        assert.deepEqual(compiler.compile(source.template.content).errors,[])
    })
}

test('POS multiple payments accept only classified receipt methods and retain all entered payments',()=>{
    const source=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/pos/partials/multiple_payment.vue','utf8'))
    const tree=parser.parse(source.script.content,{sourceType:'module'})
    const component=tree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration
    const computed=component.properties.find(node=>node.key.name==='computed').value
    const methods=component.properties.find(node=>node.key.name==='methods').value
    const run=(group,name)=>vm.runInNewContext(`({${generate(group.properties.find(node=>node.key?.name===name)).code}}).${name}`)
    const ctx={payment_method_types:[{id:'09',collection_kind:'account',is_active:true},{id:'04',collection_kind:'cash',is_active:true},{id:'01',collection_kind:'credit',is_active:true},{id:'07',collection_kind:'unclassified',is_active:true}],draftPayments:[{payment_method_type_id:'09',payment:'2'},{payment_method_type_id:'04',payment:'3'}],payments:[],enabled_payments:false,clonePayments:rows=>rows.map(row=>({...row})),events:[],$emit(...args){this.events.push(args)}}
    assert.deepEqual(Array.from(run(computed,'receiptMethods').call(ctx),x=>x.id),['09','04'])
    run(methods,'accept').call(ctx)
    assert.deepEqual(ctx.payments.map(x=>x.payment_method_type_id),['09','04'])
    assert.ok(ctx.events.some(event=>event[0]==='setPaymentMethod' && event[1]===null))
    assert.deepEqual(compiler.compile(source.template.content).errors,[])
})

for (const filename of ['resources/js/views/tenant/documents/invoice.vue','resources/js/views/tenant/documents/invoiceupdate.vue','resources/js/views/tenant/documents/invoice_generate.vue','resources/js/views/tenant/sale_notes/form.vue','modules/Sale/Resources/assets/js/views/technical-services/form.vue']) {
    test(`${filename} preserves actual receipts when switching to credit`,()=>{
        const source=compiler.parseComponent(fs.readFileSync(filename,'utf8'))
        const tree=parser.parse(source.script.content,{sourceType:'module'})
        const component=tree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration
        const methods=component.properties.find(node=>node.key.name==='methods').value
        const entry=methods.properties.find(node=>node.key?.name==='changePaymentCondition')
        const change=vm.runInNewContext(`({${generate(entry).code}}).changePaymentCondition`)
        const payment={payment_method_type_id:'09',currency_type_id:'USD',payment:'4'}
        const ctx={form:{payment_condition_id:'02',fee:[],payments:[payment]},clickAddFeeNew(){},clickAddFee(){},clickAddPayment(){throw Error('unexpected payment')},initDataPaymentCondition01(){}}
        change.call(ctx)
        assert.equal(ctx.form.payments.length,1)
        assert.equal(ctx.form.payments[0],payment)
        ctx.form.payments=[]
        change.call(ctx)
        assert.equal(ctx.form.payments.length,0)
        assert.deepEqual(compiler.compile(source.template.content).errors,[])
    })
}

for(const [filename, target] of [['resources/js/views/tenant/documents/invoice.vue','form'],['resources/js/views/tenant/documents/invoiceupdate.vue','form'],['resources/js/views/tenant/documents/invoice_generate.vue','form'],['resources/js/views/tenant/sale_notes/form.vue','form'],['resources/js/views/tenant/quotations/partials/options.vue','document']]) {
    test(`${filename} resolves installments independently from receipt IDs`,()=>{
        const source=compiler.parseComponent(fs.readFileSync(filename,'utf8'))
        const tree=parser.parse(source.script.content,{sourceType:'module'})
        const methods=tree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties.find(node=>node.key.name==='methods').value
        const entry=methods.properties.find(node=>node.key?.name==='changePaymentMethodType')
        const change=vm.runInNewContext(`({${generate(entry).code}}).changePaymentMethodType`,{_: {find:(rows,where)=>rows.find(row=>row.id===where.id)},moment:()=>({add:()=>({format:()=> '2026-11-01'})})})
        const ctx={[target]:{payments:[{payment_method_type_id:'09'}],fee:[{payment_method_type_id:'01'}],date_of_issue:'2026-10-01'}, payment_method_types:[{id:'09',collection_kind:'account'}, {id:'01',collection_kind:'credit',number_days:30}], enabled_payments:true}
        change.call(ctx,0,true)
        assert.equal(ctx[target].payment_method_type_id,'01')
        assert.equal(ctx.enabled_payments,true)
        change.call(ctx,0,false)
        assert.equal(ctx[target].payment_method_type_id,'01')
        assert.equal(ctx.enabled_payments,true)
    })
}

for (const filename of ['resources/js/views/tenant/documents/invoice.vue','resources/js/views/tenant/documents/invoiceupdate.vue','resources/js/views/tenant/documents/invoice_generate.vue','resources/js/views/tenant/sale_notes/form.vue']) {
    test(`${filename} changes only the selected installment date`,()=>{
        const source=compiler.parseComponent(fs.readFileSync(filename,'utf8'))
        const tree=parser.parse(source.script.content,{sourceType:'module'})
        const methods=tree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties.find(node=>node.key.name==='methods').value
        const entry=methods.properties.find(node=>node.key?.name==='changePaymentMethodType')
        const change=vm.runInNewContext(`({${generate(entry).code}}).changePaymentMethodType`,{
            _: {find:(rows,where)=>rows.find(row=>row.id===where.id)},
            moment:()=>({add:()=>({format:()=> '2026-11-01'})})})
        const ctx={form:{date_of_issue:'2026-10-01',payments:[],fee:[
            {payment_method_type_id:'01',date:'2026-10-20'}, {payment_method_type_id:'07',date:'2026-12-15'}]},
            payment_method_types:[{id:'01',collection_kind:'credit',number_days:30},{id:'07',collection_kind:'credit',number_days:'0'}]}
        change.call(ctx,0,true)
        assert.equal(ctx.form.fee[0].date,'2026-11-01')
        assert.equal(ctx.form.fee[1].date,'2026-12-15')
        change.call(ctx,1,true)
        assert.equal(ctx.form.fee[1].date,'2026-12-15')
    })
    test(`${filename} retains entered installments and receipts when switching payment conditions`,()=>{
        const source=compiler.parseComponent(fs.readFileSync(filename,'utf8'))
        const tree=parser.parse(source.script.content,{sourceType:'module'})
        const methods=tree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties.find(node=>node.key.name==='methods').value
        const entry=methods.properties.find(node=>node.key?.name==='changePaymentCondition')
        const change=vm.runInNewContext(`({${generate(entry).code}}).changePaymentCondition`)
        const receipt={id:4,payment:'1'}, fee={date:'2026-12-15',amount:'99'}
        const ctx={form:{payment_condition_id:'01',payments:[receipt],fee:[fee]},
            initDataPaymentCondition01(){},clickAddPayment(){throw Error('unexpected receipt')},
            clickAddFeeNew(){throw Error('existing fee should remain')},clickAddFee(){throw Error('existing fee should remain')}}
        change.call(ctx)
        ctx.form.payment_condition_id='02'; change.call(ctx)
        ctx.form.payment_condition_id='03'; change.call(ctx)
        assert.equal(ctx.form.fee[0],fee)
        assert.equal(ctx.form.payments[0],receipt)
    })
    test(`${filename} distinguishes an unselected credit method from an empty catalog`,()=>{
        const source=compiler.parseComponent(fs.readFileSync(filename,'utf8'))
        const block=source.template.content.match(/<small v-if="!row\.payment_method_type_id" class="text-muted">[\s\S]*?<\/small>/)[0]
        const compiled=compiler.compileToFunctions(`<div>${block}</div>`), Vue=require('vue')
        const render=credit_payment_metod=>{
            const vnode=new Vue({data:()=>({row:{payment_method_type_id:null},credit_payment_metod}),...compiled})._render()
            return vnode.children[0].children.map(item=>item.text||'').join('')
        }
        assert.match(render([{id:'05'}]),/Seleccione un método de crédito/)
        assert.match(render([]),/No hay un método de crédito activo/)
    })
}

for(const [filename,target] of [['resources/js/views/tenant/documents/invoice.vue','form'],['resources/js/views/tenant/documents/invoiceupdate.vue','form'],['resources/js/views/tenant/documents/invoice_generate.vue','form'],['resources/js/views/tenant/sale_notes/form.vue','form'],['resources/js/views/tenant/quotations/partials/options.vue','document'],['modules/Sale/Resources/assets/js/views/technical-services/form.vue','form']]) {
    test(`${filename} chooses matching cash instead of first account`,()=>{
        const source=compiler.parseComponent(fs.readFileSync(filename,'utf8'))
        const tree=parser.parse(source.script.content,{sourceType:'module'})
        const methods=tree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties.find(node=>node.key.name==='methods').value
        const entry=methods.properties.find(node=>node.key?.name==='clickAddPayment')
        const add=vm.runInNewContext(`({${generate(entry).code}}).clickAddPayment`,{...helper,moment:()=>({format:()=> '2026-10-01'}),String})
        const ctx={[target]:{currency_type_id:'USD',payments:[],total:'20'},form:{currency_type_id:'USD',payments:[],total:'20',quotation:{total:'20'}},paymentMethodsLoaded:true,pendingDefaultPaymentRows:[],payment_method_types:[{id:'01',is_active:true,collection_kind:'account',currency_type_id:'USD'},{id:'04',is_active:true,collection_kind:'cash',currency_type_id:'USD'}],getPaymentDestinationId:()=> 'cash',calculatePayments(){}}
        add.call(ctx);assert.equal(ctx[target].payments[0].payment_method_type_id,'04')
        ctx.payment_method_types.pop();add.call(ctx);assert.equal(ctx[target].payments[1].payment_method_type_id,null)
        assert.deepEqual(compiler.compile(source.template.content).errors,[])
    })
}

for(const [filename,target] of [['resources/js/views/tenant/documents/invoice.vue','form'],['resources/js/views/tenant/documents/invoiceupdate.vue','form'],['resources/js/views/tenant/documents/invoice_generate.vue','form'],['resources/js/views/tenant/sale_notes/form.vue','form'],['resources/js/views/tenant/quotations/partials/options.vue','document'],['modules/Sale/Resources/assets/js/views/technical-services/form.vue','form']]) {
    test(`${filename} never invents a fixed credit method`,()=>{
        const source=compiler.parseComponent(fs.readFileSync(filename,'utf8'))
        const tree=parser.parse(source.script.content,{sourceType:'module'})
        const methods=tree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties.find(node=>node.key.name==='methods').value
        const entry=methods.properties.find(node=>node.key?.name==='clickAddFeeNew')
        const add=vm.runInNewContext(`({${generate(entry).code}}).clickAddFeeNew`,{moment:()=>({add:()=>({format:()=> '2026-11-01'})})})
        const ctx={form:{date_of_issue:'2026-10-01',currency_type_id:'VES',fee:[]},document:{currency_type_id:'VES',fee:[]},credit_payment_metod:[],payment_method_types:[],calculateFee(){}}
        add.call(ctx);assert.equal(ctx[target].fee[0].payment_method_type_id,null)
        ctx.credit_payment_metod=[{id:'07',collection_kind:'credit',number_days:30,is_active:true}];ctx.payment_method_types=ctx.credit_payment_metod;add.call(ctx);assert.equal(ctx[target].fee[1].payment_method_type_id,'07')
    })
}

for(const filename of ['resources/js/views/tenant/documents/invoice.vue','resources/js/views/tenant/documents/invoiceupdate.vue','resources/js/views/tenant/sale_notes/form.vue','modules/Sale/Resources/assets/js/views/technical-services/form.vue']) {
    test(`${filename} keeps entered receipts while suggesting only the remaining amount`,()=>{
        const source=compiler.parseComponent(fs.readFileSync(filename,'utf8'))
        const tree=parser.parse(source.script.content,{sourceType:'module'})
        const methods=tree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties.find(node=>node.key.name==='methods').value
        const entry=methods.properties.find(node=>node.key?.name==='calculatePayments')
        const creation=require('./helpers/load-module.cjs')(require('node:path').resolve('resources/js/helpers/document-creation.js'))
        const calculate=vm.runInNewContext(`({${generate(entry).code}}).calculatePayments`,creation)
        const first={payment:'25',currency_type_id:'VES'},next={payment:'0',currency_type_id:'VES'}
        const ctx={form:{payment_condition_id:'01',currency_type_id:'VES',total:'100',payments:[first,next]}}
        calculate.call(ctx);assert.equal(first.payment,'25');assert.equal(next.payment,'75.00')
        next.payment='10';ctx.form.payment_condition_id='02';calculate.call(ctx)
        assert.equal(first.payment,'25');assert.equal(next.payment,'10')
        assert.deepEqual(compiler.compile(source.template.content).errors,[])
    })
}


test('suggested installments deduct applied receipts and keep saved or manually edited installments',()=>{
    const load=require('./helpers/load-module.cjs'),path=require('node:path')
    const {suggestInstallments}=load(path.resolve('resources/js/helpers/document-creation.js'))
    const saved={id:8,amount:'10'},edited={amount:'0'},suggested={amount:'0'}
    const form={total:'100',payments:[{payment:'25.01',currency_type_id:'USD',original_amount:'2.501'}],fee:[saved,edited,suggested]}
    suggestInstallments(form)
    assert.equal(saved.amount,'10');assert.equal(edited.amount,'32.50');assert.equal(suggested.amount,'32.49')
    edited.amount='20';form.payments[0].payment='30'
    suggestInstallments(form)
    assert.equal(saved.amount,'10');assert.equal(edited.amount,'20');assert.equal(suggested.amount,'40.00')
    form.payments[0].payment='1.';suggestInstallments(form);assert.equal(suggested.amount,'40.00')
    form.payments[0].payment='20';suggestInstallments(form,'90');assert.equal(suggested.amount,'40.00')
    form.payments[0].payment='100';suggestInstallments(form);assert.equal(suggested.amount,'0.00')
})

test('USD credit installments divide the applied VES receipt balance by cents',()=>{
    const load=require('./helpers/load-module.cjs')
    const {suggestInstallments}=load(require('node:path').resolve('resources/js/helpers/document-creation.js'))
    const form={total:'232',currency_type_id:'USD',payments:[{payment:'5.01',original_amount:'50.10',currency_type_id:'VES'}],
        fee:[{amount:'0'},{amount:'0'}]}
    suggestInstallments(form)
    assert.deepEqual(form.fee.map(row=>row.amount),['113.50','113.49'])
})

for(const [filename,target] of [['resources/js/views/tenant/documents/invoice.vue','form'],['resources/js/views/tenant/documents/invoiceupdate.vue','form'],['resources/js/views/tenant/documents/invoice_generate.vue','form'],['resources/js/views/tenant/sale_notes/form.vue','form'],['modules/Sale/Resources/assets/js/views/technical-services/form.vue','form'],['resources/js/views/tenant/quotations/partials/options.vue','document']]) {
    test(`${filename} refreshes suggested credit installments when applied amounts change`,()=>{
        const source=compiler.parseComponent(fs.readFileSync(filename,'utf8'))
        const tree=parser.parse(source.script.content,{sourceType:'module'})
        const component=tree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration
        const watchers=component.properties.filter(node=>node.key?.name==='watch').pop().value
        const entry=watchers.properties.find(node=>node.key?.name==='receiptAppliedAmounts')
        const watch=vm.runInNewContext(`({${generate(entry).code}}).receiptAppliedAmounts`)
        const ctx={[target]:{payment_condition_id:'02',fee:[{}]},calls:0,calculateFee(){this.calls++}}
        watch.call(ctx);assert.equal(ctx.calls,1)
        ctx[target].payment_condition_id='01';watch.call(ctx);assert.equal(ctx.calls,1)
        assert.deepEqual(compiler.compile(source.template.content).errors,[])
    })
}
