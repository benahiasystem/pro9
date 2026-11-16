const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const compiler = require('vue-template-compiler')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default
const load = require('./helpers/load-module.cjs')

test('hotel allocates the rounding cent across domestic receipts without changing foreign receipts', () => {
    const {allocateHotelPayments} = load('resources/js/helpers/hotel-payment-allocation.js')
    const items=[1,2].map(() => ({total:'0.333333333333',rent_payment:{payment_method_type_id:'04',payment:'0.33'}}))
    assert.equal(allocateHotelPayments(items,[{id:'04',currency_type_id:'VES'}]),true)
    assert.equal(items[0].rent_payment.payment,'0.34')
    assert.equal(items[1].rent_payment.payment,'0.33')
    assert.equal(items[0].rent_payment.original_amount,'0.34')
    const foreign=[{total:'0.333333333333',rent_payment:{payment_method_type_id:'01',currency_type_id:'USD',
        payment:'0.33',original_amount:'0.01',exchange_rate:'33'}}]
    const mixed=[foreign[0],{total:'0.333333333333',rent_payment:{payment_method_type_id:'04',payment:'0.33'}}]
    const methods=[{id:'01',currency_type_id:'USD'},{id:'04',currency_type_id:'VES'}]
    assert.equal(allocateHotelPayments(mixed,methods),true)
    assert.equal(foreign[0].rent_payment.original_amount,'0.01')
    assert.equal(foreign[0].rent_payment.payment,'0.33')
    assert.equal(mixed[1].rent_payment.payment,'0.34')
    const impossible=[{total:'0.333333333333',rent_payment:{payment_method_type_id:'01',currency_type_id:'USD',
        payment:'0.40',original_amount:'0.01',exchange_rate:'40'}},
        {total:'0.333333333333',rent_payment:{payment_method_type_id:'04',payment:'0.33'}}]
    assert.throws(()=>allocateHotelPayments(impossible,methods),/redondeo permitido/)
    assert.equal(impossible[0].rent_payment.original_amount,'0.01')
    assert.equal(impossible[1].rent_payment.payment,'0.33')
    const nearInteger=[{total:'0.014',rent_payment:{payment_method_type_id:'04'}},
        {total:'1000',rent_payment:{payment_method_type_id:'04'}},
        {total:'0.014',rent_payment:{payment_method_type_id:'01',currency_type_id:'USD',
            payment:'0.01',original_amount:'0.01',exchange_rate:'1'}}]
    assert.equal(allocateHotelPayments(nearInteger,methods),true)
    assert.equal(nearInteger[0].rent_payment.payment,'0.02')
    assert.equal(nearInteger[1].rent_payment.payment,'1000.00')
    const allForeign=['33','34'].map(rate=>({total:'0.333333333333',rent_payment:{payment_method_type_id:'01',currency_type_id:'USD',
        payment:rate==='33' ? '0.33' : '0.34',original_amount:'0.01',exchange_rate:rate}}))
    assert.equal(allocateHotelPayments(allForeign,methods),true)
    assert.deepEqual(allForeign.map(row=>row.rent_payment.original_amount),['0.01','0.01'])
    allForeign[1].rent_payment.exchange_rate='33'; allForeign[1].rent_payment.payment='0.33'
    assert.throws(()=>allocateHotelPayments(allForeign,methods),/cubrir exactamente/)
})

const globals = {...load('resources/js/helpers/payment-classification.js'),
    ...load('resources/js/helpers/document-creation.js'), ...load('resources/js/helpers/exchange-rate-math.js'),
    ...load('resources/js/helpers/hotel-payment-allocation.js'),
    ...load('resources/js/helpers/exact-document.js'), _:require('lodash'),
    moment: () => ({format: () => '2026-10-01'})}
