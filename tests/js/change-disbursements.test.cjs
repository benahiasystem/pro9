// ######## INICIO PRUEBAS RESTANTE VUELTO ########
const {test} = require('node:test')
const assert = require('node:assert/strict')
const path = require('node:path')
const fs = require('node:fs')
const vm = require('node:vm')
const babel = require('@babel/core')
const compiler = require('vue-template-compiler')
const load = require('./helpers/load-module.cjs')
const {defaultChangeDisbursements, eligibleChangeMethods, suggestChangeDisbursementAmount, validateChangeDisbursements} = load(path.resolve('resources/js/helpers/change-disbursements.js'))

const methods = [
    {id:'01', description:'Bolívares', collection_kind:'cash', currency_type_id:'VES', cash_currency_type_id:'VES', is_active:true, available_for_change:true},
    {id:'10', description:'Dólares', collection_kind:'cash', currency_type_id:'USD', cash_currency_type_id:'USD', is_active:true, available_for_change:true},
    {id:'11', description:'Banco', collection_kind:'account', currency_type_id:'VES', bank_account_id:2, is_active:true, available_for_change:true},
    {id:'12', description:'No disponible', collection_kind:'cash', currency_type_id:'VES', is_active:true, available_for_change:false},
    {id:'13', description:'Crédito', collection_kind:'credit', currency_type_id:'VES', is_active:true, available_for_change:true},
]
const destinations = [{id:'cash'}, {id:2, currency_type_id:'VES', establishment_id:7}]

const selectorSource = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/partials/change_disbursement.vue','utf8'))
const selectorExports = {}
vm.runInNewContext(babel.transformSync(selectorSource.script.content, {configFile:false, babelrc:false, plugins:['@babel/plugin-transform-modules-commonjs']}).code, {
    exports:selectorExports, require:name => load(path.resolve('resources/js/helpers', path.basename(name) + '.js')),
})
const selector = selectorExports.default
function createSelector(overrides = {}) {
    const events = []
    const ctx = {...selector.data(), receipt:{currency_type_id:'VES',change:'1000.00',exchange_rate:'100.00000000'},
        lines:null, methods, destinations, establishmentId:7, ...overrides,
        events, $emit:(event,value) => events.push({event,value})}
    for (const [name,method] of Object.entries(selector.methods)) ctx[name] = method.bind(ctx)
    Object.defineProperty(ctx,'eligible',{get:selector.computed.eligible.bind(ctx)})
    return ctx
}

test('only enabled methods with a compatible actual destination can be used', () => {
    assert.deepEqual(Array.from(eligibleChangeMethods(methods,destinations,7), item=>item.id), ['01','10','11','12'])
    assert.deepEqual(Array.from(eligibleChangeMethods(methods,destinations,8), item=>item.id), ['01','10','12'])
    assert.deepEqual(Array.from(eligibleChangeMethods(methods,[]), item=>item.id), [])
    const unassigned = [{id:'cash'}, {id:2, currency_type_id:'VES', establishment_id:null, change_establishment_id:7}]
    assert.ok(eligibleChangeMethods(methods,unassigned,7).some(item=>item.id === '11'))
    assert.ok(!eligibleChangeMethods(methods,unassigned,8).some(item=>item.id === '11'))
    assert.ok(!eligibleChangeMethods(methods,[{id:2, currency_type_id:'VES', establishment_id:null}],7).some(item=>item.id === '11'))
})

test('split disbursements conserve the quoted change after exact conversion', () => {
    const quote={currency_type_id:'VES', change:'100.00', exchange_rate:'100.00000000'}
    const lines=[{payment_method_type_id:'10', amount:'0.40'}, {payment_method_type_id:'11', amount:'60.00'}]
    assert.equal(validateChangeDisbursements(lines,quote,methods,destinations,7).valid,true)
    assert.equal(validateChangeDisbursements(null,quote,methods,destinations).valid,false)
    assert.equal(validateChangeDisbursements(lines,{...quote,change:'101.00'},methods,destinations).valid,false)
    assert.equal(validateChangeDisbursements([{...lines[0],amount:'0.401'}, lines[1]],quote,methods,destinations).valid,false)
    assert.equal(validateChangeDisbursements([{payment_method_type_id:'13',amount:'100'}],quote,methods,destinations).valid,false)
    assert.equal(validateChangeDisbursements(null,{...quote,change:'0.00'},methods,destinations).valid,true)
    assert.equal(validateChangeDisbursements(lines,{...quote,exchange_rate:'0'},methods,destinations).valid,false)
})

