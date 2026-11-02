const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const compiler = require('vue-template-compiler')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default
const load = require('./helpers/load-module.cjs')
const {isValidPosRate, restorePosDraftRate, refreshPosRate, ensurePosRate} = load(path.resolve('resources/js/helpers/pos-exchange-rate.js'))

function deferred() {
    let resolve, reject
    const promise = new Promise((yes, no) => { resolve = yes; reject = no })
    return {promise, resolve, reject}
}
function component(rate = null) {
    return {form: {exchange_rate_sale: rate, date_of_issue:'2026-10-31'}, exchangeRateError:'', errors:[],
        $message:{error(message) { this.errors.push(message) }}}
}

test('el borrador conserva su tasa válida y usa la actual solamente si le falta', () => {
    assert.equal(restorePosDraftRate({exchange_rate_sale:'25'}, '30').exchange_rate_sale, '25')
    assert.equal(restorePosDraftRate({exchange_rate_sale:null}, '30').exchange_rate_sale, '30')
    assert.equal(isValidPosRate('1'), true)
    for (const invalid of [null, '', '0', '-1', 'abc']) assert.equal(isValidPosRate(invalid), false)
})

test('la respuesta tardía no sobrescribe un borrador ni una edición posterior', async () => {
    const ctx = component(), pending = deferred()
    ctx.searchExchangeRateByDate = () => pending.promise
    const request = refreshPosRate(ctx)
    ctx.form = {exchange_rate_sale:'25', date_of_issue:'2026-10-31'}
    pending.resolve('30')
    await request
    assert.equal(ctx.form.exchange_rate_sale, '25')
    const next = deferred()
    ctx.searchExchangeRateByDate = () => next.promise
    const another = refreshPosRate(ctx)
    ctx.form.exchange_rate_sale = '27'
    next.resolve('31')
    await another
    assert.equal(ctx.form.exchange_rate_sale, '27')
})

test('una solicitud anterior no reemplaza la tasa de una solicitud más reciente', async () => {
    const ctx = component(), first = deferred(), second = deferred()
    let count = 0
    ctx.searchExchangeRateByDate = () => ++count === 1 ? first.promise : second.promise
    const a = refreshPosRate(ctx), b = refreshPosRate(ctx)
    second.resolve('40'); await b
    first.resolve('30'); await a
    assert.equal(ctx.form.exchange_rate_sale, '40.00000000')
})

test('fallo sin tasa bloquea el cálculo y permite recuperar mediante reintento', async () => {
    const ctx = component()
    ctx.$message.error = message => ctx.errors.push(message)
    ctx.searchExchangeRateByDate = async () => {throw new Error('sin servicio')}
    await refreshPosRate(ctx)
    assert.match(ctx.exchangeRateError, /No se pudo obtener/)
    assert.equal(ensurePosRate(ctx), false)
    assert.equal(ctx.errors.length, 1)
    ctx.searchExchangeRateByDate = async () => '35'
    await refreshPosRate(ctx)
    assert.equal(ctx.form.exchange_rate_sale, '35.00000000')
    assert.equal(ctx.exchangeRateError, '')
    assert.equal(ensurePosRate(ctx), true)
})

for (const file of ['index.vue', 'fast.vue', 'garage.vue']) {
    const vue = compiler.parseComponent(fs.readFileSync(`resources/js/views/tenant/pos/${file}`, 'utf8'))
    const ast = parser.parse(vue.script.content, {sourceType:'module'})
    const methods = ast.program.body.find(n => n.type === 'ExportDefaultDeclaration').declaration.properties.find(n => n.key.name === 'methods').value
    test(`${file}: bloquea cambio de moneda y apertura de pago sin tasa`, async () => {
        const ctx = component()
        ctx.$message.error = message => ctx.errors.push(message)
        ctx.form.currency_type_id = 'VES'
        for (const name of ['selectCurrencyType', 'changeCurrencyType', 'clickPayment']) {
            const method = methods.properties.find(n => n.key?.name === name)
            const run = vm.runInNewContext(`({${generate(method).code}})[${JSON.stringify(name)}]`, {ensurePosRate})
            await run.call(ctx)
            assert.equal(ctx.form.currency_type_id, 'VES')
        }
        assert.equal(ctx.errors.length, 3)
        assert.deepEqual(compiler.compile(vue.template.content).errors, [])
    })
}

