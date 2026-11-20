const {test} = require('node:test')
const assert = require('node:assert/strict')
const {methods} = require('./support/pos-invoice-runtime.cjs')
const fs = require('node:fs')
const vm = require('node:vm')
const compiler = require('vue-template-compiler')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default


for (const component of ['index', 'fast', 'garage']) {
    test(`${component} reloads the catalog for the current document type`, async () => {
        let send
        const requests = []
        const prices = [{sale_unit_price: '0.666666666667',
            calculation_snapshot: {version: 1, values: {sale_unit_price: {n: '2', d: '3'}}}}]
        const form = {document_type_id: '01', currency_type_id: 'VES'}
        const context = {form, resource: 'pos', all_items: [], fixItems() {}, filterItems() {},
            $http: {get(path, config) {
                requests.push([path, config.params.document_type_id])
                return new Promise(resolve => { send = resolve })
            }}}
        const reload = methods(component, ['reloadDataItems']).reloadDataItems.bind(context)
        const pending = reload()
        send({data: prices})
        await pending
        assert.equal(context.all_items, prices)
        const stale = reload()
        form.document_type_id = '80'
        send({data: [{sale_unit_price: 'invoice request in flight'}]})
        await stale
        assert.equal(context.all_items, prices)
        const note = reload()
        send({data: [{sale_unit_price: '1'}]})
        await note
        assert.equal(context.all_items[0].sale_unit_price, '1')
        form.document_type_id = '01'
        const earlier = reload(), sendEarlier = send
        const latest = reload()
        send({data: prices})
        await latest
        sendEarlier({data: [{sale_unit_price: 'older invoice request'}]})
        await earlier
        assert.equal(context.all_items, prices)
        assert.deepEqual(requests, [
            ['/pos/table/items', '01'], ['/pos/table/items', '01'], ['/pos/table/items', '80'],
            ['/pos/table/items', '01'], ['/pos/table/items', '01'],
        ])
    })
}

for (const component of ['index', 'fast', 'garage']) {
    test(`${component} clears refreshed prices and reloads when the document type changes`, () => {
        const source = compiler.parseComponent(fs.readFileSync(`resources/js/views/tenant/pos/${component}.vue`, 'utf8'))
        const definition = parser.parse(source.script.content, {sourceType: 'module'}).program.body
            .find(node => node.type === 'ExportDefaultDeclaration').declaration.properties
        const watcher = definition.find(node => node.key?.name === 'watch').value.properties
            .find(node => node.key?.value === 'form.document_type_id')
        const changeType = vm.runInNewContext(`({${generate(watcher).code}})['form.document_type_id']`)
        let reloads = 0
        const context = {all_items: [{}], items: [{}], reloadDataItems() { reloads++ }}
        changeType.call(context, '80', '01')
        assert.equal(reloads, 0)
        context.posCatalogReload = {}
        changeType.call(context, '80', '01')
        assert.equal(reloads, 1)
        assert.equal(context.all_items.length, 0)
        assert.equal(context.items.length, 0)
    })
}
