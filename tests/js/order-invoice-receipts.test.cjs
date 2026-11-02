const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const compiler = require('vue-template-compiler')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default
const load = require('./helpers/load-module.cjs')

const source=compiler.parseComponent(fs.readFileSync('modules/Order/Resources/assets/js/views/order_notes/partials/options.vue','utf8'))
const tree=parser.parse(source.script.content,{sourceType:'module'})
const component=tree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration
const methods=component.properties.find(node=>node.key.name==='methods').value
const computed=component.properties.find(node=>node.key.name==='computed').value
const globals={...load('resources/js/helpers/ensure-exchange-rate-sale.js'),...load('resources/js/helpers/document-creation.js'),
    ...load('resources/js/helpers/payment-classification.js'),_: {isEmpty: value => !value || !Object.keys(value).length}}
const get=name=>vm.runInNewContext(`({${generate(methods.properties.find(node=>node.key?.name===name)).code}})[${JSON.stringify(name)}]`,globals)
const submit=get('submit'), assign=get('assignPrepayments'), validate=get('validatePaymentDestination')
const orderForm=compiler.parseComponent(fs.readFileSync('modules/Order/Resources/assets/js/views/order_notes/form.vue','utf8'))
const formTree=parser.parse(orderForm.script.content,{sourceType:'module'})
const formComponent=formTree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration
const formMethods=formComponent.properties.find(node=>node.key.name==='methods').value
const formGet=name=>vm.runInNewContext(`({${generate(formMethods.properties.find(node=>node.key?.name===name)).code}})[${JSON.stringify(name)}]`,{
    ...globals,moment:()=>({format:()=> '2026-10-01'})})

test('order draft payments retain received currency and rate at conversion',async()=>{
    const draft={id:null,document_id:null,date_of_payment:'2026-10-01',payment_method_type_id:'13',payment_destination_id:2,
        reference:'R',payment:'100',currency_type_id:'USD',original_amount:'2.50',exchange_rate:'40'}
    const ctx={form:{order_note:{prepayments:[draft]}},document:{payments:[]},calculatePayments(){}}
    await assign.call(ctx)
    const row=ctx.document.payments[0]
    for(const field of ['payment','currency_type_id','original_amount','exchange_rate','payment_destination_id']) assert.equal(row[field],draft[field])
    assert.deepEqual(compiler.compile(source.template.content).errors,[])
    assert.equal((source.template.content.match(/<igtf-payment-fields/g)||[]).length,2)
    assert.match(source.template.content,/se registran al generar el comprobante/)
})

test('destination validation uses the method classification even with credit documents and retired IDs',()=>{
    const ctx={isCreditPaymentCondition:true,payment_method_types:[{id:'08',is_active:true,collection_kind:'account',is_credit:true},{id:'09',is_active:true,collection_kind:'cash',is_credit:true}],
        document:{payments:[{payment_method_type_id:'08',payment_destination_id:null},{payment_method_type_id:'09',payment_destination_id:'cash'}]}}
    assert.equal(validate.call(ctx).error_by_item,1)
    ctx.document.payments[0].payment_destination_id=2
    assert.equal(validate.call(ctx).error_by_item,0)
    ctx.payment_method_types[1].collection_kind='unclassified'
    assert.equal(validate.call(ctx).error_by_item,1)
})

test('order conversion proposes matching active cash instead of the first account',()=>{
    const add=get('clickAddPayment')
    const ctx={payment_method_types:[{id:'01',is_active:true,collection_kind:'account',currency_type_id:'VES'},
        {id:'10',is_active:true,collection_kind:'cash',currency_type_id:'USD'},
        {id:'14',is_active:true,collection_kind:'cash',currency_type_id:'VES'}],
        document:{currency_type_id:'VES',payments:[]},calculatePayments(){}}
    globals.moment=()=>({format:()=> '2026-10-01'})
    add.call(ctx)
    assert.equal(ctx.document.payments[0].payment_method_type_id,'14')
    ctx.payment_method_types[2].is_active=false
    add.call(ctx)
    assert.equal(ctx.document.payments[1].payment_method_type_id,null)
    assert.match(source.template.content,/No hay efectivo activo para la moneda del documento/)
    const getter=name=>vm.runInNewContext(`({${generate(computed.properties.find(node=>node.key?.name===name)).code}})[${JSON.stringify(name)}]`,globals)
    const catalog={payment_method_types:[{id:'01',is_active:true,collection_kind:'account',is_credit:true},
        {id:'04',is_active:true,collection_kind:'credit',is_credit:false},
        {id:'14',is_active:false,collection_kind:'cash'}, {id:'15',is_active:true,collection_kind:'unclassified'}]}
    assert.deepEqual(Array.from(getter('cash_payment_method_types').call(catalog),method=>method.id),['01'])
    assert.deepEqual(Array.from(getter('credit_payment_method_types').call(catalog),method=>method.id),['04'])
})

test('order draft suggests a classified method and does not overwrite earlier amounts',()=>{
    const add=formGet('clickAddPayment')
    const ctx={payment_method_types:[{id:'10',is_active:true,collection_kind:'cash',currency_type_id:'USD'},{id:'01',is_active:true,collection_kind:'cash',currency_type_id:'VES'}],
        form:{currency_type_id:'VES',total:'100',prepayments:[]}}
    add.call(ctx)
    assert.equal(ctx.form.prepayments[0].payment_method_type_id,'01')
    assert.equal(ctx.form.prepayments[0].payment,'100.00')
    add.call(ctx)
    assert.equal(ctx.form.prepayments[0].payment,'100.00')
    assert.equal(ctx.form.prepayments[1].payment,'0.00')
    const changed={payment_method_types:[{id:'01',is_active:true,collection_kind:'account',currency_type_id:'VES'},{id:'13',is_active:true,collection_kind:'account',currency_type_id:'USD'}],
        form:{currency_type_id:'VES',total:'5',prepayments:[]}}
    add.call(changed)
    assert.equal(changed.form.prepayments[0].payment_method_type_id,null)
    assert.deepEqual(compiler.compile(orderForm.template.content).errors,[])
    assert.match(orderForm.template.content,/<receipt-currency-fields/)
})