function component(path) {
    const source = compiler.parseComponent(fs.readFileSync(path, 'utf8'))
    const tree = parser.parse(source.script.content, {sourceType: 'module'})
    const declaration = tree.program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration
    const methods = declaration.properties.find(node => node.key.name === 'methods').value
    return {source, computed(name) {
        const group = declaration.properties.find(node => node.key.name === 'computed').value
        const entry = group.properties.find(node => node.key?.name === name)
        return vm.runInNewContext(`({${generate(entry).code}})[${JSON.stringify(name)}]`, globals)
    }, method(name) {
        const entry = methods.properties.find(node => node.key?.name === name)
        return vm.runInNewContext(`({${generate(entry).code}})[${JSON.stringify(name)}]`, globals)
    }}
}
const hotel = component('modules/Hotel/Resources/assets/js/views/rooms/Checkout.vue')
const order = component('resources/js/views/tenant/orders/partials/sale_note_form.vue')
const rent = component('modules/Hotel/Resources/assets/js/views/rooms/Rent.vue')
const roomItems = component('modules/Hotel/Resources/assets/js/views/rooms/AddProductToRoom.vue')

test('hotel product submission applies the mixed-currency rounding allocation before building the note', async () => {
    const products=[{total:'0.333333333333',payment_status:'PAID',is_registered:false,
        rent_payment:{payment_method_type_id:'01',currency_type_id:'USD',payment:'0.33',original_amount:'0.01',exchange_rate:'33'}},
    {total:'0.333333333333',payment_status:'PAID',is_registered:false,
        rent_payment:{payment_method_type_id:'04',currency_type_id:'VES',payment:'0.33',original_amount:'0.33'}}]
    let submittedItems
    const ctx={form:{operation_key:'known',products},paymentCurrency:'VES',document:{items:[]},
        payment_method_types:[{id:'01',currency_type_id:'USD'},{id:'04',currency_type_id:'VES'}],
        isAtLeastOnePaid: rows=>rows.some(row=>row.payment_status==='PAID'),
        onGoToInvoice:async function () { submittedItems=this.document.items; return false },
        $message:{error:message=>{throw Error(message)}}}
    await roomItems.method('onSubmit').call(ctx)
    assert.equal(submittedItems[0].rent_payment.original_amount,'0.01')
    assert.equal(submittedItems[0].rent_payment.payment,'0.33')
    assert.equal(submittedItems[1].rent_payment.payment,'0.34')
})
const methods = [{id:'01', is_active:true, is_credit:true, collection_kind:'none', currency_type_id:'VES'},
    {id:'13', is_active:true, is_credit:false, collection_kind:'account', currency_type_id:'USD', bank_account_id:2}]
const destinations = [{id:'cash'}, {id:1,currency_type_id:'VES'}, {id:2,currency_type_id:'USD'}]

test('order receipt selector requires explicit active classification for every method', () => {
    const ctx = {payment_method_types: [
        {id:'01', collection_kind:'account', is_active:true},
        {id:'04', collection_kind:'cash', is_active:1},
        {id:'06', collection_kind:'account'},
        {id:'07', collection_kind:'cash', is_active:false},
        {id:'09', collection_kind:'credit', is_active:true},
        null,
    ]}
    assert.deepEqual(Array.from(order.computed('receiptMethods').call(ctx), method => method.id), ['01','04'])
    assert.deepEqual(Array.from(hotel.computed('receiptMethods').call({paymentMethodTypes: ctx.payment_method_types}), method => method.id), ['01','04'])
})

test('hotel check-in formats exact string totals after selecting a rate', () => {
    const ctx = {room:{rates:[{hotel_rate_id:7, price:'100.25', rate:{}}]},
        form:{hotel_rate_id:7, duration:2, rent_payment:{}}, onUpdateOutputDate() {}}
    ctx.onUpdateTotalToPay = rent.method('onUpdateTotalToPay')
    ctx.setTotalPayment = rent.method('setTotalPayment')
    rent.method('onSelectedRate').call(ctx)
    assert.equal(ctx.form.total_to_pay, '200.50')
    assert.equal(ctx.form.rent_payment.payment, '200.50')
    assert.equal(rent.method('formatHotelAmount')(ctx.form.total_to_pay), '200.50')
    assert.equal(rent.method('formatHotelAmount')('100.25'), '100.25')
    assert.match(rent.source.template.content, /formatHotelAmount\(form\.total_to_pay\)/)
    assert.deepEqual(compiler.compile(rent.source.template.content).errors, [])
})

