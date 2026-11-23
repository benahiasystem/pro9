const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const {test} = require('node:test')
const compiler = require('vue-template-compiler')
const Vue = require('vue')
const load = require('./helpers/load-module.cjs')
const {methods, calculateRowItem, math} = require('./support/pos-invoice-runtime.cjs')
const {documentTotals} = load(path.resolve('resources/js/helpers/exact-document.js'))

const template = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/pos/index.vue', 'utf8')).template.content
const start = template.indexOf('<div class="pos-cart__totals">')
const end = template.indexOf('\n                <button', start)
const pay = template.match(/<span class="pos-cart__pay-amount">[\s\S]*?<\/span>/)[0]
const render = compiler.compileToFunctions('<div>' + template.slice(start, end) + pay + '</div>')
const text = node => node.text ?? (node.children || []).map(text).join('')

function cart(row, documentType = '01') {
    return new Vue({
        ...render,
        data: {form: {document_type_id: documentType, ...documentTotals([row])}, currency_type: {symbol: 'Bs.'}},
        methods: methods('index', ['money', 'cartSummaryValue', 'cartSummaryMoney']),
    })
}

function amounts(view) {
    const [summary, payment] = view._render().children.filter(node => node.tag)
    return {rows: summary.children.filter(node => node.tag).map(node => text(node).trim()), pay: text(payment).trim()}
}

test('POS invoice cart shows exact positive bases and IVA projected to zero', () => {
    const row = calculateRowItem({
        item: {unit_price: '0.000000000001', currency_type_id: 'VES'},
        quantity: '0.333333333333', quantity_input: {amount: '1', unit_price: '3'}, affectation_igv_type_id: '10',
    }, 'VES', '1')
    const view = cart(row)
    assert.equal(view.form.total_taxed, '0')
    assert.equal(view.form.total_igv, '0')
    assert.ok(math.exactValue(view.form, 'total_taxed').compare(0) > 0)
    assert.deepEqual(amounts(view).rows, ['Base imponible Bs. 0.00', 'IVA Bs. 0.00', 'TOTAL Bs. 0.00'])
    view.$destroy()
})

test('POS invoice cart rounds the payable amount directly from the exact fraction', () => {
    const row = calculateRowItem({
        item: {unit_price: '0.005', currency_type_id: 'VES'}, quantity: '1', affectation_igv_type_id: '20',
        price_input: {amount: {n: '99999999999', d: '20000000000000'}, currency_type_id: 'VES', includes_iva: true},
    }, 'VES', '1')
    const view = cart(row)
    assert.equal(view.form.total, '0.005')
    assert.equal(math.exactValue(view.form, 'total').final(2), '0.00')
    assert.deepEqual(amounts(view), {rows: ['Total exento Bs. 0.00', 'TOTAL Bs. 0.00'], pay: 'Bs. 0.00'})
    view.form.document_type_id = '80'
    assert.deepEqual(amounts(view), {rows: ['Total exento Bs. 0.01', 'TOTAL Bs. 0.01'], pay: 'Bs. 0.01'})
    view.form.total = '1.005'
    assert.deepEqual(amounts(view), {rows: ['Total exento Bs. 0.01', 'TOTAL Bs. 1.00'], pay: 'Bs. 1.00'})
    view.$destroy()
})