test('order creation displays validation errors returned in the standard envelope',async()=>{
    const create=formGet('submit')
    const messages=[]
    const ctx={form:{date_of_issue:'2026-10-01',date_of_due:'2026-10-01',delivery_date:'2026-10-01',customer_id:1},
        $refs:{},resource:'order-notes',$message:{error:value=>messages.push(value)},
        $http:{post:()=>Promise.reject({response:{status:422,data:{message:'Datos inválidos',errors:{payments:['Seleccione un método válido.']}}}})}}
    await create.call(ctx)
    assert.equal(messages[0],'Seleccione un método válido.')
    assert.equal(ctx.errors.payments[0],'Seleccione un método válido.')
    assert.equal(ctx.loading_submit,false)
})

test('order item forms retain the entered price without floating point IVA artifacts',async()=>{
    const {calculateRowItem}=load('resources/js/helpers/functions.js')
    for (const filename of ['item.vue','item_edit.vue']) {
        const part=compiler.parseComponent(fs.readFileSync(`modules/Order/Resources/assets/js/views/order_notes/partials/${filename}`,'utf8'))
        const parsed=parser.parse(part.script.content,{sourceType:'module'})
        const object=parsed.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration
        const action=object.properties.find(node=>node.key.name==='methods').value.properties.find(node=>node.key?.name==='clickAddItem')
        const run=vm.runInNewContext(`({${generate(action).code}}).clickAddItem`,{calculateRowItem,_:{filter:()=>[],find:()=>({})}})
        let saved
        const ctx={form:{item:{id:1,currency_type_id:'VES',lots:[],unit_price:100},item_id:1,unit_price:100,
            has_igv:false,quantity:1,affectation_igv_type_id:'10',discounts:[],charges:[]},
            currencyTypeIdActive:'VES',exchangeRateSale:'1',percentageIgv:'0.16',affectation_igv_types:[],
            validateQuantity(){},validateTotalItem(){return {}},initForm(){},setFocusSelectItem(){},$emit(event,row){saved=row}}
        await run.call(ctx)
        assert.equal(saved.price_input.amount,'100')
        assert.equal(saved.price_input.includes_iva,false)
        assert.equal(saved.unit_price,'116')
        assert.equal(saved.total,'116')
        assert.deepEqual(compiler.compile(part.template.content).errors,[])
    }
})

test('reopening either order item form restores its saved price and tax basis',async()=>{
    for (const filename of ['item.vue','item_edit.vue']) {
        const part=compiler.parseComponent(fs.readFileSync(`modules/Order/Resources/assets/js/views/order_notes/partials/${filename}`,'utf8'))
        const parsed=parser.parse(part.script.content,{sourceType:'module'})
        const object=parsed.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration
        const method=object.properties.find(node=>node.key.name==='methods').value.properties.find(node=>node.key?.name==='create')
        const run=vm.runInNewContext(`({${generate(method).code}}).create`,{_:{find:()=>null}})
        for (const original of [{amount:'100',currency_type_id:'USD',includes_iva:false},null]) {
            const ctx={recordItem:{item_id:1,quantity:'2',unit_price:'116',item:{},discounts:[],charges:[],
                calculation_snapshot:{inputs:{price_input:original}}},form:{item:{}},currencyTypeIdActive:'VES',
                async reloadDataItems(){},async changeItem(){},calculateQuantity(){}}
            await run.call(ctx)
            assert.equal(ctx.form.unit_price,original?.amount ?? '116')
            assert.equal(ctx.form.unit_price_value,ctx.form.unit_price)
            assert.equal(ctx.form.has_igv,original ? false : true)
            assert.equal(ctx.form.item.currency_type_id,original?.currency_type_id ?? 'VES')
        }
    }
})

test('order conversion submits the currency and blocks unverified historical receipt IDs',async()=>{
    const calls=[],errors=[]
    const row={id:null,document_id:null,payment_method_type_id:'13',currency_type_id:'USD',original_amount:'2.50',exchange_rate:'40',payment:'100',payment_destination_id:2}
    const ctx={document:{items:[{}],document_type_id:'01',date_of_issue:'2026-10-01',currency_type_id:'VES',exchange_rate_sale:'40',payment_condition_id:'01',payments:[row]},
        validatePaymentDestination:async()=>({error_by_item:0}),fnValidateRestrictSaleItemsCpe:()=>({success:true}),ensureFiscalPaymentsReady:async()=>true,
        $message:{error:m=>errors.push(m)},$http:{post:async(url,data)=>{calls.push({url,data});return {data:{success:false,message:'sin guardar'}}}}}
    await submit.call(ctx)
    await new Promise(resolve=>setImmediate(resolve))
    assert.equal(calls[0].url,'/documents')
    assert.equal(calls[0].data.exchange_rate_sale,'40.00000000')
    assert.equal(calls[0].data.payments[0].original_amount,'2.50')
    ctx.document.payments[0].id=7
    await submit.call(ctx)
    assert.equal(calls.length,1)
    assert.match(errors[errors.length-1],/origen no identificado/)
})