test('hotel check-in keeps the final room rate and every night in the note', async () => {
    globals.calculateRowItem = load('resources/js/helpers/functions.js').calculateRowItem
    const room={id:7,description:'Habitación',unit_price:'999',currency_type_id:'VES',has_igv:false}
    const ctx={form:{duration:2,rate_price:'0.10',total_to_pay:'0.20',affectation_igv_type_id:'10'},
        affectationIgvTypes:[{id:'10'}],percentage_igv:'0.16',document:{items:[],exchange_rate_sale:'0',discounts:[],charges:[]},
        axiosError:error=>{throw error}}
    const product=rent.method('buildRoomProduct').call(ctx,room)
    assert.equal(product.quantity,'2')
    assert.equal(product.total,'0.2')
    assert.equal(product.price_input.includes_iva,true)
    assert.equal(room.unit_price,'999')
    ctx.document.items=[product]
    await rent.method('onUpdateItemsWithExtras').call(ctx)
    await rent.method('onUpdateItemsWithExtras').call(ctx)
    rent.method('onCalculateTotals').call(ctx)
    assert.equal(ctx.document.items[0].item.description,'Habitación x 2 noche(s)')
    assert.equal(ctx.document.items[0].quantity,'2')
    assert.equal(ctx.document.total,'0.2')
    assert.equal(ctx.document.total_igv,'0.027586206897')
    assert.equal(ctx.document.calculation_snapshot.values.total_igv.n,'4')
    assert.equal(ctx.document.calculation_snapshot.values.total_igv.d,'145')
    assert.equal(product.item.description,'Habitación')
    ctx.form.rate_price='90071992547409.91'
    assert.equal(rent.method('buildRoomProduct').call(ctx,room).total,'180143985094819.82')
})

test('hotel check-in clears the payable amount for invalid rate input',()=>{
    const ctx={form:{rate_price:'',duration:2,total_to_pay:'10',rent_payment:{payment:'10'}},errors:{},onUpdateOutputDate(){throw Error('unexpected')}}
    rent.method('onUpdateTotalToPay').call(ctx)
    assert.equal(ctx.form.total_to_pay,null)
    assert.equal(ctx.form.rent_payment.payment,null)
    assert.ok(ctx.errors.rate_price.length)
})

test('hotel checkout sums decimal balances and applies arrears only once on retries', () => {
    const context = {arrears:'0.10', currentRent:{items:[
        {payment_status:'PAID',item:{total:'0.20'}},
        {payment_status:'PAID',item:{total:'0.30'}},
        {payment_status:'DEBT',item:{total:'100.25'}},
        {payment_status:'DEBT',item:{total:'0.05'}},
    ]}}
    hotel.method('onCalculatePaidAndDebts').call(context)
    assert.equal(context.totalPaid, '0.5')
    assert.equal(context.totalDebt, '100.4')
    assert.equal(hotel.method('formatHotelAmount')(context.totalDebt), '100.40')
    globals.calculateRowItem = load('resources/js/helpers/functions.js').calculateRowItem
    const checkout = {arrears:'0.10', percentage_igv:'0.16', room:{item_id:7},
        document:{items:[{item_id:7, quantity:'1', total:'100.25', affectation_igv_type_id:'20',
            item:{id:7,name:'Habitación',unit_price:'100.25',currency_type_id:'VES'},
            price_input:{amount:'100.25',currency_type_id:'VES',includes_iva:true}}]}}
    const update = hotel.method('onUpdateItemsWithExtras')
    update.call(checkout)
    assert.equal(checkout.document.items[0].total, '100.35')
    update.call(checkout)
    assert.equal(checkout.document.items[0].total, '100.35')
    checkout.document.items.push({affectation_igv_type_id:'20', total_base_igv:'0.05',total_value:'0.05',total_igv:'0',total:'0.05'})
    hotel.method('onCalculateTotals').call(checkout)
    assert.equal(checkout.document.total, '100.4')
    assert.equal(checkout.document.total_exonerated, '100.4')
})

