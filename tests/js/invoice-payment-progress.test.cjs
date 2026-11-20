// ######## INICIO RESUMEN PAGADO Y FALTA ########
const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs'), vm = require('node:vm')
const Vue = require('vue'), compiler = require('vue-template-compiler'), babel = require('@babel/core')
const runtime = require('./support/exact-runtime.cjs')
const {exactSnapshot, exactAmount} = runtime('exchange-rate-math')
const source = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/partials/payment_progress.vue', 'utf8'))
const exported = {}
vm.runInNewContext(babel.transformSync(source.script.content, {configFile:false, babelrc:false,
    plugins:['@babel/plugin-transform-modules-commonjs']}).code, {exports:exported, require:runtime})
const component = exported.default

function view(props = {}) {
    const state = Vue.observable({document:{currency_type_id:'VES', total_payable:'116.00', payments:[]},
        currencyTypes:[{id:'VES', symbol:'Bs.'}, {id:'USD', symbol:'$'}], ...props})
    return new Vue({...component, ...compiler.compileToFunctions(source.template.content), propsData:state})
}
function text(node) { return (node.text || '') + (node.children || []).map(text).join('') }
function value(instance, id) {
    function find(node) {
        if (node.data?.attrs?.['data-testid'] === id) return node
        return (node.children || []).map(find).find(Boolean)
    }
    return text(find(instance._render(), id))
}

