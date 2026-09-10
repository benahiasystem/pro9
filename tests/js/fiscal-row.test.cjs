const {test, before} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')

// Execute the application source in memory; no bundle is generated.
let calculateRowItem, resolveSelectableAffectationType
before(async () => {
    globalThis._ = require('lodash')
    const source = fs.readFileSync(path.join(__dirname, '../../resources/js/helpers/functions.js'), 'utf8')
    const helpers = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`)
    ;({calculateRowItem, resolveSelectableAffectationType} = helpers)
})

function row(affectation = '10', price = 116, quantity = 1, currency = 'VES') {
    return {
        item: {id: 1, currency_type_id: currency, unit_price: price, has_igv: true},
        affectation_igv_type_id: affectation,
        affectation_igv_type: {id: affectation, free: false},
        quantity, discounts: [], charges: [], attributes: [], warehouse_id: 1,
    }
}

test('taxed line retains base, IVA and total', () => {
    const result = calculateRowItem(row(), 'VES', 1)
    assert.equal(result.total_value, 100)
    assert.equal(result.total_igv, 16)
    assert.equal(result.total, 116)
    assert.equal(result.percentage_igv, 16)
    for (const key of ['total_isc', 'total_plastic_bag_taxes', 'system_isc_type_id']) {
        assert.equal(Object.hasOwn(result, key), false)
    }
})

test('exempt quantity has zero IVA', () => {
    const result = calculateRowItem(row('20', 50, 3), 'VES', 1)
    assert.equal(result.total_value, 150)
    assert.equal(result.total_igv, 0)
    assert.equal(result.total, 150)
})

test('configured IVA rate overrides the default', () => {
    const result = calculateRowItem(row('10', 108), 'VES', 1, 0.08)
    assert.equal(result.total_value, 100)
    assert.equal(result.total_igv, 8)
    assert.equal(result.total, 108)
})

test('VES and USD conversion retains quantities and tax', () => {
    assert.equal(calculateRowItem(row('10', 116, 2, 'USD'), 'VES', 40).total, 9280)
    assert.equal(calculateRowItem(row('10', 4640, 2), 'USD', 40).total, 232)
})

test('a discount affecting the base reduces IVA proportionally', () => {
    const input = row()
    input.discounts = [{discount_type_id: '00', discount_type: {base: true}, percentage: 10, is_amount: false}]
    const result = calculateRowItem(input, 'VES', 1)
    assert.equal(result.total_discount, 10)
    assert.equal(result.total_base_igv, 90)
    assert.equal(result.total_igv, 14.4)
    assert.equal(result.total, 104.4)
})

test('unknown affectations are never converted or calculated', () => {
    const catalog = [{id: '10'}, {id: '20'}, {id: '30'}]
    for (const id of ['21', '30', '40', '', null]) {
        assert.equal(resolveSelectableAffectationType(id, catalog), null)
        assert.throws(() => calculateRowItem(row(id), 'VES', 1), /Gravado o Exento/)
    }
    assert.equal(resolveSelectableAffectationType('10', catalog), catalog[0])
    assert.equal(resolveSelectableAffectationType('20', catalog), catalog[1])
    assert.equal(resolveSelectableAffectationType('20', [{id: '10'}]), null)
})