test('dollar change can be returned using bolivars and preserves cent rounding', () => {
    const quote={currency_type_id:'USD',change:'0.70',exchange_rate:'911.62430000'}
    assert.equal(validateChangeDisbursements([{payment_method_type_id:'01',amount:'638.14'}],quote,methods,destinations).valid,true)
    assert.equal(validateChangeDisbursements([{payment_method_type_id:'01',amount:'648.14'}],quote,methods,destinations).valid,false)
})

test('the default selects active cash in the receipt currency even if its switch is off', () => {
    const eligible = [{...methods[0], available_for_change:false}, methods[1]]
    const lines = defaultChangeDisbursements({currency_type_id:'VES', change:'100', exchange_rate:'100'}, eligible, destinations, 7)
    assert.equal(lines[0].payment_method_type_id, '01')
    assert.equal(lines[0].amount, '100.00')
    assert.equal(defaultChangeDisbursements({currency_type_id:'VES',change:'0',exchange_rate:'100'},eligible,destinations,7), null)
    assert.equal(defaultChangeDisbursements({currency_type_id:'VES',change:'100',exchange_rate:'100'},[methods[2]],destinations,7), null)
})

test('selector suggests the converted amount and refuses a stale distribution', () => {
    const ctx = createSelector({receipt:{currency_type_id:'USD',change:'8.20',exchange_rate:'100'}})
    ctx.open()
    assert.equal(ctx.draft[0].payment_method_type_id,'10')
    assert.equal(ctx.draft[0].amount,'8.20')
    ctx.draft[0].payment_method_type_id = '01'
    ctx.draft[0].amount = ''
    ctx.suggestAmount(ctx.draft[0])
    assert.equal(ctx.draft[0].amount,'820.00')
    ctx.apply()
    assert.equal(ctx.events[0].event,'apply')
    ctx.receipt.change = '8.30'
    ctx.apply()
    assert.equal(ctx.events.length,1)
    assert.ok(ctx.error)
})

test('adding methods fills the remainder without changing existing rows or choosing a method', () => {
    const lines = [{payment_method_type_id:'01',amount:'100.00',reference:'Primero'}]
    const ctx = createSelector({lines})
    ctx.open()
    ctx.add()
    assert.deepEqual({...ctx.draft[1]}, {payment_method_type_id:null,amount:'900.00',reference:''})
    assert.deepEqual({...ctx.draft[0]}, lines[0])
    const second = ctx.draft[1]
    second.payment_method_type_id = '11'
    ctx.suggestAmount(second)
    second.amount = '300.00'
    ctx.markAmountEdited(second)
    ctx.add()
    assert.equal(ctx.draft[2].payment_method_type_id,null)
    assert.equal(ctx.draft[2].amount,'600.00')
    assert.equal(second.amount,'300.00')
    assert.equal(lines.length,1)
    assert.equal(lines[0].amount,'100.00')
})

test('suggested amounts convert to the chosen currency with multiple rows in either direction', () => {
    const ves = createSelector({lines:[{payment_method_type_id:'01',amount:'100.00',reference:''}]})
    ves.open()
    ves.add()
    const dollars = ves.draft[1]
    dollars.payment_method_type_id = '10'
    ves.suggestAmount(dollars)
    assert.equal(dollars.amount,'9.00')
    dollars.payment_method_type_id = '11'
    ves.suggestAmount(dollars)
    assert.equal(dollars.amount,'900.00')
    ves.apply()
    assert.equal(ves.events[0].event,'apply')

    const usd = createSelector({receipt:{currency_type_id:'USD',change:'10.00',exchange_rate:'100'},
        lines:[{payment_method_type_id:'01',amount:'100.00',reference:''}]})
    usd.open()
    usd.add()
    assert.equal(usd.draft[1].amount,'9.00')
    usd.draft[1].payment_method_type_id = '01'
    usd.suggestAmount(usd.draft[1])
    assert.equal(usd.draft[1].amount,'900.00')
    usd.apply()
    assert.equal(usd.events[0].event,'apply')
})