for (const file of ['index.vue', 'fast.vue', 'garage.vue']) {
    const vue = compiler.parseComponent(fs.readFileSync(`resources/js/views/tenant/pos/${file}`, 'utf8'))
    const ast = parser.parse(vue.script.content, {sourceType:'module'})
    const methods = ast.program.body.find(n => n.type === 'ExportDefaultDeclaration').declaration.properties.find(n => n.key.name === 'methods').value
    for (const hasDraftRate of [true, false]) {
        test(`${file}: restaura borrador ${hasDraftRate ? 'con tasa' : 'sin tasa'} sin esperar una consulta pendiente`, async () => {
            const initial = deferred(), retry = deferred(), requests = []
            const draft = {exchange_rate_sale: hasDraftRate ? '25' : null, date_of_issue:'2026-10-30', total:'10'}
            const ctx = component()
            Object.assign(ctx, {resource:'pos', currency_types:[],
                $http:{get:async () => ({data:{affectation_igv_types:[], customers:[], establishment:{id:1},
                    currency_types:[{id:'VES'}], user:{}, categories:[]}})},
                renderCategories(){}, changeDateOfIssue(){}, async getPercentageIgv(){},
                initDateTimeIssue(){this.form.date_of_issue = '2026-10-31'},
                searchExchangeRateByDate(){const pending = requests.length ? retry : initial; requests.push(pending); return pending.promise},
                changeExchangeRate(){return refreshPosRate(this)}})
            const storage = {getItem:() => JSON.stringify(draft), setItem(){}}
            const scope = {localStorage:storage, withoutDraftRetentions:value => value, restorePosDraftRate, isValidPosRate}
            function extract(name) {
                const method = methods.properties.find(n => n.key?.name === name)
                return vm.runInNewContext(`({${generate(method).code}})[${JSON.stringify(name)}]`, scope)
            }
            const tables = extract('getTables').call(ctx)
            const completed = await Promise.race([tables.then(() => true), new Promise(resolve => setTimeout(() => resolve(false), 250))])
            assert.equal(completed, true, 'las tablas no deben esperar la respuesta de tasa')
            extract('getFormPosLocalStorage').call(ctx)
            assert.equal(ctx.form.total, '10')
            assert.equal(ctx.form.exchange_rate_sale, hasDraftRate ? '25' : null)
            assert.equal(requests.length, hasDraftRate ? 1 : 2)
            initial.resolve('30')
            await initial.promise
            await Promise.resolve()
            assert.equal(ctx.form.exchange_rate_sale, hasDraftRate ? '25' : null)
            if (!hasDraftRate) {
                retry.resolve('40')
                await retry.promise
                await Promise.resolve()
                assert.equal(ctx.form.exchange_rate_sale, '40.00000000')
            }
        })
    }
}

for (const file of ['index.vue', 'fast.vue', 'garage.vue']) {
    const vue = compiler.parseComponent(fs.readFileSync(`resources/js/views/tenant/pos/${file}`, 'utf8'))
    const ast = parser.parse(vue.script.content, {sourceType:'module'})
    const computed = ast.program.body.find(n => n.type === 'ExportDefaultDeclaration').declaration.properties.find(n => n.key.name === 'computed').value
    const method = computed.properties.find(n => n.key?.name === 'posRateUnavailable')
    const run = vm.runInNewContext(`({${generate(method).code}}).posRateUnavailable`, {isValidPosRate})
    test(`${file}: permite reintentar aun cuando la tasa sigue pendiente o es inválida`, () => {
        for (const rate of [null, '0', '-1', 'invalida']) assert.equal(run.call({form:{exchange_rate_sale:rate}}), true)
        assert.equal(run.call({form:{exchange_rate_sale:'1'}}), false)
        assert.match(vue.template.content, /v-if="currency_types.length && posRateUnavailable"[^>]*@click="changeExchangeRate"/)
        assert.deepEqual(compiler.compile(vue.template.content).errors, [])
    })
}

