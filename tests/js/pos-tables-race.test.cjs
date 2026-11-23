const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const vue = require('vue-template-compiler')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default

function deferred() {
    let resolve
    const promise = new Promise(done => { resolve = done })
    return {promise, resolve}
}
function response(id) {
    return {data: {items: [], affectation_igv_types: [], customers: [], establishment: {id, customer_id: null},
        currency_types: [{id:'VES'}], user: {}, categories: []}}
}
for (const file of ['index', 'fast', 'garage']) {
    const component = vue.parseComponent(fs.readFileSync(`resources/js/views/tenant/pos/${file}.vue`, 'utf8'))
    const ast = parser.parse(component.script.content, {sourceType:'module'})
    const methods = ast.program.body.find(n => n.type === 'ExportDefaultDeclaration').declaration.properties.find(n => n.key?.name === 'methods').value
    const method = methods.properties.find(n => n.key?.name === 'getTables')
    const getTables = vm.runInNewContext(`({${generate(method).code}}).getTables`)
    const eventsMethod = methods.properties.find(n => n.key?.name === 'events')
    const events = vm.runInNewContext(`({${generate(eventsMethod).code}}).events`)
    const destroyMethod = ast.program.body.find(n => n.type === 'ExportDefaultDeclaration').declaration.properties.find(n => n.key?.name === 'beforeDestroy')
    const destroy = vm.runInNewContext(`({${generate(destroyMethod).code}}).beforeDestroy`)
    const createdMethod = ast.program.body.find(n => n.type === 'ExportDefaultDeclaration').declaration.properties.find(n => n.key?.name === 'created')
    const created = vm.runInNewContext(`({${generate(createdMethod).code}}).created`)
    function context(requests) {
        return {resource:'pos', form:{currency_type_id:'VES', establishment_id:1, document_type_id:'01', customer_id:null, items:[]},
            $http:{get:() => requests.shift().promise}, rememberPosStock(){}, renderCategories(){},
            changeDateOfIssue(){this.dates = (this.dates || 0) + 1}, changeExchangeRate(){this.rates = (this.rates || 0) + 1}, async getPercentageIgv(){}}
    }
    for (const edit of ['replace', 'mutate']) test(`${file}: tablas tardías respetan borrador ${edit}`, async () => {
        const pending = deferred(), ctx = context([pending]), running = getTables.call(ctx)
        if (edit === 'replace') ctx.form = {...ctx.form, currency_type_id:'USD', document_type_id:'80', customer_id:9}
        else Object.assign(ctx.form, {currency_type_id:'USD', document_type_id:'80', customer_id:9})
        pending.resolve(response(2))
        assert.equal(await running, false)
        assert.equal(ctx.form.currency_type_id, 'USD'); assert.equal(ctx.form.document_type_id, '80'); assert.equal(ctx.form.customer_id, 9)
        assert.equal(ctx.form.establishment_id, 1); assert.equal(ctx.rates, undefined)
    })
    test(`${file}: tablas antiguas no reemplazan una respuesta más reciente`, async () => {
        const first = deferred(), second = deferred(), ctx = context([first, second])
        const old = getTables.call(ctx), latest = getTables.call(ctx)
        second.resolve(response(3)); assert.equal(await latest, true)
        first.resolve(response(2)); assert.equal(await old, false)
        assert.equal(ctx.establishment.id, 3); assert.equal(ctx.rates, 1)
    })
    test(`${file}: inicializa el borrador antes de recibir la tasa`, async () => {
        const pending = deferred(), rate = deferred(), ctx = context([pending])
        let initialized = 0
        ctx.changeExchangeRate = () => rate.promise.then(() => { ctx.form.exchange_rate_sale = '40' })
        const running = getTables.call(ctx, () => { initialized++; ctx.form.customer_id = 4 })
        pending.resolve(response(3)); await running
        assert.equal(initialized, 1); assert.equal(ctx.form.customer_id, 4); assert.equal(ctx.form.establishment_id, 3)
        rate.resolve(); await rate.promise; await Promise.resolve()
        assert.equal(ctx.form.exchange_rate_sale, '40')
    })
    test(`${file}: no inicializa un borrador editado durante la carga`, async () => {
        const pending = deferred(), ctx = context([pending]); let initialized = 0
        const running = getTables.call(ctx, () => initialized++)
        ctx.form.currency_type_id = 'USD'; pending.resolve(response(3)); await running
        assert.equal(initialized, 0); assert.equal(ctx.form.currency_type_id, 'USD')
    })
    test(`${file}: completa sucursal y solicita tasa sin cambiar un producto agregado`, async () => {
        const pending = deferred(), ctx = context([pending])
        Object.assign(ctx.form, {establishment_id:null, date_of_issue:'2026-11-21', exchange_rate_sale:null})
        const running = getTables.call(ctx)
        ctx.form.items.push({id:7}); ctx.form.currency_type_id = 'USD'
        pending.resolve(response(3)); await running
        assert.equal(ctx.form.establishment_id, 3); assert.equal(ctx.form.currency_type_id, 'USD')
        assert.equal(ctx.form.items[0].id, 7); assert.equal(ctx.rates, 1)
    })
    test(`${file}: descarta tablas al desmontar`, async () => {
        const pending = deferred(), ctx = context([pending]); let initialized = 0
        ctx.cancelCustomerMarquee = () => {}
        const running = getTables.call(ctx, () => initialized++)
        destroy.call(ctx); pending.resolve(response(3)); assert.equal(await running, false)
        assert.equal(initialized, 0); assert.equal(ctx.establishment, undefined); assert.equal(ctx._posRateRequest, 1)
    })
    test(`${file}: el montaje no continúa después de desmontar durante tablas`, async () => {
        const pending = deferred(), ctx = context([pending]); let registrations = 0
        Object.assign(ctx, {loadPriceOptions:async()=>{}, loadConfiguration(){}, enabledSearchItemByBarcode(){},
            $store:{commit(){}}, initForm:async()=>{}, getTables, cancelCustomerMarquee(){},
            events(){registrations++}, getFormPosLocalStorage(){assert.fail('no debe restaurar después de desmontar')}})
        const mounting = created.call(ctx)
        while (!ctx._posTablesRequest) await new Promise(resolve => setImmediate(resolve))
        destroy.call(ctx); pending.resolve(response(3)); await mounting
        assert.equal(registrations, 0)
    })
    test(`${file}: venta exitosa inicializa el cliente antes de una espera de catálogo`, async () => {
        const pending = deferred(), records = deferred(), ctx = context([pending]), handlers = {}
        Object.assign(ctx, {place:'prod', establishment:{id:1}, $eventHub:{$on:(name, fn) => { handlers[name] = fn }},
            initForm(){this.form = {currency_type_id:'VES', establishment_id:null, customer_id:null, items:[]}},
            getTables, getRecords:() => records.promise, selectDefaultCustomer(){this.form.customer_id = 4},
            setFormPosLocalStorage(){this.saved = this.form.customer_id}, setOriginalAffectationToItems(){}})
        await events.call(ctx)
        const sale = handlers.saleSuccess(ctx.form)
        pending.resolve(response(3)); await new Promise(resolve => setImmediate(resolve))
        assert.equal(ctx.form.customer_id, 4); assert.equal(ctx.saved, 4); assert.equal(ctx.form.establishment_id, 3)
        records.resolve(); await sale
    })
}
