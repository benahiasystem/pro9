const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const compiler = require('vue-template-compiler')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default
const load = require('./helpers/load-module.cjs')

const globals = {...load('resources/js/helpers/payment-classification.js'),
    ...load('resources/js/helpers/document-creation.js'), ...load('resources/js/helpers/exchange-rate-math.js'),
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
const methods = [{id:'01', is_active:true, is_credit:true, collection_kind:'none', currency_type_id:'VES'},
    {id:'13', is_active:true, is_credit:false, collection_kind:'account', currency_type_id:'USD'}]
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

test('hotel check-in preserves the exact rate when preparing the document line', async () => {
    globals.calculateRowItem = item => item
    const ctx = {form:{duration:1,total_to_pay:'90071992547409.91'}, percentage_igv:'0.16',
        document:{items:[{item:{description:'Habitación'}}]}, axiosError:error => { throw error }}
    await rent.method('onUpdateItemsWithExtras').call(ctx)
    assert.equal(ctx.document.items[0].item.unit_price, '90071992547409.91')
    assert.equal(ctx.document.items[0].input_unit_price_value, '90071992547409.91')
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
    checkout.document.items.push({affectation_igv_type_id:'20', total_value:'0.05',total_igv:'0',total:'0.05'})
    hotel.method('onCalculateTotals').call(checkout)
    assert.equal(checkout.document.total, '100.40')
    assert.equal(checkout.document.total_exonerated, '100.40')
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
    assert.equal(fresh.rent_payment.payment_destination_id, null)
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
