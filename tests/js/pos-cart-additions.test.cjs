const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const Vue = require('vue')
const compiler = require('vue-template-compiler')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default
const load = require('./helpers/load-module.cjs')

const mixin = load(path.resolve('resources/js/mixins/pos-cart-additions.js')).default
const { ensurePosRate } = load(path.resolve('resources/js/helpers/pos-exchange-rate.js'))
const { documentTotals } = load(path.resolve('resources/js/helpers/exact-document.js'))
const { applyPosScalePrices, withoutPosScalePrices } = load(path.resolve('resources/js/helpers/pos-scale-prices.js'))
const { checkoutTotals } = load(path.resolve('resources/js/helpers/exact-commercial.js'))
const { calculationPayload, calculationDraftKey } = load(path.resolve('resources/js/helpers/calculation-expectation.js'))
const { collectionTarget, withoutDraftRetentions, suggestRemainingPayment } = load(path.resolve('resources/js/helpers/document-creation.js'))

function componentMethods(filename, names) {
    const { script, template } = compiler.parseComponent(fs.readFileSync(`resources/js/views/tenant/pos/${filename}.vue`, 'utf8'))
    assert.deepEqual(compiler.compile(template.content).errors, [])
    const ast = parser.parse(script.content, { sourceType: 'module' })
    const options = ast.program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration.properties
    const methods = options.find(node => node.key.name === 'methods').value.properties
    return vm.runInNewContext(`({${methods.filter(node => names.includes(node.key?.name)).map(node => generate(node).code).join(',')}})`, {
        ensurePosRate,
        exactAmount, exactValue, applyPosScalePrices, withoutPosScalePrices, checkoutTotals, withoutDraftRetentions, suggestRemainingPayment,
        moment: require('moment'),
        _: require('lodash'),
    })
}

function product(id = 1, overrides = {}) {
    return { id, item_id: id, unit_type_id: 'UND', unit_type: [], currency_type_id: 'VES', sale_unit_price: '10',
        sale_affectation_igv_type_id: '10', has_igv: true,
        pos_stock: { control: true, components: [component(id, '10')] }, ...overrides }
}

function component(id, stock, quantity = '1') {
    return { item_id: id, description: `Producto ${id}`, stock, quantity }
}

const { exactAmount, exactInput, exactValue } = load(path.resolve('resources/js/helpers/exchange-rate-math.js'))
const { calculateRowItem } = load(path.resolve('resources/js/helpers/functions.js'))
const localQuantity = parser.parse(fs.readFileSync('resources/js/mixins/pos-local-quantity.js', 'utf8'), { sourceType: 'module' })
const quantityMethodsSource = localQuantity.program.body.find(node => node.type === 'ExportDefaultDeclaration')
    .declaration.properties.find(node => node.key.name === 'methods').value
const quantityMethods = vm.runInNewContext(`let lineSequence = 0; (${generate(quantityMethodsSource).code})`, {
    exactAmount, exactInput, exactValue, calculateRowItem,
})