test('hotel checkout preserves periodic room amounts through surcharge retries and document totals', () => {
    globals.calculateRowItem = load('resources/js/helpers/functions.js').calculateRowItem
    const third={n:'1',d:'3'}
    const context={arrears:'0.10',percentage_igv:'0.16',room:{item_id:7},document:{items:[{
        item_id:7,quantity:'0.333333333333',total:'0.333333333333',affectation_igv_type_id:'20',
        item:{id:7,name:'Habitación',unit_price:'1',currency_type_id:'VES'},
        quantity_input:{amount:'1',unit_price:'3'},price_input:{amount:'1',currency_type_id:'VES',includes_iva:true},
        calculation_snapshot:{values:{quantity:third,total:third},inputs:{quantity_input:{amount:'1',unit_price:'3'}}},
    }],discounts:[],charges:[]}}
    const update=hotel.method('onUpdateItemsWithExtras')
    update.call(context)
    update.call(context)
    const row=context.document.items[0]
    assert.equal(row.quantity,'1')
    assert.equal(row.item.description,'Habitación x 0.333333333333 noche(s)')
    assert.equal(row.calculation_snapshot.values.total.n,'13')
    assert.equal(row.calculation_snapshot.values.total.d,'30')
    hotel.method('onCalculateTotals').call(context)
    assert.equal(context.document.calculation_snapshot.values.total.n,'13')
    assert.equal(context.document.calculation_snapshot.values.total.d,'30')
    const debt={arrears:'0.10',currentRent:{items:[{payment_status:'DEBT',item:{total:'0.333333333333',
        calculation_snapshot:{values:{total:third}}}}]}}
    hotel.method('onCalculatePaidAndDebts').call(debt)
    assert.equal(debt.totalDebt,'0.433333333333')
})

test('hotel charges late checkout separately when the room was already paid', () => {
    globals.calculateRowItem = load('resources/js/helpers/functions.js').calculateRowItem
    const ctx={arrears:'0.25',percentage_igv:'0.16',room:{item_id:7,item:{item_id:7,quantity:'2',
        affectation_igv_type_id:'10',item:{id:7,name:'Habitación',unit_type_id:'SERV',currency_type_id:'VES',unit_price:'116'}}},
        document:{items:[{item_id:8,hotel_checkout_room:false,total:'3',total_value:'3',total_base_igv:'3',total_igv:'0',affectation_igv_type_id:'20'}],discounts:[],charges:[]}}
    const update=hotel.method('onUpdateItemsWithExtras')
    update.call(ctx)
    update.call(ctx)
    assert.equal(ctx.document.items.length,2)
    assert.equal(ctx.document.items[1].item_id,7)
    assert.equal(ctx.document.items[1].total,'0.25')
    assert.equal(ctx.document.items[1].item.description,'Cargo por salida tardía')
    hotel.method('onCalculateTotals').call(ctx)
    assert.equal(ctx.document.total,'3.25')
    ctx.arrears='0'
    update.call(ctx)
    assert.equal(ctx.document.items.length,1)
    ctx.arrears='0.25'
    ctx.room.item.item.unit_type_id='UND'
    assert.throws(()=>update.call(ctx),/servicio asociado/)
    ctx.room.item.item.unit_type_id='SERV'
    ctx.room.item.item.is_set=true
    assert.throws(()=>update.call(ctx),/servicio asociado/)
    ctx.room.item.item.is_set='0'
    update.call(ctx)
    assert.equal(ctx.document.items[1].total,'0.25')
})

test('hotel checkout retries the same saved document after finalization fails', async () => {
    const storage=new Map(), calls=[], errors=[]
    globals.window={sessionStorage:{setItem:(key,value)=>storage.set(key,value),removeItem:key=>storage.delete(key)}}
    globals.ensureExchangeRateSale=async()=>{}
    let attempts=0, completed=0
    const ctx={loading:false,preparingCheckout:false,pendingCheckout:null,currentRent:{id:7},arrears:'0.25',
        resource_documents:'sale-notes',document:{document_type_id:'80',payments:[]},
        onUpdateItemsWithExtras(){},onCalculateTotals(){},validatePaymentDestination(){return {error_by_item:0}},
        validateTotalPayments(){return {success:true}},updateDataForSend(){},async ensureFiscalPaymentsReady(){return true},
        $http:{async post(url,data){calls.push({url,data}); if (url==='/sale-notes') return {data:{success:true,data:{id:42}}};
            if (++attempts===1) throw Error('sin conexión'); return {data:{success:true,currentRent:{id:7,status:'FINALIZADO'}}}}},
        $message:{error:message=>errors.push(message)},successGoToInvoice(){completed++},$emit(){},saveCashDocument(){}}
    const run=hotel.method('onGoToInvoice')
    await run.call(ctx)
    assert.equal(ctx.pendingCheckout.id,42)
    assert.equal(completed,0)
    assert.equal(storage.size,1)
    await run.call(ctx)
    assert.equal(calls.filter(call=>call.url==='/sale-notes').length,1)
    assert.equal(calls.filter(call=>call.url.endsWith('/finalized')).length,2)
    assert.equal(calls[2].data.checkout_document_id,42)
    assert.equal(calls[2].data.checkout_document_type,'80')
    assert.equal(calls[2].data.arrears,'0.25')
    assert.equal(completed,1)
    assert.equal(storage.size,0)
    assert.deepEqual(errors,['sin conexión'])
})

