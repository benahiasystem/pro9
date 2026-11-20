const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const compiler = require('vue-template-compiler')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default
const load = require('./helpers/load-module.cjs')
const {calculateRowItem} = load(path.resolve('resources/js/helpers/functions.js'))
const {ensurePosRate} = load(path.resolve('resources/js/helpers/pos-exchange-rate.js'))
const {applyInvoiceCheckoutDiscount, checkoutTotals} = load(path.resolve('resources/js/helpers/exact-commercial.js'))
const {applyPosScalePrices} = load(path.resolve('resources/js/helpers/pos-scale-prices.js'))
const {suggestRemainingPayment} = load(path.resolve('resources/js/helpers/document-creation.js'))

for (const file of ['index.vue', 'fast.vue', 'garage.vue']) {
    const component = compiler.parseComponent(fs.readFileSync(`resources/js/views/tenant/pos/${file}`, 'utf8'))
    const ast = parser.parse(component.script.content, {sourceType: 'module'})
    const methods = ast.program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration.properties.find(node => node.key.name === 'methods').value
    for (const name of ['blurCalculateQuantity', 'blurCalculateQuantity2']) {
        const method = methods.properties.find(node => node.key?.name === name)
        if (!method) continue
        const run = vm.runInNewContext(`({${generate(method).code}})[${JSON.stringify(name)}]`, {calculateRowItem, ensurePosRate, _: require('lodash')})
        test(`${file}: ${name} conserva la tasa al cambiar la cantidad en ambas monedas`, () => {
            const comparisons = [['USD', 'VES', '10', '250']]
            if (process.env.SAB_TEST_INVOICES_VES_ONLY !== '1') comparisons.push(['VES', 'USD', '125', '20'])
            for (const [source, target, price, expected] of comparisons) {
                const initial = {item: {id: 1, currency_type_id: source, unit_price: price, has_igv: true},
                    affectation_igv_type_id: '10', affectation_igv_type: {id:'10',free:false},
                    quantity: '1', discounts: [], charges: [], attributes: [], warehouse_id: 1}
                const row = calculateRowItem(initial, target, '12.5')
                row.quantity = '2'
                const ctx = {form: {items:[row], currency_type_id: target, exchange_rate_sale: '12.5'},
                    config: {}, percentage_igv: '0.16', calculateTotal() {}, setFormPosLocalStorage() {}}
                run.call(ctx, 0)
                assert.equal(ctx.form.items[0].total, expected)
                assert.equal(ctx.form.items[0].price_input.currency_type_id, source)
                assert.equal(ctx.form.items[0].price_input.amount, price)
            }
            assert.deepEqual(compiler.compile(component.template.content).errors, [])
        })
    }
    const editName = file === 'index.vue' ? 'changeRowTotal' : file === 'garage.vue' ? 'changeRowTotalGarage' : null
    if (editName) {
        const method = methods.properties.find(node => node.key?.name === editName)
        const exactAmount = load(path.resolve('resources/js/helpers/exchange-rate-math.js')).exactAmount
        const run = vm.runInNewContext(`({${generate(method).code}})[${JSON.stringify(editName)}]`, {calculateRowItem, exactAmount, ensurePosRate, _: require('lodash')})
        test(`${file}: editar el total conserva el precio ingresado y su moneda documental`, () => {
            const input = {item:{id:1,currency_type_id:'USD',unit_price:'10',has_igv:true},
                affectation_igv_type_id:'10',affectation_igv_type:{id:'10',free:false},
                quantity:'2',discounts:[],charges:[],attributes:[],warehouse_id:1}
            const row=calculateRowItem(input,'VES','12.5')
            row.total='300'
            const ctx={form:{items:[row],currency_type_id:'VES',exchange_rate_sale:'12.5'},config:{},percentage_igv:'0.16',
                calculateTotal(){},setFormPosLocalStorage(){},$message:{error(){}}}
            run.call(ctx,0)
            assert.equal(ctx.form.items[0].total,'300')
            assert.equal(ctx.form.items[0].price_input.currency_type_id,'VES')
            const changeQuantity=methods.properties.find(node=>node.key?.name==='blurCalculateQuantity2')
            const rerun=vm.runInNewContext(`({${generate(changeQuantity).code}}).blurCalculateQuantity2`, {calculateRowItem, ensurePosRate, _: require('lodash')})
            ctx.form.items[0].quantity='3'
            rerun.call(ctx,0)
            assert.equal(ctx.form.items[0].total,'450')
            ctx.form.items[0].total='1'
            run.call(ctx,0)
            assert.equal(ctx.form.items[0].total,'1')
            assert.equal(ctx.form.items[0].price_input.amount.n,'1')
            assert.equal(ctx.form.items[0].price_input.amount.d,'3')
        })
    }
}

