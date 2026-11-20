const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const compiler = require('vue-template-compiler')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default
const runtime = require('./support/exact-runtime.cjs')

function methods(file, names) {
    const component = compiler.parseComponent(fs.readFileSync(file, 'utf8'))
    const tree = parser.parse(component.script.content, {sourceType: 'module'})
    const options = tree.program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration.properties
    const entries = options.find(node => node.key?.name === 'methods').value.properties
    const source = names.map(name => generate(entries.find(node => node.key?.name === name)).code).join(',')
    return vm.runInNewContext(`({${source}})`, {
        _: require('lodash'),
        ...runtime('invoice-lots'),
        resolveSelectableAffectationType: () => ({id: '10'}),
    })
}

test('a converted VES invoice retains its customer and seller through a mixed-type catalogue refresh', async () => {
    const path = 'resources/js/views/tenant/documents/invoice_generate.vue'
    const handlers = methods(path, ['reloadDataCustomers', 'verifySelectedSeller', 'changeCustomer',
        'shouldProtectPreloadedCustomer', 'ensurePreloadedCustomerInList', 'changeDocumentType', 'cleanCustomer'])
    const payments = [
        {currency_type_id: 'VES', original_amount: '50.00', payment: '50.00'},
        {currency_type_id: 'USD', original_amount: '0.50', payment: '50.00', exchange_rate: '100.00000000'},
    ]
    const customer = {id: '7', seller_id: null, name: 'Cliente de la nota'}
    const state = {
        ...handlers, resource: 'documents', preloadedCustomerId: 7, preloadedCustomer: {id: 7},
        customers: [{id: 7}], all_customers: [{id: '7'}], idUser: 42, sellers: [{id: 9}],
        filteredSellers: [{id: 9}, {id: 42}], config: {},
        form: {currency_type_id: 'VES', operation_type_id: '0101', customer_id: 7, seller_id: '9', payments},
        $http: {get: async (url) => {
            assert.equal(url, '/documents/search/customer/7')
            return {data: {customers: [customer]}}
        }},
        $nextTick: run => run(), buildCustomerAddresses: () => [], selectDefaultCustomerAddress() {},
        getConsigneds() {}, setCustomerAccumulatedPoints() {}, checkCustomerExpiredDebt() {},
        validateDateOfIssue() {}, filterSeries() {}, setDefaultSerieByDocument() {},
        filterCustomers() { this.ensurePreloadedCustomerInList() },
    }
    await state.reloadDataCustomers(7)
    state.verifySelectedSeller()
    state.changeDocumentType()
    assert.equal(state.form.customer_id, '7')
    assert.equal(state.preloadedCustomerId, 7)
    assert.equal(state.form.seller_id, 9)
    assert.equal(state.customers.length, 1)
    assert.equal(state.all_customers.length, 1)
    assert.equal(state.form.currency_type_id, 'VES')
    assert.equal(state.form.payments, payments)

    state.preloadedCustomerId = '9007199254740992'
    state.form.customer_id = '9007199254740993'
    state.changeCustomer()
    assert.equal(state.preloadedCustomerId, null)
    state.form.seller_id = 9007199254740992
    state.filteredSellers = [{id: '9007199254740992'}]
    state.verifySelectedSeller()
    assert.equal(state.form.seller_id, 42)
})

test('invoice variations refresh an equivalent ID and presentation uses exact keys', async () => {
    const path = 'resources/js/views/tenant/documents/partials/item.vue'
    const handlers = methods(path, ['selectVariation', 'changeItem', 'isSelectedPrice'])
    const response = {id: '7', sale_unit_price: '12.004', currency_type_id: 'VES', item_unit_types: []}
    const state = {
        ...handlers, resource: 'documents', form: {}, isFromInvoice: true, config: {}, selectedOptionPrice: 1,
        items: [{id: 7}, {id: '9007199254740992'}, {id: '9007199254740993'}],
        $http: {get: async () => ({data: {items: [response]}})}, $refs: {},
        clearExtraInfoItem() {}, setExtraFieldOfitem: item => item, cleanTotalItem() {},
        hasAttributes: () => false, setExtraElements() {}, getLastPriceItem() {}, addDescriptionToDocumentItem() {},
    }
    await state.selectVariation({id: 7})
    assert.equal(state.items.length, 3)
    assert.equal(state.form.item.id, '7')
    assert.equal(state.form.unit_price_value, '12.004')
    state.item_unit_type = {id: 7}
    assert.equal(state.isSelectedPrice({id: '7'}), true)
    state.item_unit_type = {id: '9007199254740992'}
    assert.equal(state.isSelectedPrice({id: '9007199254740993'}), false)
    assert.equal(state.isSelectedPrice({id: 9007199254740992}), false)
})