test('hotel atomic checkout recovers a lost response without creating another document', async () => {
    const storage=new Map(), calls=[], messages=[]
    globals.window={sessionStorage:{setItem:(key,value)=>storage.set(key,value),removeItem:key=>storage.delete(key)}}
    globals.newPaymentOperationKey=()=> 'a98c5b98-5964-4f6e-9cff-7c405690a58b'
    globals.ensureExchangeRateSale=async()=>{}
    let complete=0
    const result={success:true,found:true,data:{id:42,document_type_id:'80'},currentRent:{id:7,status:'FINALIZADO'}}
    const ctx={loading:false,preparingCheckout:false,pendingAtomic:null,atomicDraft:null,currentRent:{id:7},arrears:'0.25',
        document:{document_type_id:'80',payments:[]},onUpdateItemsWithExtras(){},onCalculateTotals(){},
        validatePaymentDestination(){return {error_by_item:0}},validateTotalPayments(){return {success:true}},
        updateDataForSend(){},async ensureFiscalPaymentsReady(){return true},
        $http:{async post(url,data){calls.push({url,data});throw Error('respuesta perdida')},
            async get(url){calls.push({url});return {data:result}}},
        $message:{error:message=>messages.push(message)},successGoToInvoice(){complete++},$emit(){},saveCashDocument(){}}
    ctx.clearAtomicPending=hotel.method('clearAtomicPending')
    ctx.completeAtomicCheckout=hotel.method('completeAtomicCheckout')
    const run=hotel.method('onAtomicCheckout')
    await run.call(ctx)
    assert.equal(ctx.pendingAtomic.key,'a98c5b98-5964-4f6e-9cff-7c405690a58b')
    assert.equal(storage.size,1)
    assert.equal(complete,0)
    await run.call(ctx)
    assert.equal(calls.filter(call=>call.data).length,1)
    assert.match(calls[1].url,/operation\/a98c5b98/)
    assert.equal(ctx.documentNewId,42)
    assert.equal(ctx.currentRent.status,'FINALIZADO')
    assert.equal(storage.size,0)
    assert.equal(complete,1)
    assert.deepEqual(messages,['respuesta perdida'])
    // A reloaded page has only the opaque key, and still recovers the committed result.
    ctx.currentRent={id:7,status:'FINALIZADO'}; ctx.pendingAtomic={key:'a98c5b98-5964-4f6e-9cff-7c405690a58b',type:'80'}
    ctx.atomicDraft=null
    await run.call(ctx)
    assert.equal(calls.filter(call=>call.data).length,1)
    assert.equal(complete,2)
    assert.deepEqual(compiler.compile(hotel.source.template.content).errors, [])
})

test('hotel atomic checkout never sends a document without a recoverable session key', async () => {
    globals.window={sessionStorage:{setItem(){throw Error('denegado')}}}
    globals.newPaymentOperationKey=()=> 'a98c5b98-5964-4f6e-9cff-7c405690a58b'
    globals.ensureExchangeRateSale=async()=>{}
    let posts=0; const messages=[]
    const ctx={loading:false,preparingCheckout:false,pendingAtomic:null,currentRent:{id:7},arrears:'0',
        document:{document_type_id:'80',payments:[]},onUpdateItemsWithExtras(){},onCalculateTotals(){},
        validatePaymentDestination(){return {error_by_item:0}},validateTotalPayments(){return {success:true}},
        updateDataForSend(){},async ensureFiscalPaymentsReady(){return true},$http:{async post(){posts++}},
        $message:{error:message=>messages.push(message)}}
    await hotel.method('onAtomicCheckout').call(ctx)
    assert.equal(posts,0)
    assert.match(messages[0],/clave necesaria/)
})

