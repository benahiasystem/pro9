// ######## INICIO RESUMEN REACTIVO IVA E IGTF ########
const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm')
const compiler = require('vue-template-compiler'), babel = require('@babel/core')
const parser = require('@babel/parser'), generate = require('@babel/generator').default
const Vue = require('vue')
const runtime = require('./support/exact-runtime.cjs')
const {exactAmount, exactSnapshot, normalizeExchangeRate} = runtime('exchange-rate-math')
const {documentTotals} = runtime('exact-document')
const {calculateRowItem} = require('./helpers/load-module.cjs')(path.resolve('resources/js/helpers/functions.js'))
const {defaultChangeDisbursements, validateChangeDisbursements} = require('./helpers/load-module.cjs')(path.resolve('resources/js/helpers/change-disbursements.js'))

const file = 'resources/js/views/tenant/documents/partials/tax_summary.vue'
const summarySource = compiler.parseComponent(fs.readFileSync(file, 'utf8'))
const formSource = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/invoice_generate.vue', 'utf8'))
function load(file) {
    const source = fs.readFileSync(file, 'utf8')
    const script = file.endsWith('.vue') ? compiler.parseComponent(source).script.content : source
    const exports = {}
    vm.runInNewContext(babel.transformSync(script, {configFile:false, babelrc:false,
        plugins:['@babel/plugin-transform-modules-commonjs']}).code, {exports, require(name) {
        if (name === 'vue') return Vue
        const exact = runtime(name)
        if (exact) return exact
        if (name.startsWith('.')) return load(path.resolve(path.dirname(file), /\.(vue|js)$/.test(name) ? name : name + '.js'))
        throw new Error(`Unexpected dependency: ${name}`)
    }})
    return exports
}
const component = load(file).default
function settings() { return {enabled:true, rate:'3.000000000000', loaded:true, loading:false, error:''} }
function makeSummary(props = {}) {
    const parentState = Vue.observable({
        document:{currency_type_id:'VES', exchange_rate_sale:'40', total_taxed:'100', total_igv:'16'},
        ivaRate:'0.16', settings:settings(), currencyTypes:[{id:'VES', symbol:'Bs.'}, {id:'USD', symbol:'$'}], ...props,
    })
    return new Vue({...component, ...compiler.compileToFunctions(summarySource.template.content), propsData:parentState})
}
function text(node) { return (node.text || '') + (node.children || []).map(text).join('') }
function cell(view, id) {
    function find(node) {
        if (node.data?.attrs?.['data-testid'] === id) return node
        return (node.children || []).map(find).find(Boolean)
    }
    return text(find(view._render()))
}
function quote(payments) { return {igtf_enabled:true, igtf_rate:'3', payments} }
function subject(amount, rate = '40', tax = '3.00') {
    return {currency_type_id:'USD', igtf_status:'subject', original_amount:amount, exchange_rate:rate, tax_amount:tax, reused:false}
}

test('the approved table renders the voucher IVA and formats bolívares only at presentation', () => {
    assert.deepEqual(compiler.compile(summarySource.template.content).errors, [])
    assert.deepEqual(compiler.compile(formSource.template.content).errors, [])
    const document = {currency_type_id:'VES', total_taxed:'646.551724137931', total_igv:'103.448275862069',
        calculation_snapshot:exactSnapshot({total_taxed:exactAmount('18750').dividedBy('29'), total_igv:exactAmount('3000').dividedBy('29')})}
    const before = JSON.stringify(document)
    const view = makeSummary({document})
    for (const header of ['Impuesto', 'Monto Imponible', 'Monto de Impuestos']) assert.match(text(view._render()), new RegExp(header))
    assert.equal(cell(view, 'iva-taxable-base'), 'Bs. 646,55')
    assert.equal(cell(view, 'iva-tax-amount'), 'Bs. 103,45')
    assert.equal(cell(view, 'igtf-taxable-base'), 'Bs. 0,00')
    assert.equal(cell(view, 'igtf-tax-amount'), 'Bs. 0,00')
    assert.equal(JSON.stringify(document), before)
    view.$destroy()
})

