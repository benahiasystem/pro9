const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const Vue = require('vue')
const {parseComponent, compile} = require('vue-template-compiler')
const babel = require('@babel/core')
const load = require('./helpers/load-module.cjs')
const {exactValue, exactSnapshot} = load(path.resolve('resources/js/helpers/exchange-rate-math.js'))
const {suggestInstallments} = load(path.resolve('resources/js/helpers/document-creation.js'))

const component = parseComponent(fs.readFileSync('resources/js/components/secondary/ListRestrictItems.vue', 'utf8'))
const exportsByComponent = {}
vm.runInNewContext(babel.transformSync(component.script.content, {
    configFile: false, babelrc: false, plugins: ['@babel/plugin-transform-modules-commonjs'],
}).code, {
    exports: exportsByComponent,
    require: dependency => load(path.resolve('resources/js', dependency.slice(1) + '.js')),
})
assert.deepEqual(compile(component.template.content).errors, [])

function draft(items, discounts = [], fields = {}) {
    const errors = []
    const view = new Vue({
        methods: exportsByComponent.default.methods,
        data: () => ({form: {document_type_id: '01', items, discounts, ...fields}}),
    })
    view.$message = {error: message => errors.push(message)}
    return {view, errors}
}

const exempt = value => ({total_value: value, total_base_igv: value, affectation_igv_type_id: '20'})
const restricted = {...exempt('4'), item: {restrict_sale_cpe: true}}

test('removing a restricted item keeps the fixed global discount fraction and the source note intact', () => {
    const original = {n: '3349999999999', d: '10000000000000'}
    const items = [exempt('1'), restricted]
    const discount = {is_amount: true, discount_type_id: '03', amount: '0.335', amount_exact: original}
    const {view, errors} = draft(items, [discount])
    view.clickRemoveItem(1)
    assert.deepEqual(errors, [])
    assert.equal(items.length, 2)
    assert.equal(view.form.items.length, 1)
    assert.equal(JSON.stringify(view.form.discounts[0].amount_exact), JSON.stringify(original))
    assert.equal(view.form.total_payable, '0.67')
    assert.equal(discount.base, undefined)
    view.$destroy()
})

for (const isAmount of [false, 0]) {
    test(`percentage discounts in mode ${isAmount} include the original taxable base already distributed`, () => {
        const taxed = {affectation_igv_type_id: '10', total_value: '2.9', total_base_igv: '2.9', total_igv: '0.464',
            total_discount: '0.1', discounts: [{from_global_distribution: true, discount_type_id: '00', amount: '0.1'}]}
        const {view, errors} = draft([taxed, restricted], [{is_amount: isAmount, percentage: '50',
            discount_type_id: '02', factor: '0.5', amount: '5'}])
        view.clickRemoveItem(1)
        assert.deepEqual(errors, [])
        assert.equal(view.form.discounts[0].amount, '1.5')
        assert.equal(view.form.discounts[0].base, '3')
        assert.equal(view.form.discounts[0].is_amount, isAmount)
        assert.equal(view.form.total_igv, '0.24')
        assert.equal(view.form.total_payable, '1.74')
        view.$destroy()
    })
}

test('rejects removal atomically when the saved discount exceeds the remaining base', () => {
    const items = [exempt('1'), restricted]
    const discount = {is_amount: true, discount_type_id: '03', amount: '2'}
    const {view, errors} = draft(items, [discount], {total: '3'})
    view.clickRemoveItem(1)
    assert.equal(errors.length, 1)
    assert.equal(view.form.items, items)
    assert.equal(view.form.discounts[0], discount)
    assert.equal(view.form.total, '3')
    view.$destroy()
})

test('a new credit installment follows the remaining exact advance and preserves recorded collections', () => {
    const receipt = {id: 7, payment: '0.3'}
    const fees = [{id: null, amount: '0'}]
    const {view, errors} = draft([exempt('2'), restricted], [], {
        payment_condition_id: '02', payments: [receipt], fee: fees, total: '5.666666666667',
        total_prepayment: '0.333333333333', calculation_snapshot: exactSnapshot({total_prepayment: {n: '1', d: '3'}}),
    })
    view.$on('items-changed', () => suggestInstallments(view.form))
    view.clickRemoveItem(1)
    assert.deepEqual(errors, [])
    assert.equal(exactValue(view.form, 'total').compare({n: '5', d: '3'}), 0)
    assert.equal(view.form.fee[0].amount, '1.37')
    assert.equal(view.form.payments[0], receipt)
    assert.equal(receipt.payment, '0.3')
    view.$destroy()
})

test('the converter accepts the saved note JSON objects for header charges and line discounts', () => {
    const row = {...exempt('2'), discounts: {}}
    const charge = {charge_type_id: '50', amount: '0.333333333333', amount_exact: {n: '1', d: '3'}}
    const charges = {0: charge}
    const {view, errors} = draft([row, restricted], {}, {charges})
    view.clickRemoveItem(1)
    assert.deepEqual(errors, [])
    assert.equal(view.form.items[0], row)
    assert.equal(view.form.charges, charges)
    assert.equal(exactValue(view.form, 'total').compare({n: '7', d: '3'}), 0)
    view.$destroy()
})

test('neutral distributed discounts do not reduce the outstanding taxable discount', () => {
    const taxed = {affectation_igv_type_id: '10', total_value: '70', total_base_igv: '90', total_igv: '14.4',
        total_discount: '30', discounts: [
            {from_global_distribution: true, discount_type_id: '00', amount: '10'},
            {from_global_distribution: true, discount_type_id: '01', amount: '20'},
        ]}
    const discounts = [{discount_type_id: '02', amount: '20'}, {discount_type_id: '03', amount: '20'}]
    const {view, errors} = draft([taxed, restricted], discounts)
    view.clickRemoveItem(1)
    assert.deepEqual(errors, [])
    assert.equal(view.form.total_taxed, '80')
    assert.equal(view.form.total_igv, '12.8')
    assert.equal(view.form.total_payable, '72.8')
    view.$destroy()
})