test('hotel atomic checkout does not recreate a missing draft after a reload', async () => {
    const storage=new Map([['sab-hotel-atomic:7','pending']]), messages=[]; let posts=0
    globals.window={sessionStorage:{removeItem:key=>storage.delete(key)}}
    const ctx={loading:false,preparingCheckout:false,pendingAtomic:{key:'a98c5b98-5964-4f6e-9cff-7c405690a58b',type:'80'},
        atomicDraft:null,currentRent:{id:7},$message:{error:message=>messages.push(message)},
        $http:{async get(){return {data:{success:true,found:false}}},async post(){posts++}}}
    ctx.clearAtomicPending=hotel.method('clearAtomicPending')
    await hotel.method('onAtomicCheckout').call(ctx)
    assert.equal(posts,0)
    assert.equal(ctx.pendingAtomic,null)
    assert.equal(storage.size,0)
    assert.match(messages[0],/Revise los datos/)
})

test('hotel recovers pending operations saved before the SAB rename', async () => {
    const key='a98c5b98-5964-4f6e-9cff-7c405690a58b'
    const storage=new Map([
        ['sab-hotel-atomic:7','invalid json'],
        ['pro9-hotel-atomic:7',JSON.stringify({key,type:'80'})],
        ['pro9-hotel-checkout:7',JSON.stringify({id:42,type:'01'})],
    ])
    globals.window={sessionStorage:{getItem:k=>storage.get(k),removeItem:k=>storage.delete(k),setItem(){throw Error('denegado')}}}
    const read=hotel.method('readPendingHotelOperation')
    const pending=read('atomic',7)
    assert.equal(pending.key,key)
    assert.equal(read('checkout',7).id,42)
    let posts=0
    const ctx={loading:false,preparingCheckout:false,pendingAtomic:pending,atomicDraft:null,currentRent:{id:7},
        $http:{async get(){return {data:{found:true}}},async post(){posts++}},
        completeAtomicCheckout(){this.clearAtomicPending()}}
    ctx.clearAtomicPending=hotel.method('clearAtomicPending')
    await hotel.method('onAtomicCheckout').call(ctx)
    assert.equal(posts,0)
    assert.equal(storage.has('pro9-hotel-atomic:7'),false)
    assert.equal(storage.has('sab-hotel-atomic:7'),false)
})

test('hotel finalizes a legacy checkout without creating another document', async () => {
    const storage=new Map([['pro9-hotel-checkout:7',JSON.stringify({id:42,type:'80',arrears:'0.25'})]])
    globals.window={sessionStorage:{getItem:k=>storage.get(k),removeItem:k=>storage.delete(k)}}
    const pendingCheckout=hotel.method('readPendingHotelOperation')('checkout',7)
    const calls=[]
    const ctx={loading:false,preparingCheckout:false,pendingCheckout,currentRent:{id:7},document:{},
        $http:{async post(url,data){calls.push({url,data});return {data:{success:true,currentRent:{id:7,status:'FINALIZADO'}}}}},
        successGoToInvoice(){},$emit(){},saveCashDocument(){}}
    await hotel.method('onGoToInvoice').call(ctx)
    assert.equal(calls.length,1)
    assert.match(calls[0].url,/finalized$/)
    assert.equal(calls[0].data.checkout_document_id,42)
    assert.equal(storage.size,0)
})

test('hotel product summary adds exact rows without mixing registered items or binary rounding', () => {
    const context = {form:{products:[
        {is_registered:false, total_value:'0.1', total_igv:'0.02', total:'0.12'},
        {is_registered:false, total_value:'0.2', total_igv:'0.03', total:'0.23'},
        {is_registered:true, total_value:'99', total_igv:'0', total:'99'},
    ]}}
    roomItems.method('onCalculateTotals').call(context)
    assert.equal(context.form.subtotal, '0.3')
    assert.equal(context.form.igv, '0.05')
    assert.equal(context.form.total, '0.35')
    context.form.products = [
        {is_registered:false, total_value:'0.333333333333', total_igv:'0', total:'0.333333333333',
            calculation_snapshot:{values:{total_value:{n:'1',d:'3'},total:{n:'1',d:'3'},quantity:{n:'1',d:'3'}}}},
        {is_registered:false, total_value:'0.666666666667', total_igv:'0', total:'0.666666666667',
            calculation_snapshot:{values:{total_value:{n:'2',d:'3'},total:{n:'2',d:'3'}}}},
    ]
    roomItems.method('onCalculateTotals').call(context)
    assert.equal(context.form.total, '1')
    assert.equal(context.form.subtotal, '1')
    assert.equal(roomItems.method('hotelQuantity')(context.form.products[0]), '0.333333333333')
    assert.equal(roomItems.method('hotelAmount')('9007199254740993.01'), '9007199254740993.01')
    assert.deepEqual(compiler.compile(roomItems.source.template.content).errors, [])
})

