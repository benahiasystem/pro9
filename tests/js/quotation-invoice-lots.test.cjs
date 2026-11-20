const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const {test} = require('node:test')
const vm = require('node:vm')
const compiler = require('vue-template-compiler')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default
const loadModule = require('./helpers/load-module.cjs')

function component(name) {
    const file = path.resolve('resources/js/views/tenant/quotations/partials', name + '.vue')
    const source = compiler.parseComponent(fs.readFileSync(file, 'utf8'))
    const tree = parser.parse(source.script.content, {sourceType: 'module'})
    const methods = tree.program.body.find(node => node.type === 'ExportDefaultDeclaration')
        .declaration.properties.find(node => node.key?.name === 'methods').value.properties
    const bindings = {_: require('lodash')}
    for (const entry of tree.program.body.filter(node => node.type === 'ImportDeclaration' && /\/(invoice-lots|lotValidation|exchange-rate-math)$/.test(node.source.value))) {
        const modulePath = entry.source.value.startsWith('@helpers/')
            ? path.resolve('resources/js/helpers', path.basename(entry.source.value) + '.js')
            : path.resolve(path.dirname(file), entry.source.value + '.js')
        const module = loadModule(modulePath)
        for (const {imported, local} of entry.specifiers) {
            bindings[local.name] = module[imported.name]
        }
    }
    return {
        template: source.template.content,
        method(name) {
            const method = methods.find(node => node.key?.name === name)
            return vm.runInNewContext('({' + generate(method).code + '}).' + name, bindings)
        },
    }
}
const options = component('options')
const validate = options.method('validateQuantityandSeriesLots')

test('quotation conversion blocks invoice submission when lot totals differ at the twelfth decimal', async () => {
    const messages = []
    const state = {
        document: {document_type_id: '01'},
        form: {quotation: {items: [{
            quantity: '1.000000000001',
            item: {description: 'Producto con lotes', lots_enabled: true},
            IdLoteSelected: [{id: 17, compromise_quantity: '1'}],
        }]}},
        validateQuantityandSeriesLots: validate,
        assignDocument: () => assert.fail('A mismatched lot must be rejected before assembling the invoice.'),
        $message: {error: message => messages.push(message)},
    }
    await options.method('submit').call(state)
    assert.equal(messages.length, 1)
    assert.match(messages[0], /Producto con lotes/)
})

test('quotation conversion accepts exact invoice lot totals and legacy single lot identifiers', async () => {
    const row = {quantity: '0.3', item: {lots_enabled: true, lots_group: [
        {id: 1, compromise_quantity: '0.1'}, {id: 2, compromise_quantity: '0.2'},
    ]}}
    const state = {document: {document_type_id: '01'}, form: {quotation: {items: [row]}}}
    assert.equal((await validate.call(state)).success, true)
    row.IdLoteSelected = '17'
    assert.equal((await validate.call(state)).success, true)
    row.item.series_enabled = true
    row.item.lots = [{id: 1, has_sale: true}]
    assert.equal((await validate.call(state)).success, false)
})

test('quotation conversion keeps the existing sale note lot validation', async () => {
    const row = {quantity: '1.000000000001', item: {lots_enabled: true},
        IdLoteSelected: [{id: 17, compromise_quantity: '1'}]}
    const state = {document: {document_type_id: 'nv'}, form: {quotation: {items: [row]}}}
    assert.equal((await validate.call(state)).success, true)
    row.IdLoteSelected[0].compromise_quantity = '2'
    assert.equal((await validate.call(state)).success, false)
})

test('quotation invoice serial count rejects a fraction hidden by Number rounding', async () => {
    const serials = Array.from({length: 16384}, (_, index) => ({id: index + 1, has_sale: true}))
    const row = {quantity: '16384.000000000001', item: {series_enabled: true, lots: serials}}
    const state = {document: {document_type_id: '01'}, form: {quotation: {items: [row]}}}
    assert.equal(Number(row.quantity), serials.length)
    const rejected = await validate.call(state)
    assert.equal(rejected.success, false)
    assert.match(rejected.message, /cantidades y series/)
    row.quantity = '16384'
    assert.equal((await validate.call(state)).success, true)
    row.quantity = 'invalid'
    assert.equal((await validate.call(state)).success, false)
    state.document.document_type_id = 'nv'
    row.quantity = '16384.000000000001'
    assert.equal((await validate.call(state)).success, true)
})

test('quotation invoice lot editor keeps fractional quantity text while correcting an assignment', async () => {
    const form = component('series_form')
    const row = {item_id: 13, quantity: '9007199254740993.000000000001', item: {lots_enabled: true},
        IdLoteSelected: [{id: '17', compromise_quantity: '9007199254740993'}]}
    const state = {exactQuantities: true, regularizeCompromiseQuantity: form.method('regularizeCompromiseQuantity'),
        async getLotsGroup() { this.lots_group = [{id: 17, compromise_quantity: '0'}] }}
    assert.equal(form.method('rowNeedsLotAssignment').call(state, row), true)
    await form.method('openDialogLotsGroup').call(state, 0, row)
    assert.equal(state.lots_group_quantity, row.quantity)
    assert.equal(state.lots_group[0].compromise_quantity, '9007199254740993')
    assert.equal(state.showDialogLotsGroup, true)
    assert.match(options.template, /:exact-quantities="document.document_type_id === '01'"/)
    assert.match(form.template, /:exact-quantities="exactQuantities"/)
})