test('empty and zero payment rows show zero paid and the full outstanding amount', async () => {
    assert.deepEqual(compiler.compile(source.template.content).errors, [])
    assert.deepEqual(compiler.compile(compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/invoice_generate.vue', 'utf8')).template.content).errors, [])
    const instance = view()
    assert.match(text(instance._render()), /Pagado.*Bs\. 0\.00.*Falta.*Bs\. 116\.00/)
    instance.document.payments.push({payment:'0'}, {payment:''})
    await Vue.nextTick()
    assert.equal(value(instance, 'invoice-paid'), 'Bs. 0.00')
    assert.equal(value(instance, 'invoice-missing'), 'Bs. 116.00')
    instance.document.total_payable = '250.00'
    await Vue.nextTick()
    assert.equal(value(instance, 'invoice-missing'), 'Bs. 250.00')
    instance.$destroy()
})

test('mixed subject dollar payments include collected IGTF and close after the remaining bolívares', async () => {
    const quote = {principal_applied:'27133.46', tax_in_document_currency:'814.00', balance:'27133.45', total_with_tax:'55080.91'}
    const instance = view({quote, document:{currency_type_id:'VES', total_payable:'54266.91', payments:[
        {payment:'27133.46', original_amount:'30', currency_type_id:'USD', exchange_rate:'904.4485'},
    ]}})
    const id = instance._uid
    const before = JSON.stringify(instance.document)
    assert.equal(value(instance, 'invoice-paid'), 'Bs. 27947.46')
    assert.equal(value(instance, 'invoice-missing'), 'Bs. 27133.45')
    assert.equal(JSON.stringify(instance.document), before, 'presentation must not create or modify receipts')
    instance.quote = {...quote, principal_applied:'54266.91', balance:'0.00'}
    await Vue.nextTick()
    assert.equal(value(instance, 'invoice-paid'), 'Bs. 55080.91')
    assert.equal(value(instance, 'invoice-missing'), 'Bs. 0.00')
    assert.equal(instance._uid, id)
    instance.quote = null
    await Vue.nextTick()
    assert.equal(value(instance, 'invoice-paid'), '—', 'a new positive receipt must wait for the server evaluation')
    instance.document.payments.splice(0)
    await Vue.nextTick()
    assert.equal(value(instance, 'invoice-paid'), 'Bs. 0.00')
    assert.equal(value(instance, 'invoice-missing'), 'Bs. 54266.91')
    instance.$destroy()
})

test('the server net applied amount excludes cash change and preserves the settled principal', () => {
    const instance = view({document:{currency_type_id:'VES', total_payable:'5000.00', payments:[
        {payment:'5385.23', currency_type_id:'USD', original_amount:'6.00', change:'0.43'},
    ]}, quote:{principal_applied:'5000.00', tax_in_document_currency:'152.58', balance:'0.00'}})
    assert.equal(value(instance, 'invoice-paid'), 'Bs. 5152.58')
    assert.equal(value(instance, 'invoice-missing'), 'Bs. 0.00')
    instance.$destroy()
})

test('USD document amounts convert to bolívares using the document rate before presentation', async () => {
    const instance = view({document:{currency_type_id:'USD', exchange_rate_sale:'40.12345678', total_payable:'5.00', payments:[]},
        quote:{principal_applied:'2.00', tax_in_document_currency:'0.03', balance:'3.00'}})
    assert.equal(value(instance, 'invoice-paid'), 'Bs. 81.45')
    assert.equal(value(instance, 'invoice-missing'), 'Bs. 120.37')
    instance.document.exchange_rate_sale = '50'
    await Vue.nextTick()
    assert.equal(value(instance, 'invoice-paid'), 'Bs. 101.50')
    assert.equal(value(instance, 'invoice-missing'), 'Bs. 150.00')
    instance.document.exchange_rate_sale = '0'
    await Vue.nextTick()
    assert.equal(value(instance, 'invoice-paid'), '—')
    assert.equal(value(instance, 'invoice-missing'), '—')
    instance.$destroy()
})

test('previous receipts without new payments use their applied snapshots without charging another IGTF', async () => {
    const instance = view({document:{currency_type_id:'VES', total_payable:'1.00', payments:[
        {receipt_origin:{id:1}, payment:'0.333333333333', tax_amount:'0.10', calculation_snapshot:exactSnapshot({payment:exactAmount(1).dividedBy(3)})},
        {id:2, payment:'0.666666666667', tax_amount:'0.20', calculation_snapshot:exactSnapshot({payment:exactAmount(2).dividedBy(3)})},
        {id:3, payment:'100', reversed_at:'2026-11-12'},
    ]}})
    assert.equal(value(instance, 'invoice-paid'), 'Bs. 1.00')
    assert.equal(value(instance, 'invoice-missing'), 'Bs. 0.00')
    instance.document.payments.splice(0, 1)
    await Vue.nextTick()
    assert.equal(value(instance, 'invoice-paid'), 'Bs. 0.67')
    assert.equal(value(instance, 'invoice-missing'), 'Bs. 0.33')
    instance.$destroy()
})

test('pending evaluations retain labelled amounts without a blank flash and failures stay unavailable', async () => {
    const instance = view({quote:{principal_applied:'50', tax_in_document_currency:'1.5', balance:'66'}})
    const id = instance._uid
    instance.pending = true
    instance.quote = null
    instance.document.currency_type_id = 'USD'
    instance.document.exchange_rate_sale = '40'
    await Vue.nextTick()
    assert.equal(value(instance, 'invoice-paid'), 'Bs. 51.50', 'do not reinterpret the previous quote in the new document currency')
    assert.equal(value(instance, 'invoice-missing'), 'Bs. 66.00')
    assert.match(text(instance._render()), /Evaluando/)
    instance.quote = {principal_applied:'2', tax_in_document_currency:'0.06', balance:'1'}
    instance.pending = false
    await Vue.nextTick()
    assert.equal(value(instance, 'invoice-paid'), 'Bs. 82.40')
    assert.equal(value(instance, 'invoice-missing'), 'Bs. 40.00')
    instance.error = 'No se pudo evaluar el cobro.'
    await Vue.nextTick()
    assert.equal(value(instance, 'invoice-paid'), '—')
    assert.equal(value(instance, 'invoice-missing'), '—')
    assert.equal(instance._uid, id)
    instance.$destroy()
})

test('a replacement draft cannot display the previous draft paid amount while loading', async () => {
    const instance = view({quote:{principal_applied:'50', tax_in_document_currency:'0', balance:'66'}})
    instance.document = {currency_type_id:'VES', total_payable:'500', payments:[]}
    instance.quote = null
    instance.pending = true
    await Vue.nextTick()
    assert.equal(value(instance, 'invoice-paid'), '—')
    assert.equal(value(instance, 'invoice-missing'), '—')
    instance.pending = false
    await Vue.nextTick()
    assert.equal(value(instance, 'invoice-paid'), 'Bs. 0.00')
    assert.equal(value(instance, 'invoice-missing'), 'Bs. 500.00')
    instance.$destroy()
})

test('large exact VES invoice amounts preserve a one cent balance', () => {
    const instance = view({document:{currency_type_id:'VES', total_payable:'9007199254740993.13', payments:[]},
        quote:{principal_applied:'9007199254740993.12', tax_in_document_currency:'0', balance:'0.01'}})
    assert.equal(value(instance, 'invoice-paid'), 'Bs. 9007199254740993.12')
    assert.equal(value(instance, 'invoice-missing'), 'Bs. 0.01')
    instance.$destroy()
})

test('large exact amounts and a fractional conversion keep their cents', async () => {
    const instance = view({document:{currency_type_id:'VES', total_payable:'9007199254740993.13', payments:[]},
        quote:{principal_applied:'9007199254740993.12', tax_in_document_currency:'0', balance:'0.01'}})
    assert.equal(value(instance, 'invoice-paid'), 'Bs. 9007199254740993.12')
    assert.equal(value(instance, 'invoice-missing'), 'Bs. 0.01')
    instance.quote = null
    instance.document = {currency_type_id:'USD', exchange_rate_sale:'0.015', total:'0.333333333333', payments:[],
        calculation_snapshot:exactSnapshot({total:exactAmount(1).dividedBy(3)})}
    await Vue.nextTick()
    assert.equal(value(instance, 'invoice-missing'), 'Bs. 0.01')
    instance.$destroy()
})

test('invalid receipts stay unavailable and an existing excess never creates a negative outstanding amount', async () => {
    const instance = view({document:{currency_type_id:'VES', total_payable:'100.00', payments:[{id:1, payment:'150.00'}]}})
    assert.equal(value(instance, 'invoice-paid'), 'Bs. 150.00')
    assert.equal(value(instance, 'invoice-missing'), 'Bs. 0.00')
    instance.document.payments[0].payment = '-1'
    await Vue.nextTick()
    assert.equal(value(instance, 'invoice-paid'), '—')
    assert.equal(value(instance, 'invoice-missing'), '—')
    instance.document.payments[0].payment = 'incompleto'
    await Vue.nextTick()
    assert.equal(value(instance, 'invoice-paid'), '—')
    instance.$destroy()
})
// ######## FIN RESUMEN PAGADO Y FALTA ########