test('unselected rows use receipt currency provisionally and do not duplicate the remainder', () => {
    const ctx = createSelector({lines:[{payment_method_type_id:'01',amount:'100.00',reference:''}]})
    ctx.open()
    ctx.add()
    ctx.add()
    assert.equal(ctx.draft[1].amount,'900.00')
    assert.equal(ctx.draft[2].amount,'0.00')
    ctx.apply()
    assert.equal(ctx.events.length,0)
    assert.match(ctx.error,/método disponible/)
})

test('remainder arithmetic keeps decimal precision, large amounts and eight-place rates', () => {
    const receipt = {currency_type_id:'VES',change:'0.30',exchange_rate:'100'}
    const cents = [{payment_method_type_id:'01',amount:'0.10'}, {payment_method_type_id:'11',amount:'0.20'}]
    assert.equal(suggestChangeDisbursementAmount(cents,receipt,methods),'0.00')
    assert.equal(suggestChangeDisbursementAmount([{payment_method_type_id:'01',amount:'9007199254740993.00'}],
        {...receipt,change:'9007199254740993.10'},methods),'0.10')
    const thirds = [{payment_method_type_id:'01',amount:'1.00'}, {payment_method_type_id:'11',amount:'1.00'}]
    assert.equal(suggestChangeDisbursementAmount(thirds,{currency_type_id:'USD',change:'1.00',exchange_rate:'3'},methods),'0.33')
    assert.equal(suggestChangeDisbursementAmount([{payment_method_type_id:'10',amount:'1.00'}],
        {...receipt,change:'1000.00',exchange_rate:'911.62430001'},methods),'88.38')
    const bolivars = {payment_method_type_id:'01',amount:''}
    assert.equal(suggestChangeDisbursementAmount([{payment_method_type_id:'10',amount:'0.20'},bolivars],
        {currency_type_id:'USD',change:'1.00',exchange_rate:'911.62430001'},methods,bolivars),'729.30')
})

test('covered or overassigned change suggests zero and keeps application validation', () => {
    for (const amount of ['1000.00','1100.00']) {
        const ctx = createSelector({lines:[{payment_method_type_id:'01',amount,reference:''}]})
        ctx.open()
        ctx.add()
        assert.equal(ctx.draft[1].amount,'0.00')
        ctx.draft[1].payment_method_type_id = '11'
        ctx.suggestAmount(ctx.draft[1])
        assert.equal(ctx.draft[1].amount,'0.00')
        ctx.apply()
        assert.equal(ctx.events.length,0)
        assert.match(ctx.error,/importes positivos/)
    }
})

test('manual amounts are preserved even when they match the previous suggestion', () => {
    for (const amount of ['900.00','4.00']) {
        const ctx = createSelector({lines:[{payment_method_type_id:'01',amount:'100.00',reference:''}]})
        ctx.open()
        ctx.add()
        const line = ctx.draft[1]
        line.amount = amount
        ctx.markAmountEdited(line)
        line.payment_method_type_id = '10'
        ctx.suggestAmount(line)
        assert.equal(line.amount,amount)
        assert.equal(ctx.draft[0].amount,'100.00')
    }
    const ctx = createSelector({lines:[{payment_method_type_id:'01',amount:'100.00',reference:'Guardada'}]})
    ctx.open()
    ctx.draft[0].payment_method_type_id = '10'
    ctx.suggestAmount(ctx.draft[0])
    assert.equal(ctx.draft[0].amount,'100.00')
    assert.equal(ctx.lines[0].payment_method_type_id,'01')
})

