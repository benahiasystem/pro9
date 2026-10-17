const {test, before} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
let math, recipe
before(async () => {
    const url = source=>`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`
    const mathUrl = url(fs.readFileSync('resources/js/helpers/exchange-rate-math.js','utf8'))
    math = await import(mathUrl)
    recipe = await import(url(fs.readFileSync('resources/js/helpers/exact-recipe.js','utf8').replace('./exchange-rate-math',mathUrl)))
})
test('recipe costs use original fractions through waste, quantity and suggested margin', () => {
    const row = {cost:'0.333333333333',waste_percentage:'10',quantity:'3',
        calculation_snapshot:math.exactSnapshot({cost:math.exactAmount(1).dividedBy(3),waste_percentage:'10',quantity:'3'})}
    assert.deepEqual(recipe.recipeEffectiveCost(row).fraction(), {n:'11',d:'30'})
    const result = recipe.recipeTotals([row,row,row])
    assert.equal(result.production_cost,'3.3')
    assert.equal(result.suggested_price,'3.96')
    assert.equal(math.exactValue(JSON.parse(JSON.stringify(result)),'suggested_price').compare('3.96'),0)
})
test('decimal input rejects precision loss, overflow and already converted numbers', () => {
    assert.equal(math.exactInput('999999999999999999.123456789012'),'999999999999999999.123456789012')
    assert.equal(math.exactInput('1e-12'),'0.000000000001')
    assert.equal(math.exactInput('0.1000000000000'),'0.1')
    for (const value of ['0.0000000000001','1000000000000000000','NaN','1/3','',0.1]) assert.throws(()=>math.exactInput(value))
})
