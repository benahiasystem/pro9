const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const {test} = require('node:test')
const compiler = require('vue-template-compiler')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default
const Vue = require('vue')
const {methods, pos} = require('./support/pos-invoice-runtime.cjs')

const customersSource = 'resources/js/views/tenant/pos/partials/fast_payment_garage.vue'
const script = compiler.parseComponent(fs.readFileSync(customersSource, 'utf8')).script.content
const component = parser.parse(script, {sourceType: 'module'}).program.body.find(node => node.type === 'ExportDefaultDeclaration')
const customerWatcher = component.declaration.properties.find(node => node.key?.name === 'watch')
    .value.properties.find(node => node.key?.name === 'customer')
const watch = vm.runInNewContext('({' + generate(customerWatcher).code + '})', {_: require('lodash'), ...pos})

test('Garage clears the customer and discount with an existing customer list', () => {
    let updates = 0
    const view = new Vue({
        watch,
        methods: {...methods('partials/fast_payment_garage', ['customerById']), inputDiscountAmount() { updates++ }},
        data: {
            form: {customer_id: 7}, all_customers: [{id: 7}], customer: {id: 7},
            enabled_discount: true, discount_type: '02', discount_amount: '5', userTouchedDiscount: true,
        },
    })
    try {
        const handler = watch.customer.handler.bind(view)
        assert.doesNotThrow(() => handler(null))
        assert.equal(view.form.customer_id, null)
        assert.equal(view.enabled_discount, false)
        assert.equal(view.discount_type, '01')
        assert.equal(view.discount_amount, 0)
        assert.equal(view.userTouchedDiscount, false)
        assert.equal(view.all_customers.length, 1)
        assert.equal(updates, 1)
    } finally {
        view.$destroy()
    }
})

test('Garage avoids duplicate customers across number and text and preserves adjacent bigint customers', () => {
    const ctx = {
        ...methods('partials/fast_payment_garage', ['customerById']),
        all_customers: [{id: 7}, {id: '9007199254740992'}], form: {customer_id: 7},
        inputDiscountAmount() {},
    }
    watch.customer.handler.call(ctx, {id: '7'})
    assert.deepEqual(ctx.all_customers.map(customer => customer.id), [7, '9007199254740992'])
    assert.equal(ctx.form.customer_id, 7)
    watch.customer.handler.call(ctx, {id: '9007199254740993'})
    assert.deepEqual(ctx.all_customers.map(customer => customer.id), [7, '9007199254740992', '9007199254740993'])
    assert.equal(ctx.form.customer_id, '9007199254740993')
    assert.equal(ctx.customerById('9007199254740993').id, '9007199254740993')
    assert.equal(ctx.customerById(9007199254740992), undefined)
})

test('POS shows and decreases the cart row across ID formats without merging adjacent bigint products', async () => {
    const rows = [{item_id: 7, quantity: '2'},
        {item_id: '9007199254740992', quantity: '4'}, {item_id: '9007199254740993', quantity: '3'}]
    const changes = []
    const ctx = {...methods('index', ['cartQty', 'cartQtyLabel', 'decrementCardItem', 'clickDeleteItem']), form: {items: rows},
        changeCartQuantity(row, index, delta) { changes.push([row.item_id, index, delta]) },
        calculateTotal() {}, setFormPosLocalStorage() {}}
    assert.equal(ctx.cartQtyLabel({item_id: '7'}), '2')
    assert.equal(ctx.cartQtyLabel({item_id: '9007199254740993'}), '3')
    await ctx.decrementCardItem({item_id: '7'})
    await ctx.decrementCardItem({item_id: '9007199254740993'})
    await ctx.decrementCardItem({item_id: 9007199254740992})
    assert.deepEqual(changes, [[7, 0, -1], ['9007199254740993', 2, -1]])
    await ctx.clickDeleteItem({item_id: '7'})
    assert.deepEqual(ctx.form.items.map(row => row.item_id), ['9007199254740992', '9007199254740993'])
})

test('the POS invoice card keeps controls for a positive quantity below display precision', () => {
    const source = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/pos/index.vue', 'utf8'))
    const template = compiler.compile(source.template.content)
    assert.deepEqual(template.errors, [])
    const condition = source.template.content.match(/v-if="([^"]+)"\s+class="pos-m-card-qty"/)[1]
    const isVisible = vm.runInNewContext(`(function (item) { with (this) { return ${condition} } })`)
    const item = {item_id: '7'}
    const row = {item_id: 7, unit_type_id: 'KGM', quantity: '0',
        quantity_input: {amount: '0.000000000001', unit_price: '3'},
        calculation_snapshot: {version: 1, values: {quantity: {n: '1', d: '3000000000000'}}}}
    assert.equal(pos.posInvoiceQuantityError(row), null)
    const ctx = {...methods('index', ['cartQty', 'cartQtyLabel', 'cartHasQuantity']),
        form: {document_type_id: '01', currency_type_id: 'VES', items: [row]}}
    assert.equal(isVisible.call(ctx, item), true)
    assert.equal(ctx.cartQtyLabel(item), '<0.000000000001')
    assert.equal(isVisible.call(ctx, {item_id: '8'}), false)
    assert.equal(ctx.cartQtyLabel({item_id: '8'}), '0')

    ctx.form.document_type_id = '80'
    assert.equal(isVisible.call(ctx, item), false)
    assert.equal(ctx.cartQtyLabel(item), '0')
})