test('IGTF uses only net subject dollars at each receipt rate and the tax actually collected', async () => {
    const rows = [subject('100'), subject('0.55', '10', '0.02'),
        {...subject('500'), igtf_status:'not_applicable'}, {...subject('200'), currency_type_id:'VES'},
        {...subject('300'), reused:true}]
    const view = makeSummary({quote:quote(rows)})
    assert.equal(cell(view, 'igtf-taxable-base'), 'Bs. 4.005,50')
    assert.equal(cell(view, 'igtf-tax-amount'), 'Bs. 120,20', 'USD 0.02 collected at rate 10 is Bs. 0.20, not a new 3% calculation')
    const id = view._uid
    rows[0].original_amount = '75.00'
    rows[0].tax_amount = '2.25'
    rows[0].change = '25.00'
    await Vue.nextTick()
    assert.equal(cell(view, 'igtf-taxable-base'), 'Bs. 3.005,50', 'returned dollars do not enter the taxable base')
    assert.equal(cell(view, 'igtf-tax-amount'), 'Bs. 90,20')
    rows.splice(0, rows.length)
    await Vue.nextTick()
    assert.equal(cell(view, 'igtf-taxable-base'), 'Bs. 0,00')
    assert.equal(cell(view, 'igtf-tax-amount'), 'Bs. 0,00')
    assert.equal(view._uid, id)
    view.$destroy()
})

function formMethods() {
    const declaration = parser.parse(formSource.script.content, {sourceType:'module'}).program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration
    const methods = declaration.properties.find(node => node.key?.name === 'methods').value.properties
    return vm.runInNewContext(`({${['initForm', 'calculateTotal'].map(name => generate(methods.find(node => node.key?.name === name)).code).join(',')}})`, {
        documentTotals, moment:require('moment'),
    })
}

test('invoice submission applies the cash default without opening the selector and preserves an explicit choice', () => {
    const declaration = parser.parse(formSource.script.content, {sourceType:'module'}).program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration
    const methods = declaration.properties.find(node => node.key?.name === 'methods').value.properties
    const actual = vm.runInNewContext(`({${generate(methods.find(node => node.key?.name === 'validateChangeSelections')).code}})`, {defaultChangeDisbursements, validateChangeDisbursements})
    const receipt = {currency_type_id:'VES',change:'50',exchange_rate:'100'}
    const row = {}
    const context = {fiscalPaymentsApplicable:true, igtfQuote:{payments:[receipt]}, fiscalSourceForQuote:()=>row,
        form:{establishment_id:7}, payment_destinations:[{id:'cash'}],
        payment_method_types:[{id:'01',collection_kind:'cash',currency_type_id:'VES',is_active:true,available_for_change:false}],
        $set:(object,key,value)=>{object[key]=value}, $message:{error:()=>assert.fail('unexpected validation error')}}
    assert.equal(actual.validateChangeSelections.call(context),true)
    assert.equal(row.change_disbursements[0].payment_method_type_id,'01')
    assert.equal(row.change_disbursements[0].amount,'50.00')
    row.change_disbursements[0].amount='49.00'
    context.$message.error=()=>{}
    assert.equal(actual.validateChangeSelections.call(context),false)
    assert.equal(row.change_disbursements[0].amount,'49.00')
})
function item(quantity = '1', currency = 'VES', affectation = '10') {
    return calculateRowItem({item:{id:1, currency_type_id:currency, unit_price:'116', has_igv:true},
        quantity, affectation_igv_type_id:affectation, discounts:[], charges:[], attributes:[]}, currency, '40', '0.16')
}

