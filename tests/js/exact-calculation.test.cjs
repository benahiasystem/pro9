const {test, before} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
let math, helpers, totals, commercial
before(async () => {
    const url = file => `data:text/javascript;base64,${Buffer.from(fs.readFileSync(file, 'utf8')).toString('base64')}`
    const mathUrl = url('resources/js/helpers/exchange-rate-math.js')
    math = await import(mathUrl)
    const moduleUrl = file => `data:text/javascript;base64,${Buffer.from(fs.readFileSync(file, 'utf8').replaceAll('./exchange-rate-math', mathUrl)).toString('base64')}`
    helpers = await import(moduleUrl('resources/js/helpers/functions.js'))
    totals = await import(moduleUrl('resources/js/helpers/exact-document.js'))
    const commercialSource=fs.readFileSync('resources/js/helpers/exact-commercial.js','utf8')
        .replaceAll('./exchange-rate-math',mathUrl).replaceAll('./exact-document',moduleUrl('resources/js/helpers/exact-document.js'))
    commercial=await import(`data:text/javascript;base64,${Buffer.from(commercialSource).toString('base64')}`)
})
const input = (quantity = '1') => ({quantity, item: {id: 1, currency_type_id: 'VES', unit_price: '500'}, affectation_igv_type_id: '10', discounts: [], charges: []})
test('shared PHP and JavaScript cases produce the same normalized fractions', () => {
    for(const scenario of JSON.parse(fs.readFileSync('tests/Fixtures/exact-calculations.json','utf8'))) {
        const source={...scenario.input,item:{id:1,currency_type_id:'VES',unit_price:scenario.input.unit_price}}
        const line=helpers.calculateRowItem(source,source.currency_type_id || 'VES',source.exchange_rate_sale || '1',math.exactAmount(scenario.percentage).dividedBy(100).decimal())
        for(const [field,value] of Object.entries(scenario.expected)) assert.deepEqual(math.exactValue(line,field).fraction(),value,scenario.name+': '+field)
    }
})

test('commercial totals retain all mixed IVA lines and do not compound global adjustments', () => {
    const taxed=helpers.calculateRowItem(input(),'VES','1')
    const exempt=helpers.calculateRowItem({...input(),affectation_igv_type_id:'20'},'VES','1')
    const form={items:[taxed,exempt],discounts:[],charges:[]}
    const options={discount:'1',discountIsAmount:true,discountType:'02',chargePercentage:'3'}
    const first=commercial.commercialTotals(form,options)
    const second=commercial.commercialTotals({...form,...first},options)
    assert.equal(first.total,'1028.84')
    assert.deepEqual(second.calculation_snapshot,first.calculation_snapshot)
    assert.equal(first.total_exonerated,'500')
    const exemptDiscount=commercial.commercialTotals(form,{discount:'1',discountType:'05',discountIsAmount:true})
    assert.equal(exemptDiscount.total,'999')
    assert.equal(exemptDiscount.total_exonerated,'499')
})
test('price 500 and grouping survive exact totals and JSON round trips', () => {
    const line = helpers.calculateRowItem(input(), 'VES', '1')
    assert.equal(line.total, '500')
    assert.equal(line.total_value, '431.034482758621')
    assert.equal(line.total_igv, '68.965517241379')
    const loaded = JSON.parse(JSON.stringify(line))
    const hundred = totals.documentTotals(Array.from({length: 100}, () => loaded))
    assert.equal(hundred.total, '50000')
    assert.equal(hundred.total, helpers.calculateRowItem(input('100'), 'VES', '1').total)
    assert.equal(math.exactValue(loaded, 'unit_value').times('1.16').decimal(), '500')
})

test('warehouse selector fractions feed the first calculation and explicit price edits take precedence', () => {
    const source = input('3')
    source.affectation_igv_type_id = '20'
    source.item.unit_price = '0.333333333333'
    source.item.calculation_snapshot = math.exactSnapshot({unit_price:math.exactAmount(1).dividedBy(3)})
    const line = helpers.calculateRowItem(source, 'VES', '1')
    assert.equal(line.total, '1')
    assert.deepEqual(line.price_input.amount, {n:'1', d:'3'})
    const edit = helpers.calculateRowItem({...source, item:{...source.item, unit_price:'0.4'}}, 'VES', '1')
    assert.equal(edit.total, '1.2')
    const explicit = helpers.calculateRowItem({...source, price_input:{amount:'0.5',currency_type_id:'VES',includes_iva:true}}, 'VES', '1')
    assert.equal(explicit.total, '1.5')
})
test('fractions, large decimals and currency round trips are lossless', () => {
    const third = math.exactAmount(1).dividedBy(3)
    assert.equal(math.exactAmount(JSON.parse(JSON.stringify(third.fraction()))).times(3).decimal(), '1')
    assert.equal(math.exactAmount('9007199254740993.000000000001').decimal(), '9007199254740993.000000000001')
    assert.equal(math.exactAmount('500').dividedBy('873.86712345').times('873.86712345').decimal(), '500')
})
test('reopening lines restores original inputs for server recalculation', () => {
    const periodic={...input('166.666666666667'),affectation_igv_type_id:'20',
        item:{id:1,currency_type_id:'VES',unit_price:'3'},quantity_input:{amount:'500',unit_price:'3'}}
    const first=helpers.calculateRowItem(periodic,'VES','1')
    const loaded=JSON.parse(JSON.stringify(first));delete loaded.quantity_input;delete loaded.price_input
    const reopened=helpers.calculateRowItem(loaded,'VES','1')
    assert.deepEqual(reopened.quantity_input,{amount:'500',unit_price:'3'})
    const posted=JSON.parse(JSON.stringify(reopened));delete posted.calculation_snapshot
    assert.equal(helpers.calculateRowItem(posted,'VES','1').total,'500')

    const converted=helpers.calculateRowItem({...input(),affectation_igv_type_id:'20'},'USD','873.86712345')
    assert.deepEqual(converted.price_input,{amount:'500',currency_type_id:'VES',includes_iva:true})
    const request=JSON.parse(JSON.stringify(converted));delete request.calculation_snapshot
    assert.deepEqual(helpers.calculateRowItem(request,'USD','873.86712345').calculation_snapshot.values.total,
        {n:'10000000000',d:'17477342469'})
})
test('largest remainders conserve each cents target deterministically', () => {
    assert.deepEqual(math.allocateExact('100', [1, 1, 1]), ['33.34', '33.33', '33.33'])
    assert.deepEqual(math.allocateExact('1000', [1, 1, 1]), ['333.34', '333.33', '333.33'])
    assert.deepEqual(math.allocateExact('1', [1, 1, 1]), ['0.34', '0.33', '0.33'])
    assert.equal(math.exactSum(math.allocateExact('0.06', Array(10).fill('1'))).final(2), '0.06')
    const fees=[{},{},{}]
    commercial.allocateCommercialRows(fees,'amount','100')
    assert.deepEqual(fees,[{amount:'33.34',allocation_weight:'1'},{amount:'33.33',allocation_weight:'1'},{amount:'33.33',allocation_weight:'1'}])
})
test('a fractional global discount is preserved in persisted line snapshots', () => {
    const row = input()
    row.item.unit_price = '100'; row.affectation_igv_type_id = '20'
    row.discounts = [{discount_type_id: '00', from_global_distribution: true, is_amount: true, amount_exact: math.exactAmount(1).dividedBy(3).fraction()}]
    const line = helpers.calculateRowItem(row, 'VES', '1')
    assert.equal(totals.documentTotals([line, line, line]).total, '299')
})
