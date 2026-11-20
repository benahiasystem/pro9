const assert = require('node:assert/strict')
const fs = require('node:fs')
const test = require('node:test')
const vm = require('node:vm')
const babel = require('@babel/core')
const {parseComponent} = require('vue-template-compiler')
const exactRuntime = require('./support/exact-runtime.cjs')

function methods(name) {
    const source = fs.readFileSync('resources/js/views/tenant/documents/partials/' + name + '.vue', 'utf8')
    const {script} = parseComponent(source)
    const exportsForTest = {}
    const code = babel.transformSync(script.content, {
        configFile: false,
        babelrc: false,
        plugins: ['@babel/plugin-transform-modules-commonjs'],
    }).code
    vm.runInNewContext(code, {exports: exportsForTest, require: name => exactRuntime(name) ?? {}, _: require('lodash')})
    return exportsForTest.default.methods
}
const getPayment = values => methods('dialog_link_payment').getPayment.call(values)

test('invoice payment links convert to bolivars before rounding once to cents', () => {
    assert.equal(String(getPayment({currencyTypeId: 'USD', payment: '0.29', exchangeRateSale: '1.5'})), '0.44')
    assert.equal(String(getPayment({currencyTypeId: 'USD', payment: '900719925474099.31', exchangeRateSale: '10'})), '9007199254740993.1')
})

test('invoice payment links preserve the bolivar amount and tolerate an unloaded dialog', () => {
    assert.equal(getPayment({currencyTypeId: 'VES', payment: '1.20', exchangeRateSale: '1.5'}), '1.20')
    assert.equal(getPayment({}), '')
})

test('a new VES payment link requires a positive decimal in the supported range', () => {
    const paymentMethods = methods('payments')
    const invoicePayment = {payment_destination_id: 1, payment_method_type_id: '01', currency_type_id: 'VES'}
    for (const [payment, valid] of [['0.01', true], ['9007199254740993.01', true], ['0.000000000001', true],
        ['0', false], ['-0.01', false], ['abc', false], ['0x10', false], [true, false], ['0.0000000000001', false]]) {
        assert.equal(paymentMethods.validateDataPayment({...invoicePayment, payment}).success, valid, String(payment))
    }
})

test('the invoice warehouse selector distinguishes adjacent bigint identifiers', async () => {
    const {create} = methods('select_warehouses')
    const warehouses = [12, '9007199254740992', '9007199254740993'].map(warehouse_id => ({warehouse_id, checked: false}))
    for (const [isUpdateWarehouseId, expected] of [['12', 12], ['9007199254740993', '9007199254740993'], [9007199254740992, undefined]]) {
        await create.call({warehouses, isUpdateWarehouseId})
        assert.equal(warehouses.find(row => row.checked)?.warehouse_id, expected)
    }
})