for (const file of ['index.vue', 'fast.vue', 'garage.vue']) {
    const vue = compiler.parseComponent(fs.readFileSync(`resources/js/views/tenant/pos/${file}`, 'utf8'))
    const ast = parser.parse(vue.script.content, {sourceType:'module'})
    const methods = ast.program.body.find(n => n.type === 'ExportDefaultDeclaration').declaration.properties.find(n => n.key.name === 'methods').value
    test(`${file}: impide modificar cantidad o total antes de obtener tasa`, () => {
        const template = compiler.compile(vue.template.content)
        assert.deepEqual(template.errors, [])
        const nodes = []
        function visit(node) {
            if (node.type === 1) nodes.push(node)
            for (const child of node.children || []) visit(child)
            for (const branch of (node.ifConditions || []).slice(1)) visit(branch.block)
            for (const slot of Object.values(node.scopedSlots || {})) visit(slot)
        }
        visit(template.ast)
        for (const model of ['item.item.aux_quantity','item.total']) {
            const inputs = nodes.filter(n => n.tag === 'el-input' && n.attrsMap['v-model'] === model)
            assert.equal(inputs.length, 1, `se esperaba el control visible ${model}`)
            assert.equal(inputs[0].attrsMap[':disabled'], 'posRateUnavailable')
        }
        const buttons = nodes.filter(n => n.tag === 'button' && /changeCartQuantity\(|decrementItem\(|clickAddItem\(item, index\)/.test(n.attrsMap['@click'] || ''))
        if (file !== 'fast.vue') {
            assert.equal(buttons.length, 2)
            for (const button of buttons) assert.equal(button.attrsMap[':disabled'], 'posRateUnavailable')
            const name = file === 'index.vue' ? 'changeCartQuantity' : 'decrementItem'
            const method = methods.properties.find(n => n.key?.name === name)
            const run = vm.runInNewContext(`({${generate(method).code}})[${JSON.stringify(name)}]`, {ensurePosRate})
            const ctx = component()
            ctx.$message.error = message => ctx.errors.push(message)
            const item = {item:{aux_quantity:2}}
            run.call(ctx, item, 0, 1)
            assert.equal(item.item.aux_quantity, 2)
            assert.equal(ctx.errors.length, 1)
        }
    })
}

test('Garage resta una unidad sin truncar ni redondear cantidades fraccionarias', () => {
    const vue = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/pos/garage.vue', 'utf8'))
    const ast = parser.parse(vue.script.content, {sourceType:'module'})
    const methods = ast.program.body.find(n => n.type === 'ExportDefaultDeclaration').declaration.properties.find(n => n.key.name === 'methods').value
    const method = methods.properties.find(n => n.key?.name === 'decrementItem')
    const {exactAmount, exactInput} = load(path.resolve('resources/js/helpers/exchange-rate-math.js'))
    const run = vm.runInNewContext(`({${generate(method).code}}).decrementItem`, {ensurePosRate, exactAmount, exactInput})
    const ctx = component('10')
    ctx.$message.error = message => ctx.errors.push(message)
    ctx.clickAddItem = item => { ctx.updated = item.item.aux_quantity }
    ctx.clickDeleteItem = () => { ctx.deleted = true }
    for (const [before, after] of [['1.5','0.5'], ['2.000000000001','1.000000000001']]) {
        ctx.deleted = false
        const row = {item:{aux_quantity:before}}
        run.call(ctx, row, 0)
        assert.equal(ctx.deleted, false)
        assert.equal(ctx.updated, after)
    }
    run.call(ctx, {item:{aux_quantity:'1'}}, 0)
    assert.equal(ctx.deleted, true)
    ctx.deleted = false; ctx.updated = null
    run.call(ctx, {item:{aux_quantity:'1.0000000000001'}}, 0)
    assert.equal(ctx.deleted, false)
    assert.equal(ctx.updated, null)
    assert.equal(ctx.errors.length, 1)
})

test('Garage recalcula la línea en moneda documental después de restar una cantidad fraccionaria', async () => {
    const vue = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/pos/garage.vue', 'utf8'))
    const ast = parser.parse(vue.script.content, {sourceType:'module'})
    const methods = ast.program.body.find(n => n.type === 'ExportDefaultDeclaration').declaration.properties.find(n => n.key.name === 'methods').value
    const math = load(path.resolve('resources/js/helpers/exchange-rate-math.js'))
    const {calculateRowItem} = load(path.resolve('resources/js/helpers/functions.js'))
    const scope = {...math, ensurePosRate, calculateRowItem, _:require('lodash')}
    function extract(name) {
        const method = methods.properties.find(n => n.key?.name === name)
        return vm.runInNewContext(`({${generate(method).code}})[${JSON.stringify(name)}]`, scope)
    }
    const initial = {item_id:1, unit_type_id:'KG', sale_unit_price:'10',
        item:{id:1, currency_type_id:'USD', unit_price:'10', sale_unit_price:'10', has_igv:true, aux_quantity:'1.5'},
        affectation_igv_type_id:'10', affectation_igv_type:{id:'10',free:false},
        quantity:'1.5', discounts:[], charges:[], attributes:[], warehouse_id:1}
    const row = calculateRowItem(initial,'VES','12.5')
    row.item.aux_quantity = '1.5'
    const ctx = component('12.5')
    Object.assign(ctx, {form:{items:[row], currency_type_id:'VES', exchange_rate_sale:'12.5'}, items:[], percentage_igv:'0.16',
        $notify:() => ({close(){}}), async getStatusStock(id, qty){this.checkedQuantity=qty; return {success:true}},
        cleanInput(){}, calculateTotal(){}, setFormPosLocalStorage(){}, setDefaultDataPriceSelected(){},
        clickDeleteItem(){throw Error('No debe eliminar la partida')},
        clickAddItem(...args){this.pending = extract('clickAddItem').apply(this,args)}})
    extract('decrementItem').call(ctx,row,0)
    await ctx.pending
    assert.equal(ctx.checkedQuantity,'0.5')
    assert.equal(ctx.form.items[0].quantity,'0.5')
    assert.equal(ctx.form.items[0].total,'62.5')
})

test('POS varía cantidades decimales sin redondearlas a cuatro posiciones', () => {
    const vue = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/pos/index.vue', 'utf8'))
    const ast = parser.parse(vue.script.content, {sourceType:'module'})
    const methods = ast.program.body.find(n => n.type === 'ExportDefaultDeclaration').declaration.properties.find(n => n.key.name === 'methods').value
    const method = methods.properties.find(n => n.key?.name === 'changeCartQuantity')
    const math = load(path.resolve('resources/js/helpers/exchange-rate-math.js'))
    const run = vm.runInNewContext(`({${generate(method).code}}).changeCartQuantity`, {...math, ensurePosRate})
    const ctx = component('10')
    ctx.$message.error = message => ctx.errors.push(message)
    ctx.clickAddItem = item => { ctx.updated = item.item.aux_quantity }
    const row = {item:{aux_quantity:'1.000000000001'}}
    run.call(ctx,row,0,1)
    assert.equal(ctx.updated,'2.000000000001')
    run.call(ctx,row,0,-1)
    assert.equal(ctx.updated,'1.000000000001')
    row.item.aux_quantity = '1.0000000000001'; ctx.updated = null
    run.call(ctx,row,0,1)
    assert.equal(ctx.updated,null)
    assert.equal(ctx.errors.length,1)
    row.item.aux_quantity = '999999999999999999.999999999999'
    run.call(ctx,row,0,1)
    assert.equal(ctx.updated,null)
    assert.equal(ctx.errors.length,2)
})