test('real form initialization and total calculation update IVA after quantity, discounts, currency, rate and item removal', async () => {
    const noop = () => {}
    const form = new Vue({data:() => ({form:{}, config:{currency_type_id:'VES'}, idUser:1}), methods:{...formMethods(),
        clickAddPayment:noop, clickAddInitGuides:noop, initInputPerson:noop, resetCustomerPoints:noop,
        chargeGlobal:noop, setTotalDefaultPayment:noop, setPendingAmount:noop, calculateFee:noop, setTotalPointsBySale:noop,
    }, beforeCreate() { this.$eventHub = {$emit:noop} }})
    form.initForm()
    const view = makeSummary({document:form.form}), id = view._uid
    form.form.items = [item()]
    form.calculateTotal()
    await Vue.nextTick()
    assert.equal(cell(view, 'iva-taxable-base'), 'Bs. 100,00')
    assert.equal(cell(view, 'iva-tax-amount'), 'Bs. 16,00')
    assert.ok(form.form.calculation_snapshot.__ob__, 'the snapshot added by calculateTotal must be reactive in Vue 2')
    form.form.items = [item('2')]
    form.calculateTotal()
    await Vue.nextTick()
    assert.equal(cell(view, 'iva-taxable-base'), 'Bs. 200,00')
    assert.equal(cell(view, 'iva-tax-amount'), 'Bs. 32,00')
    form.form.discounts = [{discount_type_id:'02', amount:'20'}]
    form.calculateTotal()
    await Vue.nextTick()
    assert.equal(cell(view, 'iva-taxable-base'), 'Bs. 180,00')
    assert.equal(cell(view, 'iva-tax-amount'), 'Bs. 28,80')
    form.form.currency_type_id = 'USD'
    form.form.exchange_rate_sale = '40.12345678'
    await Vue.nextTick()
    assert.equal(cell(view, 'iva-taxable-base'), 'Bs. 7.222,22')
    assert.equal(cell(view, 'iva-tax-amount'), 'Bs. 1.155,56')
    form.form.exchange_rate_sale = '50'
    await Vue.nextTick()
    assert.equal(cell(view, 'iva-taxable-base'), 'Bs. 9.000,00')
    assert.equal(cell(view, 'iva-tax-amount'), 'Bs. 1.440,00')
    form.form.discounts = []
    form.form.items = [item('1', 'USD', '20')]
    form.calculateTotal()
    await Vue.nextTick()
    assert.equal(cell(view, 'iva-taxable-base'), 'Bs. 0,00')
    assert.equal(cell(view, 'iva-tax-amount'), 'Bs. 0,00')
    form.form.items.splice(0)
    form.calculateTotal()
    await Vue.nextTick()
    assert.equal(cell(view, 'iva-taxable-base'), 'Bs. 0,00')
    assert.equal(view._uid, id, 'reactivity does not recreate the component')
    view.$destroy(); form.$destroy()
})

test('IVA converts the snapshot fraction before rounding and preserves amounts above the JavaScript safe integer', async () => {
    const view = makeSummary({document:{currency_type_id:'USD', exchange_rate_sale:'0.015', total_taxed:'0.333333333333', total_igv:'0',
        calculation_snapshot:exactSnapshot({total_taxed:exactAmount(1).dividedBy(3), total_igv:0})}})
    assert.equal(cell(view, 'iva-taxable-base'), 'Bs. 0,01')
    view.document = {currency_type_id:'VES', total_taxed:'9007199254740993.12', total_igv:'1441151880758558.8992'}
    await Vue.nextTick()
    assert.equal(cell(view, 'iva-taxable-base'), 'Bs. 9.007.199.254.740.993,12')
    assert.equal(cell(view, 'iva-tax-amount'), 'Bs. 1.441.151.880.758.558,90')
    view.$destroy()
})

test('pending quotes retain the previous IGTF with a status while errors hide it', async () => {
    const view = makeSummary({quote:quote([subject('100')])}), id = view._uid
    view.quotePending = true
    await Vue.nextTick()
    assert.equal(cell(view, 'igtf-taxable-base'), 'Bs. 4.000,00')
    assert.equal(cell(view, 'igtf-tax-amount'), 'Bs. 120,00')
    assert.match(text(view._render()), /Evaluando el cobro/)
    view.quotePending = false; view.quoteError = 'Revise el destino del cobro.'
    await Vue.nextTick()
    assert.equal(cell(view, 'igtf-tax-amount'), '—')
    assert.match(text(view._render()), /Revise el destino/)
    view.quoteError = ''; view.quote = quote([subject('50', '50', '1.50')])
    await Vue.nextTick()
    assert.equal(cell(view, 'igtf-taxable-base'), 'Bs. 2.500,00')
    assert.equal(cell(view, 'igtf-tax-amount'), 'Bs. 75,00')
    view.settings.enabled = false
    await Vue.nextTick()
    assert.equal(cell(view, 'igtf-taxable-base'), 'Bs. 0,00')
    assert.equal(cell(view, 'igtf-tax-amount'), 'Bs. 0,00')
    assert.equal(view._uid, id)
    view.quote = {...view.quote,server_verified:true,igtf_enabled:true}
    await Vue.nextTick()
    assert.equal(cell(view, 'igtf-tax-amount'), 'Bs. 75,00')
    view.$destroy()
})

