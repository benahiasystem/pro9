const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const babel = require('@babel/core')
const compiler = require('vue-template-compiler')
const runtime = require('./support/exact-runtime.cjs')
const loadModule = require('./helpers/load-module.cjs')
const {resolve} = require('node:path')
const {exactValue} = runtime('exchange-rate-math')
const {restoreSaleNoteContext} = runtime('sale-note-conversion')

for (const moduleName of ['Suscription', 'FullSuscription']) {
    for (const grouped of [true, false]) {
        test(`${moduleName} prepares a VES invoice with exact parts and mixed payments (grouped=${grouped})`, async () => {
            const path = `modules/${moduleName}/Resources/assets/js/payment_receipt/ModalGenerateCPE.vue`
            const source = compiler.parseComponent(fs.readFileSync(path, 'utf8'))
            assert.deepEqual(compiler.compile(source.template.content).errors, [])
            const exports = {}
            const stored = new Map([['itemsForNotes', 'stale']])
            const localStorage = {
                getItem: key => stored.get(key),
                setItem: (key, value) => stored.set(key, value),
                removeItem: key => stored.delete(key),
            }
            const window = {location: {href: ''}}
            const script = babel.transformSync(source.script.content, {configFile: false, babelrc: false,
                plugins: ['@babel/plugin-transform-modules-commonjs']}).code
            vm.runInNewContext(script, {exports, window, localStorage,
                require: name => name.endsWith('/document-number')
                    ? loadModule(resolve('resources/js/helpers/document-number.js'))
                    : runtime(name)})
            const rows = ['500', '499'].map((price, index) => ({
                item_id: 11,
                quantity: '0.333333333333',
                unit_price: price,
                calculation_snapshot: {version: 1, values: {
                    quantity: {n: '1', d: '3'},
                    unit_price: {n: price, d: '1'},
                    total: {n: price, d: '3'},
                }},
                source_sale_note_items: [{sale_note_id: index + 1, sale_note_item_id: index + 5}],
            }))
            const payments = [
                {id: 3, sale_note_id: 1, currency_type_id: 'USD', original_amount: '2',
                    exchange_rate: '40.12345678', payment: '80.25'},
                {id: 4, sale_note_id: 2, currency_type_id: 'VES', original_amount: '10', payment: '10'},
            ]
            const requests = []
            const errors = []
            let closed = false
            const context = {
                form: {client_id: 8, selecteds: [1, 2]},
                group_items_generate_document: grouped,
                clients: [{id: 8}],
                notes: [{id: 1, series: 'NV01', number: 21, selected: true},
                    {id: 2, series: 'NV01', number: 22, selected: true}],
                onClose() { closed = true },
                $message: {error: error => errors.push(error)},
                axiosError: error => errors.push(error.message),
                $http: {post: async (url, payload) => {
                    requests.push({url, payload})
                    return {data: {
                        data: grouped ? [{item_id: 11, quantity: '0.666666666666'}] : rows,
                        grouped_items: rows,
                        payments,
                        conversion_context: {customer_id: 8, establishment_id: 9,
                            currency_type_id: 'VES', exchange_rate_sale: '40.12345678'},
                    }}
                }},
            }
            const methods = exports.default.methods
            for (const [name, method] of Object.entries(methods)) {
                if (!context[name]) context[name] = method.bind(context)
            }
            await context.onFetchNoteItems()
            await new Promise(setImmediate)

            assert.deepEqual(errors, [])
            assert.equal(requests.length, 1)
            assert.equal(requests[0].url, '/sale-notes/items')
            assert.deepEqual(Array.from(requests[0].payload.notes_id), [1, 2])
            assert.equal(Boolean(requests[0].payload.select_all), !grouped)
            assert.ok(stored.has('itemsNotGroupForNotes'), 'the invoice must receive the conserved rows')
            assert.equal(stored.has('itemsForNotes'), false)
            const transferred = JSON.parse(stored.get('itemsNotGroupForNotes'))
            assert.deepEqual(transferred, rows)
            assert.equal(exactValue(transferred[0], 'quantity').times(3).decimal(), '1')
            assert.equal(exactValue(transferred[0], 'total').plus(exactValue(transferred[1], 'total')).decimal(), '333')
            assert.deepEqual(JSON.parse(stored.get('notes')).map(note => note.id), [1, 2])
            const invoice = {form: {currency_type_id: 'VES'}}
            assert.ok(restoreSaleNoteContext(invoice, localStorage))
            assert.equal(invoice.form.currency_type_id, 'VES')
            assert.equal(invoice.form.exchange_rate_sale, '40.12345678')
            assert.deepEqual(JSON.parse(JSON.stringify(invoice.form.payments)), payments)
            assert.ok(closed)
            assert.equal(context.loading, false)
            assert.equal(window.location.href, '/documents/create')
        })
    }
}
