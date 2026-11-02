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

for (const file of ['index.vue', 'fast.vue', 'garage.vue']) {
    const component = compiler.parseComponent(fs.readFileSync(`resources/js/views/tenant/pos/${file}`, 'utf8'))
    const ast = parser.parse(component.script.content, {sourceType: 'module'})
    const methods = ast.program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration.properties.find(node => node.key.name === 'methods').value
    for (const name of ['blurCalculateQuantity', 'blurCalculateQuantity2']) {
        const method = methods.properties.find(node => node.key?.name === name)
        if (!method) continue
        const run = vm.runInNewContext(`({${generate(method).code}})[${JSON.stringify(name)}]`, {calculateRowItem, ensurePosRate, _: require('lodash')})
        test(`${file}: ${name} conserva la tasa al cambiar la cantidad en ambas monedas`, () => {
            for (const [source, target, price, expected] of [['USD', 'VES', '10', '250'], ['VES', 'USD', '125', '20']]) {
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