test('invalid conversion data stays unavailable and configured tax rates remain reactive', async () => {
    const view = makeSummary({quote:quote([subject('100', null)])})
    assert.equal(cell(view, 'igtf-tax-amount'), '—')
    view.quote.payments[0].exchange_rate = '50'
    view.quote.igtf_rate = '4'
    view.ivaRate = '0.08'
    await Vue.nextTick()
    assert.equal(cell(view, 'igtf-taxable-base'), 'Bs. 5.000,00')
    assert.match(text(view._render()), /IGTF - 4% \(IGTF\)/)
    assert.match(text(view._render()), /IVA - 8% \(G\)/)
    view.document.currency_type_id = 'USD'; view.document.exchange_rate_sale = '0'
    await Vue.nextTick()
    assert.equal(cell(view, 'iva-taxable-base'), '—')
    view.$destroy()
})

test('the form binding retains a labelled previous quote only while updating', async () => {
    const binding = formSource.template.content.match(/<document-tax-summary[\s\S]*?\/>/)[0]
    const declaration = parser.parse(formSource.script.content, {sourceType:'module'}).program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration
    const computed = declaration.properties.find(node => node.key?.name === 'computed').value.properties
    const actual = vm.runInNewContext(`({${generate(computed.find(node => node.key?.name === 'displayFiscalQuote')).code}})`)
    const parent = new Vue({...compiler.compileToFunctions(`<div>${binding}</div>`), components:{DocumentTaxSummary:component},
        data:() => ({form:{currency_type_id:'VES'}, percentage_igv:'0.16', currency_types:[], igtfSettings:settings(),
            igtfQuote:quote([subject('100')]), lastDisplayedFiscalQuote:quote([subject('50')]), igtfQuoteKey:'previous', fiscalQuoteKey:'current', igtfQuoteLoading:false, igtfQuoteError:''}),
        computed:actual})
    const props = () => parent._render().children[0].componentOptions.propsData
    assert.equal(props().quote, parent.lastDisplayedFiscalQuote)
    assert.equal(props().quotePending, true)
    parent.igtfQuoteKey = 'current'
    await Vue.nextTick()
    assert.equal(props().quote, parent.igtfQuote)
    assert.equal(props().quotePending, false)
    parent.fiscalQuoteKey = ''; parent.igtfQuoteKey = null; parent.igtfQuote = null
    await Vue.nextTick()
    assert.equal(props().quote, null)
    assert.equal(props().quotePending, false)
    parent.$destroy()
})

test('the invoice total uses the quoted total with IGTF and updates after a new quote', async () => {
    const declaration = parser.parse(formSource.script.content, {sourceType:'module'}).program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration
    const computed = declaration.properties.find(node => node.key?.name === 'computed').value.properties
    const actual = vm.runInNewContext(`({${generate(computed.find(node => node.key?.name === 'invoiceTotalWithIgtf')).code}})`, {exactAmount})
    const row = formSource.template.content.match(/<tr v-if="form.total > 0 && fiscalPaymentsApplicable && !isUpdateDocument" class="invoice-total-due" data-testid="invoice-total-with-igtf">[\s\S]*?<\/tr>/)[0]
    const view = new Vue({...compiler.compileToFunctions(`<table>${row}</table>`), computed:actual,
        data:() => ({form:{total:'250', total_payable:'250.00'}, documentCurrencySymbol:'Bs.', fiscalPaymentsApplicable:true,
            isUpdateDocument:false, igtfQuoteError:'', fiscalQuoteKey:'', igtfQuoteKey:null, igtfQuoteLoading:false, displayFiscalQuote:null})})
    assert.match(text(view._render()), /Total a pagar:.*Bs\. 250\.00/)
    view.fiscalQuoteKey = 'pending'; view.igtfQuoteLoading = true
    await Vue.nextTick()
    assert.match(text(view._render()), /Total a pagar:.*—.*Evaluando/)
    view.displayFiscalQuote = {total_with_tax:'259.04'}; view.igtfQuoteKey = 'pending'; view.igtfQuoteLoading = false
    await Vue.nextTick()
    assert.match(text(view._render()), /Total a pagar:.*Bs\. 259\.04/)
    view.displayFiscalQuote = {total_with_tax:'268.09'}
    await Vue.nextTick()
    assert.match(text(view._render()), /Total a pagar:.*Bs\. 268\.09/)
    view.igtfQuoteError = 'No se pudo evaluar'
    await Vue.nextTick()
    assert.match(text(view._render()), /Total a pagar:.*—/)
    view.$destroy()
})