test('hotel product sale note preserves exact document fractions and large amounts', () => {
    const context = {document:{items:[
        {affectation_igv_type_id:'20',total_base_igv:'0.333333333333',total_value:'0.333333333333',total_igv:'0',total:'0.333333333333',
            calculation_snapshot:{values:{total_base_igv:{n:'1',d:'3'},total_value:{n:'1',d:'3'},total:{n:'1',d:'3'}}}},
        {affectation_igv_type_id:'20',total_base_igv:'0.666666666667',total_value:'0.666666666667',total_igv:'0',total:'0.666666666667',
            calculation_snapshot:{values:{total_base_igv:{n:'2',d:'3'},total_value:{n:'2',d:'3'},total:{n:'2',d:'3'}}}},
    ],discounts:[],charges:[]}}
    roomItems.method('onCalculateTotalsDocument').call(context)
    assert.equal(context.document.total, '1')
    assert.equal(context.document.total_exonerated, '1')
    assert.equal(context.document.calculation_snapshot.values.total.n, '1')
    assert.equal(context.document.calculation_snapshot.values.total.d, '1')
    context.document.items = [{affectation_igv_type_id:'20',total_base_igv:'9007199254740993.01',
        total_value:'9007199254740993.01',total_igv:'0',total:'9007199254740993.01'}]
    roomItems.method('onCalculateTotalsDocument').call(context)
    assert.equal(context.document.total, '9007199254740993.01')
})

test('hotel payment defaults use document currency and do not assign cash to an account method', () => {
    const context = {payment_method_types:[...methods], payment_destinations:destinations, paymentCurrency:'VES',
        document:{currency_type_id:'VES'}, form:{rent_payment:{}}}
    rent.method('setDefaultDataPayments').call(context)
    assert.equal(context.form.rent_payment.payment_method_type_id, null)
    assert.equal(context.form.rent_payment.payment_destination_id, null)
    const result = roomItems.method('getDefaultValuesRentPayment').call(context)
    assert.equal(result.payment_method_type_id, null)
    assert.equal(result.payment_destination_id, null)
    context.payment_method_types.push({id:'04',is_active:true,collection_kind:'cash',currency_type_id:'VES'})
    rent.method('setDefaultDataPayments').call(context)
    assert.equal(context.form.rent_payment.payment_method_type_id, '04')
    assert.equal(roomItems.method('getDefaultValuesRentPayment').call(context).payment_destination_id, 'cash')
    assert.deepEqual(compiler.compile(rent.source.template.content).errors, [])
    assert.deepEqual(compiler.compile(roomItems.source.template.content).errors, [])
})

test('hotel product payment changes clear incompatible destinations without changing registered products', () => {
    const tree = parser.parse(roomItems.source.script.content, {sourceType:'module'})
    const component = tree.program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration
    const watchers = component.properties.find(node => node.key.name === 'watch').value
    const watcher = watchers.properties.find(node => node.key.value === 'form.products').value
    const handler = watcher.properties.find(node => node.key.name === 'handler')
    const run = vm.runInNewContext(`({${generate(handler).code}}).handler`, globals)
    const fresh = {is_registered:false, rent_payment:{payment_method_type_id:'13', currency_type_id:'USD', payment_destination_id:1}}
    const saved = {is_registered:true, rent_payment:{payment_method_type_id:'13', currency_type_id:'USD', payment_destination_id:1}}
    const mixin = globals.default.methods
    const ctx = {payment_destinations:destinations, payment_method_types:methods, form:{currency_type_id:'VES'},
        $set:(target, key, value) => {target[key] = value}}
    ctx.classifiedPaymentDestinations = (items, row) => mixin.classifiedPaymentDestinations.call(ctx, items, row)
    ctx.clearIncompatiblePaymentDestinations = rows => mixin.clearIncompatiblePaymentDestinations.call(ctx, rows)
    run.call(ctx, [fresh, saved])
    assert.equal(fresh.rent_payment.payment_destination_id, 2)
    assert.equal(saved.rent_payment.payment_destination_id, 1)
})