test('removing a row and adding another recalculates from the rows that remain', () => {
    const ctx = createSelector({lines:[{payment_method_type_id:'01',amount:'100.00',reference:''},
        {payment_method_type_id:'10',amount:'3.00',reference:''}]})
    ctx.open()
    ctx.add()
    assert.equal(ctx.draft[2].amount,'600.00')
    ctx.draft.splice(2,1)
    ctx.draft.splice(1,1)
    ctx.add()
    assert.equal(ctx.draft[1].amount,'900.00')
    ctx.draft[1].payment_method_type_id = '10'
    ctx.suggestAmount(ctx.draft[1])
    assert.equal(ctx.draft[1].amount,'9.00')
})

test('invalid drafts or rates show an error without adding a row or changing amounts', () => {
    const invalidRows = [
        {payment_method_type_id:'01',amount:'abc',reference:''},
        {payment_method_type_id:'01',amount:'-1.00',reference:''},
        {payment_method_type_id:'01',amount:'100.001',reference:''},
        {payment_method_type_id:'missing',amount:'100.00',reference:''},
    ]
    for (const line of invalidRows) {
        const ctx = createSelector({lines:[line]})
        ctx.open()
        ctx.add()
        assert.equal(ctx.draft.length,1)
        assert.deepEqual({...ctx.draft[0]},line)
        assert.ok(ctx.error)
    }
    const ctx = createSelector({lines:[{payment_method_type_id:'01',amount:'100.00',reference:''}]})
    ctx.open()
    ctx.add()
    const line = ctx.draft[1]
    ctx.receipt.exchange_rate = '0'
    ctx.suggestAmount(line,'10')
    assert.equal(line.amount,'900.00')
    assert.equal(line.payment_method_type_id,null)
    assert.ok(ctx.error)
    ctx.add()
    assert.equal(ctx.draft.length,2)
    ctx.receipt.exchange_rate = '100'
    ctx.apply()
    assert.equal(ctx.events.length,0)
    ctx.suggestAmount(line,'10')
    assert.equal(line.amount,'9.00')
    assert.equal(line.payment_method_type_id,'10')
    assert.equal(ctx.error,'')
})

test('a failed method change preserves the selected method and its amount', () => {
    const ctx = createSelector({lines:[{payment_method_type_id:'01',amount:'100.00',reference:''}]})
    ctx.open()
    ctx.add()
    const line = ctx.draft[1]
    ctx.suggestAmount(line,'10')
    assert.equal(line.amount,'9.00')
    ctx.draft[0].amount = 'invalid'
    ctx.suggestAmount(line,'11')
    assert.equal(line.payment_method_type_id,'10')
    assert.equal(line.amount,'9.00')
    assert.equal(ctx.draft[0].amount,'invalid')
    assert.ok(ctx.error)
    ctx.draft[0].amount = '100.00'
    ctx.apply()
    assert.equal(ctx.events[0].event,'apply')
})

test('empty amounts can be suggested in multiple rows and UI state stays out of emitted lines', () => {
    const ctx = createSelector({lines:[{payment_method_type_id:'01',amount:'100.00',reference:''},
        {payment_method_type_id:'11',amount:'',reference:'Transferencia'}]})
    ctx.open()
    ctx.suggestAmount(ctx.draft[1])
    assert.equal(ctx.draft[1].amount,'900.00')
    ctx.apply()
    assert.equal(ctx.events[0].event,'apply')
    const sent = ctx.events[0].value
    assert.deepEqual(Array.from(sent,line => ({...line})), [
        {payment_method_type_id:'01',amount:'100.00',reference:''},
        {payment_method_type_id:'11',amount:'900.00',reference:'Transferencia'},
    ])
    assert.notEqual(sent[0],ctx.draft[0])
    ctx.clear()
    assert.equal(ctx.events[1].event,'clear')
})

test('selector template compiles and distinguishes manual amount input', () => {
    const compiled = compiler.compile(selectorSource.template.content)
    assert.deepEqual(compiled.errors,[])
    assert.ok(compiled.render.includes('markAmountEdited(line)'))
    assert.ok(compiled.render.includes('suggestAmount(line, $event)'))
})
// ######## FIN PRUEBAS RESTANTE VUELTO ########