test('document currency symbol follows the selected currency when the catalog loads or changes', async () => {
    const declaration = parser.parse(formSource.script.content, {sourceType:'module'}).program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration
    const computed = declaration.properties.find(node => node.key?.name === 'computed').value.properties
    const symbol = vm.runInNewContext(`({${generate(computed.find(node => node.key?.name === 'documentCurrencySymbol')).code}})`)
    const view = new Vue({computed:symbol, data:() => ({form:{currency_type_id:'VES'}, currency_types:[]})})
    assert.equal(view.documentCurrencySymbol, 'Bs.')
    view.currency_types = [{id:'VES',symbol:'Bs.'},{id:'USD',symbol:'$'}]
    view.form.currency_type_id = 'USD'
    await Vue.nextTick()
    assert.equal(view.documentCurrencySymbol, '$')
    view.form.currency_type_id = 'VES'
    await Vue.nextTick()
    assert.equal(view.documentCurrencySymbol, 'Bs.')
    view.$destroy()
})
// ######## FIN RESUMEN REACTIVO IVA E IGTF ########

test('receipt breakdown uses the server net receipt and collected tax for each subject payment', () => {
    const declaration = parser.parse(formSource.script.content, {sourceType:'module'}).program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration
    const methods = declaration.properties.find(node => node.key?.name === 'methods').value.properties
    const actual = vm.runInNewContext(`({${['fiscalReceiptBreakdown','fiscalEquivalent'].map(name => generate(methods.find(node => node.key?.name === name)).code).join(',')}})`, {exactAmount, normalizeExchangeRate})
    const first = {payment:'27133.46'}, second = {payment:'10000'}, empty = {payment:'0'}
    const ctx = {fiscalQuoteRows:[empty, first, second], igtfQuoteKey:'current', fiscalQuoteKey:'current', igtfQuoteLoading:false, igtfQuoteError:'',
        igtfQuote:{payments:[{currency_type_id:'USD', original_amount:'30.00', tax_amount:'0.90', exchange_rate:'40.12345678', igtf_status:'subject'},
            {currency_type_id:'VES', original_amount:'9116.24', tax_amount:'0.00', change:'883.76', exchange_rate:'911.62430000', igtf_status:'not_applicable'}]}}
    const result = actual.fiscalReceiptBreakdown.call(ctx, first)
    assert.equal(result.principal, '30.00'); assert.equal(result.tax, '0.90'); assert.equal(result.total, '30.90'); assert.equal(result.currency, 'USD')
    assert.equal(actual.fiscalEquivalent(result.total, result), 'Bs. 1239.81')
    ctx.igtfQuote.payments[0].exchange_rate = null
    assert.equal(actual.fiscalEquivalent('30.90', ctx.igtfQuote.payments[0]), '—', 'a missing exchange rate must not fabricate a bolivar equivalent')
    ctx.igtfQuote.payments[0].exchange_rate = '50'
    assert.equal(actual.fiscalEquivalent('30.90', ctx.igtfQuote.payments[0]), 'Bs. 1545.00')
    assert.equal(actual.fiscalEquivalent('9116.24', ctx.igtfQuote.payments[1]), '$ 10.00')
    assert.equal(actual.fiscalEquivalent('883.76', ctx.igtfQuote.payments[1]), '$ 0.97')
    assert.equal(actual.fiscalEquivalent('8.20', ctx.igtfQuote.payments[0]), 'Bs. 410.00')
    assert.deepEqual({...actual.fiscalReceiptBreakdown.call(ctx, second)},
        {principal:'9116.24', tax:'0.00', total:'9116.24', change:'883.76', currency:'VES', exchange_rate:'911.62430000'})
    ctx.igtfQuote.payments[1].reused = true
    assert.equal(actual.fiscalReceiptBreakdown.call(ctx, second), null, 'historical receipts must not offer change again')
    ctx.igtfQuote.payments[0].original_amount = '29.50'
    ctx.igtfQuote.payments[0].tax_amount = '0.89'
    assert.equal(actual.fiscalReceiptBreakdown.call(ctx, first).total, '30.39', 'net principal after change and actual charged tax')
    Object.assign(ctx.igtfQuote.payments[0], {original_amount:'60.00', tax_amount:'1.80', change:'8.20'})
    assert.equal(actual.fiscalReceiptBreakdown.call(ctx, first).total, '61.80')
    assert.equal(actual.fiscalReceiptBreakdown.call(ctx, first).change, '8.20')
    ctx.fiscalQuoteKey = 'changed'
    assert.equal(actual.fiscalReceiptBreakdown.call(ctx, first), null, 'a stale quote must not be shown on another payment')
})