for (const file of ['fast_payment.vue', 'fast_payment_garage.vue', 'payment.vue']) {
    const component = compiler.parseComponent(fs.readFileSync(`resources/js/views/tenant/pos/partials/${file}`, 'utf8'))
    const ast = parser.parse(component.script.content, {sourceType: 'module'})
    const methods = ast.program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration.properties.find(node => node.key.name === 'methods').value
    const names = ['inputDiscountAmount', 'changeEnabledDiscount', 'reCalculateTotal', 'calculatePayments', 'refreshScaleTotals', 'clickPayment']
    const source = methods.properties.filter(node => names.includes(node.key.name)).map(node => generate(node).code).join(',')
    const run = vm.runInNewContext(`({${source}})`, {
        applyInvoiceCheckoutDiscount, checkoutTotals, suggestRemainingPayment, applyPosScalePrices, invoiceDiscountByDraft:new WeakMap(),
    })

    function checkout(total, mode = 'amount') {
        const errors = []
        return {
            ...run, errors, enabled_discount: true,
            ...(file === 'payment.vue' ? {is_discount_amount: mode === 'amount'} : {discount_type: mode === 'amount' ? '01' : '02'}),
            form: {document_type_id:'01', total, items:[{affectation_igv_type_id:'20',total_base_igv:total,total_value:total}], discounts:[]},
            $message: {error(message) { errors.push(message) }},
        }
    }

    test(`${file}: el descuento de factura usa la base original y conserva otros ajustes`, async () => {
        const state = checkout('100')
        state.form.discounts = [{discount_type_id:'05', amount:'2'}]
        state.form.payments = [{payment:'1.10', currency_type_id:'VES'}]
        state.form.currency_type_id = 'VES'
        state.enabledPointSystem = true
        const points = []
        state.setTotalPointsBySale = () => points.push(state.form.total)
        state.discount_amount = '9.'
        assert.equal(state.inputDiscountAmount(), true)
        assert.equal(state.form.total, '89')
        state.discount_amount = '95'
        assert.equal(state.inputDiscountAmount(), true)
        assert.equal(state.form.total, '3')

        state.discount_amount = '99'
        const discounts = state.form.discounts
        assert.equal(state.inputDiscountAmount(), false)
        assert.equal(state.form.discounts, discounts)
        assert.equal(state.form.total, '3')
        await state.clickPayment()
        assert.equal(state.errors.length, 2)
        state.discount_amount = '98'
        assert.equal(state.inputDiscountAmount(), false)
        state.discount_amount = '100'
        assert.equal(state.inputDiscountAmount(), false)
        state.discount_amount = '100.000000000001'
        assert.equal(state.inputDiscountAmount(), false)
        state.discount_amount = '9x'
        assert.equal(state.inputDiscountAmount(), false)
        state.enabled_discount = false
        state.changeEnabledDiscount()
        assert.equal(state.form.total, '98')
        assert.deepEqual(Array.from(state.form.discounts, row => row.discount_type_id), ['05'])
        assert.equal(state.form.payments[0].payment, '1.10')
        assert.deepEqual(points, ['89','3','98'])
    })

    test(`${file}: el descuento monetario conserva los doce decimales sobre importes grandes`, () => {
        const state = checkout('999999999999999999.999999999999')
        state.discount_amount = '999999999999999998.999999999999'
        assert.equal(state.inputDiscountAmount(), true)
        assert.equal(state.form.total, '1')
        assert.equal(state.form.discounts[0].amount, state.discount_amount)
    })

    test(`${file}: el descuento con IVA incluido se valida contra su base gravada`, () => {
        const state = checkout('116')
        state.configuration = {global_discount_type_id:'02', exact_discount:true}
        state.form.items = [{affectation_igv_type_id:'10', total_base_igv:'100', total_value:'100', total_igv:'16'}]
        state.discount_amount = '114.84'
        assert.equal(state.inputDiscountAmount(), true)
        assert.equal(state.form.discounts[0].amount, '99')
        assert.equal(state.form.total_igv, '0.16')
        assert.equal(state.form.total, '1.16')
        state.discount_amount = '116'
        assert.equal(state.inputDiscountAmount(), false)
        assert.equal(state.form.total, '1.16')

        state.form.discounts.push({discount_type_id:'04', amount:'60'})
        state.discount_amount = '58'
        assert.equal(state.inputDiscountAmount(), false)
        assert.equal(state.form.total_igv, '0.16')
    })

    if (file !== 'fast_payment.vue') {
        test(`${file}: el porcentaje de factura se aplica a la base y no se compara como monto`, () => {
            const state = checkout('5', 'percentage')
            state.discount_amount = '9'
            assert.equal(state.inputDiscountAmount(), true)
            assert.equal(state.form.discounts[0].amount, '0.45')
            assert.equal(state.form.total, '4.55')
            state.discount_amount = '100.000000000001'
            assert.equal(state.inputDiscountAmount(), false)
            assert.equal(state.form.total, '4.55')
        })
    }
}