for (const view of ['index', 'garage']) {
    const addMethods = componentMethods(view, [
        'clickAddItem', 'clickPayment', 'clickClearCart', 'initForm', 'setDefaultDataPriceSelected',
        'selectVariationFromModal', 'searchItemsBarcode', 'enabledSearchItemsBarcode',
        'getQuantityFromElectronicScale', 'getUnitPriceFromElectronicScale', 'setDataToElectronicScaleData', 'getResponseValidate',
    ])

    function checkout() {
        const requests = []
        const errors = []
        const snapshots = []
        const cart = new Vue({
            mixins: [mixin],
            data: () => ({
                form: { items: [], exchange_rate_sale: '12.5', currency_type_id: 'VES', customer_id: 1 },
                is_payment: false,
                loading: false,
                configuration: {},
                changeValuesElectronicScale: false,
                electronic_scale_data: null,
                resource: 'pos',
                input_item: '123456',
                items: [],
                search_item_by_barcode: true,
                form_item: { discounts: [], charges: [], attributes: [] },
                localPosStockChecks: [],
                percentage_igv: '0.16',
                affectation_igv_types: [{ id: '10' }, { id: '20' }],
            }),
            methods: {
                ...quantityMethods,
                ...addMethods,
                getStatusStock() { assert.fail('La edición de stock debe ser local') },
                calculateTotal() {
                    this.$set(this.form, 'total', documentTotals(this.form.items).total)
                },
                setFormPosLocalStorage() {
                    snapshots.push(JSON.parse(JSON.stringify(this.form.items)))
                },
                cleanInput() {},
                initFocus() {},
                initFormItem() {},
                changeDateOfIssue() {},
                initInputPerson() {},
                initElectronicScaleData() { this.electronic_scale_data = null },
                filterItems() {},
                ChangeSelectedPrice() {},
            },
        })
        cart.$message = { error: message => errors.push(message) }
        cart.$notify = () => {}
        cart.$http = { get(url) { requests.push(url); throw new Error('Solicitud inesperada') } }
        cart.$confirm = async () => {}
        return { cart, requests, errors, snapshots }
    }

    if (view === 'index') {
        async function scaleCart(documentType = '01', item = product(1, { unit_type_id: 'KG' })) {
            const state = checkout()
            const { cart } = state
            cart.$set(cart.form, 'document_type_id', documentType)
            cart.electronic_scale_barcode = true
            cart.initElectronicScaleData = () => { cart.electronic_scale_data = {} }
            cart.input_item = '1234503000000100'
            cart.$http = { async get() { return { data: { items: [item] } } } }
            await cart.searchItemsBarcode()
            return state
        }

        test('la factura de balanza conserva 3 kg por Bs. 1 desde el código de barras', async () => {
            const { cart, errors } = await scaleCart()
            assert.deepEqual(errors, [])
            assert.equal(cart.form.items.length, 1)
            assert.equal(cart.form.items[0].quantity, '3')
            assert.equal(cart.form.items[0].price_input.amount.n, '1')
            assert.equal(cart.form.items[0].price_input.amount.d, '3')
            assert.equal(cart.form.total, '1')
        })

        test('el cobro alterna factura y nota de venta y envía el precio vigente sin datos del borrador', async () => {
            const { cart } = await scaleCart('80')
            const centBoundary = calculateRowItem({
                item: { id: 2, unit_price: '0.005', currency_type_id: 'VES' },
                affectation_igv_type_id: '20',
                quantity: '1',
            }, 'VES', '12.5')
            cart.form.items.push(centBoundary)
            cart.calculateTotal()
            const checkoutVue = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/pos/partials/payment.vue', 'utf8'))
            assert.match(compiler.compile(checkoutVue.template.content).render, /"input":selectDocumentType/)
            const methods = componentMethods('partials/payment', [
                'filterSeries', 'selectDocumentType', 'setDefaultDocumentType', 'handleFn113',
                'reCalculateTotal', 'calculatePayments', 'refreshScaleTotals', 'clickPayment', 'getTotal', 'setDifference', 'inputAmount',
            ])
            const requests = []
            const drafts = []
            let submittedKey = null
            const ctx = {
                ...methods,
                form: JSON.parse(JSON.stringify(cart.form)),
                percentageIgv: '0.16',
                amount: '1',
                get collectionTarget() { return collectionTarget(this.form) },
                $eventHub: { $emit(event, form) {
                    assert.equal(event, 'eventSetFormPosLocalStorage')
                    drafts.push(JSON.parse(JSON.stringify(form)))
                } },
                lStoPayment() {},
                authUser: { document_id: '01', serie: 1 },
                all_series: [{ id: 1, document_type_id: '01' }, { id: 2, document_type_id: '80' }],
                $message: { warning: message => assert.fail(message), error: message => assert.fail(message) },
                validateRestrictSaleItemsCpe: () => ({ success: true }),
                validateRestrictSellerDiscount: () => ({ success: true }),
                async asignPlateNumberToItems() {},
                async ensureFiscalPaymentsReady() {
                    assert.equal(this.form.total, this.form.document_type_id === '01' ? '1.005' : '1.004999')
                    return true
                },
                prepareCalculationSubmission(form) {
                    submittedKey = calculationDraftKey(form)
                    return calculationPayload(form, null, 'fixture')
                },
                handleCalculationChanged(error, current) {
                    assert.equal(error.response.status, 409)
                    assert.equal(calculationDraftKey(current), submittedKey)
                    return true
                },
                $http: { async post(url, form) {
                    requests.push({ url, form })
                    throw Object.assign(new Error('fixture del servidor'), { response: { status: 409 } })
                } },
            }
            ctx.form.date_of_issue = require('moment')().format('YYYY-MM-DD')
            ctx.form.payment_condition_id = '01'
            ctx.form.payments = [{ payment: '1', currency_type_id: 'VES' }]
            assert.equal(ctx.form.total, '1.004999')
            ctx.setDefaultDocumentType()
            assert.equal(ctx.form.total, '1.005')
            assert.equal(drafts.length, 0)
            ctx.handleFn113()
            assert.equal(ctx.form.total, '1.004999')
            assert.equal(drafts.at(-1).document_type_id, '80')
            assert.equal(drafts.at(-1).total, '1.004999')
            assert.equal(ctx.difference, '0')
            assert.equal(ctx.button_payment, false)
            await ctx.clickPayment()
            ctx.selectDocumentType('01')
            assert.equal(ctx.form.total, '1.005')
            assert.equal(drafts.at(-1).document_type_id, '01')
            assert.equal(drafts.at(-1).total, '1.005')
            assert.equal(ctx.form.payments[0].payment, '1')
            assert.equal(ctx.difference, '-0.01')
            assert.equal(ctx.button_payment, true)
            ctx.form.payments[0].payment = '1.01'
            ctx.amount = '1.01'
            ctx.inputAmount()
            assert.equal(ctx.button_payment, false)
            await ctx.clickPayment()
            assert.equal(requests.length, 2)
            assert.deepEqual(requests.map(({url, form}) => [url, form.total, form.items[0].price_input.amount.n, form.items[0].price_input.amount.d]), [
                ['/sale-notes', '1.004999', '333333', '1000000'],
                ['/documents', '1.005', '1', '3'],
            ])
            assert.ok(requests.every(({form}) => !Object.hasOwn(form.items[0], '_pos_scale_prices')))
            assert.ok(Object.hasOwn(ctx.form.items[0], '_pos_scale_prices'))
            assert.equal(requests[1].form.expected_calculation.items[0].total.n, '1')
            assert.equal(requests[1].form.expected_calculation.items[0].total.d, '1')
        })

        test('la balanza preserva moneda, cantidad y edición explícita al cambiar de comprobante', async () => {
            const { cart } = await scaleCart('01', product(1, {
                currency_type_id: 'USD', unit_type_id: 'KG', sale_affectation_igv_type_id: '20',
            }))
            assert.equal(cart.form.total, '12.5')
            assert.equal(cart.form.items[0].price_input.currency_type_id, 'USD')
            assert.equal(cart.form.items[0].total_igv, '0')
            cart.form.items[0].item.aux_quantity = '6'
            cart.clickAddItem(cart.form.items[0], 0, true)
            assert.equal(cart.form.total, '25')
            cart.form.document_type_id = '80'
            assert.equal(applyPosScalePrices(cart.form, '0.16'), true)
            assert.equal(cart.form.items[0].total, '24.999975')
            cart.form.items[0].total = '10'
            cart.updateLocalPosTotal(0)
            cart.form.document_type_id = '01'
            assert.equal(applyPosScalePrices(cart.form, '0.16'), false)
            assert.equal(cart.form.items[0].total, '10')
            assert.equal(cart.form.items[0].price_input.currency_type_id, 'VES')
        })

        test('el carrito conserva lecturas distintas aunque coincida el precio de nota de venta', async () => {
            const item = product(1, { unit_type_id: 'KG', pos_stock: { control: false, components: [] } })
            const { cart, errors } = await scaleCart('80', item)
            cart.input_item = '1234599999000001'
            await cart.searchItemsBarcode()
            cart.input_item = '1234599998000001'
            await cart.searchItemsBarcode()
            cart.clickAddItem({ ...item, sale_unit_price: '0.333333' })
            assert.deepEqual(errors, [])
            assert.equal(cart.form.items.length, 4)
            assert.deepEqual(Array.from(cart.form.items.slice(1, 3), row => exactAmount(row.price_input.amount).decimal()), ['0.0001', '0.0001'])
            cart.form.document_type_id = '01'
            assert.equal(applyPosScalePrices(cart.form, '0.16'), true)
            assert.deepEqual(Array.from(cart.form.items, row => row.total), ['0.333333', '0.01', '0.01', '1'])
        })
    }

    for (const stock of [2, 3]) {
        test(`${view}: tres clics actualizan al instante una fila con stock para ${stock}`, () => {
            const { cart, requests, errors, snapshots } = checkout()
            const item = product(1, { pos_stock: { control: true, components: [component(1, String(stock))] } })
            cart.clickAddItem(item)
            cart.clickAddItem(item)
            cart.clickAddItem(item)
            assert.equal(cart.form.items.length, 1)
            assert.equal(cart.form.items[0].quantity, String(stock))
            assert.equal(cart.form.total, String(stock * 10))
            assert.equal(snapshots.at(-1)[0].quantity, String(stock))
            assert.deepEqual(requests, [])
            assert.equal(errors.length, stock === 2 ? 1 : 0)
        })
    }

    test(`${view}: el mismo producto comparte existencias entre precios, presentaciones y packs`, () => {
        const { cart, requests, errors } = checkout()
        const item = product()
        const box = { item_unit_type_id: 31, unit_type_id: 'UND', description: 'Caja', quantity_unit: '3' }
        cart.clickAddItem(item)
        cart.clickAddItem({ ...item, presentation: box, sale_unit_price: '25' })
        cart.clickAddItem({ ...item, presentation: box, sale_unit_price: '30' })
        const pack = product(5, { is_set: true, pos_stock: { control: true, components: [component(1, '10', '2')] } })
        cart.clickAddItem(pack)
        cart.clickAddItem(pack)
        assert.deepEqual(Array.from(cart.form.items, row => [row.item_id, row.price_input.amount, row.quantity]), [
            [5, '10', '1'], [1, '30', '1'], [1, '25', '1'], [1, '10', '1'],
        ])
        assert.deepEqual(requests, [])
        assert.match(errors[0], /Producto 1.*stock/)
    })

    test(`${view}: una factura VES conserva presentaciones distintas al restaurar precios con IDs mixtos`, () => {
        const { cart, errors, snapshots } = checkout()
        cart.form.document_type_id = '01'
        const unitIds = ['9007199254740992', '9007199254740993']
        const units = unitIds.map((id, index) => ({id, quantity_unit: '2', unit_type_id: 'UND',
            description: 'Caja', prices: [{id: 7 + index, price_label_id: 2}]}))
        const item = product(1, {item_unit_types: units})
        const selection = id => ({...item, presentation: {id: String(id), price_label_id: 2, quantity_unit: '2',
            unit_type_id: 'UND', description: 'Caja'}})

        cart.clickAddItem(selection(7))
        const storedRow = snapshots.at(-1)[0]
        delete storedRow.presentation.item_unit_type_id
        delete storedRow.item.presentation.item_unit_type_id
        storedRow.presentation.id = '7'
        storedRow.item.presentation.id = '7'
        cart.form.items = [storedRow]
        cart.clickAddItem(selection(8))
        assert.equal(cart.form.items.length, 2)
        assert.equal(cart.form.items[0].presentation.item_unit_type_id, unitIds[1])
        cart.clickAddItem(selection(7))
        assert.equal(cart.form.items.length, 2)
        const first = cart.form.items.find(row => row.presentation.id === unitIds[0])
        const second = cart.form.items.find(row => row.presentation.id === unitIds[1])
        assert.equal(first.item.presentation.id, unitIds[0])
        assert.equal(first.quantity, '2')
        assert.equal(second.quantity, '1')
        assert.equal(cart.form.total, '30')
        assert.deepEqual(errors, [])
    })

    test(`${view}: las ediciones y los incrementos usan el saldo local exacto`, () => {
        const { cart, requests, errors } = checkout()
        const item = product(1, { unit_type_id: 'KG',
            pos_stock: { control: true, components: [component(1, { n: '2000000000001', d: '1000000000000' })] },
        })
        cart.clickAddItem(item)
        let row = cart.form.items[0]
        row.item.aux_quantity = '1.000000000001'
        cart.clickAddItem(row, 0, true)
        row = cart.form.items[0]
        cart.incrementPosLine(row)
        assert.equal(cart.form.items[0].quantity, '2.000000000001')
        row = cart.form.items[0]
        row.item.aux_quantity = '2.000000000002'
        cart.clickAddItem(row, 0, true)
        assert.equal(cart.form.items[0].item.aux_quantity, '2.000000000001')
        assert.equal(cart.form.items[0].quantity, '2.000000000001')
        assert.deepEqual(requests, [])
        assert.equal(errors.length, 1)
    })

    test(`${view}: usa la fracción de la presentación para controlar existencias`, () => {
        const { cart, errors, requests } = checkout()
        const item = product(1, {
            presentation: { item_unit_type_id: 31, unit_type_id: 'UND', quantity_unit: '0.333333333333',
                calculation_snapshot: { values: { quantity_unit: { n: '1', d: '3' } } } },
            pos_stock: { control: true, components: [component(1, '0.999999999999')] },
        })
        cart.clickAddItem(item)
        cart.clickAddItem(item)
        cart.clickAddItem(item)
        assert.equal(cart.form.items[0].quantity, '2')
        assert.match(errors[0], /stock/)
        assert.deepEqual(requests, [])
    })

    test(`${view}: aplica el precio mínimo sobre la fracción de catálogo`, () => {
        const { cart, errors } = checkout()
        const item = product(1, {
            sale_unit_price: '0.1',
            calculation_snapshot: { values: { sale_unit_price: { n: '999999999999', d: '10000000000000' } } },
        })
        assert.equal(cart.clickAddItem(item), false)
        assert.equal(cart.form.items.length, 0)
        assert.match(errors[0], /0.1/)
    })

    test(`${view}: omite inventario para servicios y respeta el control desactivado`, () => {
        const { cart, requests, errors } = checkout()
        cart.clickAddItem(product(2, { unit_type_id: 'SERV', sale_affectation_igv_type_id: '20',
            pos_stock: { control: true, components: [] } }))
        cart.clickAddItem(product(3, { pos_stock: { control: false, components: [component(3, '0')] } }))
        assert.equal(cart.form.items.length, 2)
        assert.deepEqual(requests, [])
        assert.deepEqual(errors, [])
    })

    test(`${view}: elige una variación ya cargada sin consultar stock ni datos adicionales`, () => {
        const { cart, requests } = checkout()
        cart.selectVariationFromModal({ id: 5, sale_item: product(5) })
        assert.equal(cart.form.items[0].item_id, 5)
        assert.deepEqual(requests, [])
    })

    test(`${view}: el lector solo consulta el catálogo y valida con el saldo recibido`, async () => {
        const { cart, requests } = checkout()
        cart.$http = { async get(url) {
            requests.push(url)
            return { data: { items: [product(8)] } }
        } }
        await cart.searchItemsBarcode()
        assert.equal(cart.form.items[0].item_id, 8)
        assert.equal(cart.posBarcodePending, 0)
        assert.equal(requests.length, 1)
        assert.match(requests[0], /search_items/)
    })
}