test('the actual receipt template updates bolivar change without recreating the component', async () => {
    const declaration = parser.parse(formSource.script.content, {sourceType:'module'}).program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration
    const methods = declaration.properties.find(node => node.key?.name === 'methods').value.properties
    const actual = vm.runInNewContext(`({${['fiscalEquivalent','hasFiscalChange'].map(name => generate(methods.find(node => node.key?.name === name)).code).join(',')}})`, {exactAmount, normalizeExchangeRate})
    const computed = declaration.properties.find(node => node.key?.name === 'computed').value.properties
    const changeComputed = vm.runInNewContext(`({${['fiscalChangeReceipts','fiscalChangeTotal'].map(name => generate(computed.find(node => node.key?.name === name)).code).join(',')}})`, {exactAmount, normalizeExchangeRate})
    const detail = formSource.template.content.match(/<tr v-if="form.total > 0 && fiscalPaymentsApplicable && !isUpdateDocument" data-testid="invoice-change">[\s\S]*?<\/tr>/)[0]
    const view = new Vue({...compiler.compileToFunctions(`<table><tbody>${detail}</tbody></table>`), computed:changeComputed, methods:{...actual, fiscalSourceForQuote:()=>null},
        data:() => ({form:{total:'9116.24',currency_type_id:'VES',exchange_rate_sale:'911.62430000'}, fiscalPaymentsApplicable:true, isUpdateDocument:false,
            igtfQuoteKey:'current', fiscalQuoteKey:'current', igtfQuoteLoading:false,
            displayFiscalQuote:{payments:[{currency_type_id:'VES', change:'883.76', exchange_rate:'911.62430000'}]}})})
    const id = view._uid
    assert.match(text(view._render()), /Vuelto:.*883\.76 VES ⇄ \$ 0\.97/s)
    view.displayFiscalQuote.payments[0].change = '383.76'
    await Vue.nextTick()
    assert.match(text(view._render()), /Vuelto:.*383\.76 VES/s)
    view.displayFiscalQuote.payments[0].change = '0.00'
    await Vue.nextTick()
    assert.match(text(view._render()), /Vuelto:.*0\.00 VES/s)
    view.displayFiscalQuote.payments = [
        {currency_type_id:'VES', change:'500.00', exchange_rate:'100'},
        {currency_type_id:'VES', change:'0.00', exchange_rate:'100'},
        {currency_type_id:'VES', change:'0.00', exchange_rate:'100'},
    ]
    await Vue.nextTick()
    assert.match(text(view._render()), /Vuelto:.*500\.00 VES/s)
    assert.equal(view.fiscalChangeReceipts.length, 1)
    assert.equal((text(view._render()).match(/VES/g) || []).length, 1)
    view.displayFiscalQuote.payments[1] = {currency_type_id:'USD', change:'1.00', exchange_rate:'100'}
    view.displayFiscalQuote = {...view.displayFiscalQuote, payments:[...view.displayFiscalQuote.payments]}
    await Vue.nextTick()
    assert.equal(view.fiscalChangeTotal, '600.00')
    assert.deepEqual(Array.from(view.fiscalChangeReceipts, entry => entry.index), [0, 1])
    assert.equal(view._uid, id)
    view.$destroy()
})

test('changing the exchange rate recalculates item snapshots and invoice totals', () => {
    const declaration = parser.parse(formSource.script.content, {sourceType:'module'}).program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration
    const methods = declaration.properties.find(node => node.key?.name === 'methods').value.properties
    const actual = vm.runInNewContext(`({${generate(methods.find(node => node.key?.name === 'recalculateItemsForExchangeRate')).code}})`, {normalizeExchangeRate, calculateRowItem})
    const row = calculateRowItem({item:{id:1, currency_type_id:'USD', unit_price:'10', has_igv:true}, quantity:'1', affectation_igv_type_id:'10', discounts:[], charges:[], attributes:[]}, 'VES', '911.6243', '0.16')
    const ctx = {form:{items:[row], currency_type_id:'VES'}, percentage_igv:'0.16', isExportInvoice:false, enabled_discount_global:false,
        calculateTotal() { Object.assign(this.form, documentTotals(this.form.items, [], [], '0')) }}
    actual.recalculateItemsForExchangeRate.call(ctx, '1000')
    assert.equal(ctx.form.total, '10000')
    assert.equal(exactAmount(ctx.form.calculation_snapshot.values.total).decimal(), '10000')
    actual.recalculateItemsForExchangeRate.call(ctx, '')
    assert.equal(ctx.form.total, '10000', 'incomplete input does not replace the last valid amounts')
})
