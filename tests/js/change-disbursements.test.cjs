const {test} = require('node:test')
const assert = require('node:assert/strict')
const path = require('node:path')
const fs = require('node:fs')
const vm = require('node:vm')
const babel = require('@babel/core')
const compiler = require('vue-template-compiler')
const {defaultChangeDisbursements, eligibleChangeMethods, validateChangeDisbursements} = require('./helpers/load-module.cjs')(path.resolve('resources/js/helpers/change-disbursements.js'))

const methods = [
    {id:'01', description:'Bolívares', collection_kind:'cash', currency_type_id:'VES', cash_currency_type_id:'VES', is_active:true, available_for_change:true},
    {id:'10', description:'Dólares', collection_kind:'cash', currency_type_id:'USD', cash_currency_type_id:'USD', is_active:true, available_for_change:true},
    {id:'11', description:'Banco', collection_kind:'account', currency_type_id:'VES', bank_account_id:2, is_active:true, available_for_change:true},
    {id:'12', description:'No disponible', collection_kind:'cash', currency_type_id:'VES', is_active:true, available_for_change:false},
    {id:'13', description:'Crédito', collection_kind:'credit', currency_type_id:'VES', is_active:true, available_for_change:true},
]
const destinations = [{id:'cash'}, {id:2, currency_type_id:'VES', establishment_id:7}]

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
    const script = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/partials/change_disbursement.vue','utf8')).script.content
    const exports = {}
    const load = require('./helpers/load-module.cjs')
    vm.runInNewContext(babel.transformSync(script, {configFile:false, babelrc:false, plugins:['@babel/plugin-transform-modules-commonjs']}).code, {
        exports, require: name => load(path.resolve('resources/js/helpers', path.basename(name) + '.js')),
    })
    const component = exports.default
    const events = []
    const ctx = {receipt:{currency_type_id:'USD',change:'8.20',exchange_rate:'100'}, lines:null,
        methods, destinations, establishmentId:7, eligible:eligibleChangeMethods(methods,destinations,7), suggestAmount:component.methods.suggestAmount,
        $emit:(event, value)=>events.push({event,value})}
    component.methods.open.call(ctx)
    assert.equal(ctx.draft[0].payment_method_type_id,'10')
    assert.equal(ctx.draft[0].amount,'8.20')
    ctx.draft[0].payment_method_type_id = '01'
    ctx.draft[0].amount = ''
    component.methods.suggestAmount.call(ctx,ctx.draft[0])
    assert.equal(ctx.draft[0].amount,'820.00')
    component.methods.apply.call(ctx)
    assert.equal(events[0].event,'apply')
    ctx.receipt.change = '8.30'
    component.methods.apply.call(ctx)
    assert.equal(events.length,1)
    assert.ok(ctx.error)
})