test('remounting the POS invoice checkout preserves its original discount mode and amount', () => {
    const component = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/pos/partials/payment.vue', 'utf8'))
    const body = parser.parse(component.script.content, {sourceType:'module'}).program.body
    const definition = body.find(node => node.type === 'ExportDefaultDeclaration').declaration.properties
    const methods = definition.find(node => node.key.name === 'methods').value.properties
    const names = ['inputDiscountAmount','changeEnabledDiscount','reCalculateTotal','calculatePayments','selectDocumentType','refreshScaleTotals','back']
    const draftState = body.find(node => node.declarations?.some(entry => entry.id.name === 'invoiceDiscountByDraft'))
    const hooks = [definition.find(node => node.key.name === 'data'), ...methods.filter(node => names.includes(node.key.name))]
    const view = vm.runInNewContext(`${draftState ? generate(draftState).code : ''}\n({${hooks.map(node => generate(node).code).join(',')}})`,
        {applyInvoiceCheckoutDiscount, checkoutTotals, suggestRemainingPayment, applyPosScalePrices})

    function mount(form) {
        return {...view, ...view.data.call({form}), form, percentageIgv:'0.16', configuration:{global_discount_type_id:'03'},
            inputAmount(){}, filterSeries(){}, $emit(event, value){this.closed = event === 'update:is_payment' && value === false},
            $message:{error(message){assert.fail(message)}}}
    }

    for (const isAmount of [true, false]) {
        const row = calculateRowItem({
            item:{id:1, unit_price:'120', currency_type_id:'VES', has_igv:true},
            quantity:'1', unit_price:'120', affectation_igv_type_id:'20', discounts:[], charges:[],
            price_input:{amount:'120', currency_type_id:'VES', includes_iva:true},
        }, 'VES', '1', '0.16')
        row._pos_scale_prices = {invoice:{n:'120',d:'1'}, saleNote:{n:'99',d:'1'}}
        const form = {document_type_id:'01', currency_type_id:'VES', exchange_rate_sale:'1',
            items:[row], discounts:[], charges:[{charge_type_id:'50',amount:'5'}], payments:[]}
        const state = mount(form)
        state.enabled_discount = true
        state.is_discount_amount = isAmount
        state.discount_amount = '10'
        assert.equal(state.inputDiscountAmount(), true)
        assert.equal(form.total, isAmount ? '115' : '113')
        state.back()
        assert.equal(state.closed, true)

        assert.equal(mount({...form}).enabled_discount, false)
        form.currency_type_id = 'USD'
        assert.equal(mount(form).enabled_discount, false)
        form.currency_type_id = 'VES'
        const reopened = mount(form)
        assert.equal(reopened.enabled_discount, true)
        assert.equal(reopened.is_discount_amount, isAmount)
        assert.equal(reopened.discount_amount, '10')
        assert.equal(reopened.selectDocumentType('80'), true)
        assert.equal(form.total, '94')
        assert.equal(reopened.selectDocumentType('01'), true)
        assert.equal(form.total, isAmount ? '115' : '113')
        assert.equal(form.discounts[0].amount_exact.n, isAmount ? '10' : '12')
        assert.equal(form.charges[0].amount, '5')

        reopened.enabled_discount = false
        reopened.changeEnabledDiscount()
        assert.equal(form.total, '125')
        assert.equal(form.discounts.length, 0)
        assert.equal(mount(form).enabled_discount, false)
        assert.equal(mount({...form}).enabled_discount, false)
    }
})
