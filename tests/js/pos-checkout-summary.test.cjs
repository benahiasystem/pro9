const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const {test} = require('node:test')
const compiler = require('vue-template-compiler')
const Vue = require('vue')
const load = require('./helpers/load-module.cjs')
const {methods} = require('./support/pos-invoice-runtime.cjs')
const settlement = load(path.resolve('resources/js/mixins/payment-settlement-summary.js')).default

const text = node => node.text ?? (node.children || []).map(text).join('')
const belowHalfCent = {n: '99999999999', d: '20000000000000'}

function checkout(name) {
    const source = fs.readFileSync('resources/js/views/tenant/pos/partials/' + name + '.vue', 'utf8')
    const {content} = compiler.parseComponent(source).template
    let fragment
    if (name === 'payment') {
        const start = content.indexOf('<div class="pos-checkout__totals">')
        const end = content.indexOf('<div class="pos-checkout__grand-total">', start)
        const row = content.match(/<span class="pos-checkout__item-total">[\s\S]*?<\/span>/)[0]
        fragment = row + content.slice(start, end)
    } else {
        const label = content.indexOf(name === 'fast_payment' ? 'SUBTOTAL' : 'Subtotal')
        fragment = content.slice(content.lastIndexOf('<template>', label), content.indexOf('</template>', label) + '</template>'.length)
    }
    const {money} = methods('partials/' + name, ['money'])
    return new Vue({
        ...compiler.compileToFunctions('<div>' + fragment + '</div>'),
        methods: {
            ...settlement.methods,
            // Each loader evaluates imports in its own VM, so pass a portable fraction between them.
            money(value) { return money.call(this, value?.fraction ? value.fraction() : value) },
        },
        data: {
            currencyTypeActive: {symbol: 'Bs.'}, ivaPercentageLabel: '16',
            item: {total: '0.005', calculation_snapshot: {version: 1, values: {total: belowHalfCent}}},
            form: {document_type_id: '01', total_taxed: '0.005', total_igv: '0.005', total_discount: '0',
                calculation_snapshot: {version: 1, values: {
                    total_taxed: belowHalfCent, total_igv: belowHalfCent, total_discount: {n: '1', d: '30000000000000'},
                }}},
        },
    })
}

for (const name of ['payment', 'fast_payment', 'fast_payment_garage']) {
    test(name + ' rounds checkout amounts from invoice snapshots and retains the sales note projection', () => {
        const view = checkout(name)
        try {
            const invoice = text(view._render())
            assert.equal((invoice.match(/Bs\. 0\.01/g) || []).length, 0)
            assert.equal((invoice.match(/Bs\. 0\.00/g) || []).length, {
                payment: 4, fast_payment: 2, fast_payment_garage: 3,
            }[name])
            if (name === 'payment') assert.match(invoice, /Descuento\s*- Bs\. 0\.00/)
            view.form.document_type_id = '80'
            const note = text(view._render())
            assert.equal((note.match(/Bs\. 0\.01/g) || []).length, name === 'payment' ? 3 : 2)
            if (name === 'payment') assert.doesNotMatch(note, /Descuento/)
        } finally {
            view.$destroy()
        }
    })
}