test('hotel checkout suggests a valid classified method and filters account currencies', () => {
    const context = {paymentMethodTypes:methods, paymentDestinations:destinations,
        document:{currency_type_id:'VES',payments:[],total:'100'}, totalDebt:'100'}
    hotel.method('clickAddPayment').call(context)
    const row = context.document.payments[0]
    assert.equal(row.payment_method_type_id, null)
    assert.equal(row.payment_destination_id, null)
    row.payment_method_type_id = '13'
    row.currency_type_id = 'USD'
    assert.deepEqual(Array.from(hotel.method('destinationsFor').call(context, row), item => item.id), [2])
    context.getResponseValidations = (success = true, message = null) => ({success,message})
    row.payment = '100.00'
    assert.equal(hotel.method('validateTotalPayments').call(context).success, true)
    row.payment = '100.01'
    assert.equal(hotel.method('validateTotalPayments').call(context).success, false)
    assert.deepEqual(compiler.compile(hotel.source.template.content).errors, [])
    assert.match(hotel.source.template.content, /<igtf-payment-fields/)
    assert.match(hotel.source.template.content, /<receipt-currency-fields/)
})

test('hotel checkout and order select the configured bank account for new payments', () => {
    const tree = parser.parse(hotel.source.script.content, {sourceType:'module'})
    const declaration = tree.program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration
    const watchers = declaration.properties.find(node => node.key.name === 'watch').value
    const entry = watchers.properties.find(node => node.key.value === 'document.payments').value
    const handler = entry.properties.find(node => node.key.name === 'handler')
    const run = vm.runInNewContext(`({${generate(handler).code}}).handler`, globals)
    const row = {payment_method_type_id:'13',currency_type_id:'USD',payment_destination_id:null}
    const historic = {id:55,payment_method_type_id:'13',currency_type_id:'USD',payment_destination_id:1}
    const ctx = {paymentMethodTypes:methods, paymentDestinations:destinations,document:{currency_type_id:'VES'},
        $set:(target,key,value)=>{target[key]=value}}
    ctx.destinationsFor = payment => hotel.method('destinationsFor').call(ctx,payment)
    run.call(ctx,[row,historic])
    assert.equal(row.payment_destination_id,2)
    assert.equal(historic.payment_destination_id,1)
    assert.equal(hotel.method('isFixedBankDestination').call(ctx,row),true)
    const orderCtx={payment_method_types:methods,payment_destinations:destinations,payment:{payment_method_type_id:'13'}}
    order.method('changePaymentMethod').call(orderCtx)
    assert.equal(orderCtx.payment.payment_destination_id,2)
    assert.equal(order.computed('fixedBankMethod').call(orderCtx),true)
})

test('order sale note submits received amount and rate without replacing them with the document total', async () => {
    const posts = [], errors = []
    const context = {payment_method_types:methods, payment_destinations:destinations, document:{currency_type_id:'VES',total:'100'},
        orderId:42, resource:'sale-notes', $message:{error:message => errors.push(message)},
        validateQuantitySeriesLots:async () => ({success:true}), paymentDate:()=>'2026-10-01',
        $http:{post:async (url, data) => {posts.push({url,data}); return {data:{success:false,message:'test'}}}}}
    order.method('presetPayment').call(context)
    assert.equal(context.payment.payment_method_type_id, null)
    assert.equal(context.payment.payment_destination_id, null)
    Object.assign(context.payment, {payment_method_type_id:'13',payment_destination_id:2,currency_type_id:'USD',original_amount:'2.50',exchange_rate:'40',payment:'100.00'})
    context.availableDestinations = [destinations[2]]
    await order.method('submit').call(context)
    assert.equal(posts.length, 1)
    assert.equal(posts[0].data.payments[0].original_amount, '2.50')
    assert.equal(posts[0].data.payments[0].exchange_rate, '40')
    assert.equal(posts[0].data.payments[0].currency_type_id, 'USD')
    context.availableDestinations = [destinations[1]]
    await order.method('submit').call(context)
    assert.equal(posts.length, 1)
    assert.match(errors.at(-1), /destino/)
    assert.deepEqual(compiler.compile(order.source.template.content).errors, [])
})
