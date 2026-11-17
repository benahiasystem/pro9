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

function componentMethods(filename, names) {
    const { script, template } = compiler.parseComponent(fs.readFileSync(`resources/js/views/tenant/pos/${filename}.vue`, 'utf8'))
    assert.deepEqual(compiler.compile(template.content).errors, [])
    const ast = parser.parse(script.content, { sourceType: 'module' })
    const options = ast.program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration.properties
    const methods = options.find(node => node.key.name === 'methods').value.properties
    return vm.runInNewContext(`({${methods.filter(node => names.includes(node.key?.name)).map(node => generate(node).code).join(',')}})`, {
        ensurePosRate,
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
        'getQuantityFromElectronicScale', 'getUnitPriceFromElectronicScale',
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

