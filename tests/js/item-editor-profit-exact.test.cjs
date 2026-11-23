const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path')
const compiler=require('vue-template-compiler'),parser=require('@babel/parser'),generate=require('@babel/generator').default
const {exactAmount}=require('./helpers/load-module.cjs')(path.resolve('resources/js/helpers/exchange-rate-math.js'))
const source=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/items/form.vue','utf8'))
const tree=parser.parse(source.script.content,{sourceType:'module'})
const methods=tree.program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration.properties.find(n=>n.key?.name==='methods').value.properties
const wanted=['prepareExactProfitForm','profitInput','getCurrencySymbol','priceBreakdown','replayProfitEdits','recordProfitEdit','calculatePercentageOfProfitBySale','calculatePercentageOfProfitByPurchase','calculatePercentageOfProfitByPercentage']
function state(form, enabled) {
    const f=vm.runInNewContext(`({${methods.filter(n=>wanted.includes(n.key?.name)).map(n=>generate(n).code).join(',')}})`,{exactAmount})
    return {...f,form:{...form,exact_profit_base:Object.fromEntries(['sale_unit_price','purchase_unit_price','percentage_of_profit'].map(k=>[k,exactAmount(form[k]).fraction()])),exact_profit_sequence:[]},enabled_percentage_of_profit:enabled}
}
test('item editor retains exact large prices and decimal margins',()=>{
    const s=state({sale_unit_price:'1',purchase_unit_price:'1',percentage_of_profit:'0'},true)
    s.form.purchase_unit_price='9007199254740993';s.calculatePercentageOfProfitByPurchase()
    assert.equal(s.form.sale_unit_price,'9007199254740993')
    s.form.purchase_unit_price='0.1';s.calculatePercentageOfProfitByPurchase()
    s.form.percentage_of_profit='7';s.calculatePercentageOfProfitByPercentage()
    assert.equal(s.form.sale_unit_price,'0.107')
})
test('item breakdown uses saved fractions, configured tax and correct currency',()=>{
    const s=state({sale_unit_price:'1.005',purchase_unit_price:'1.005',percentage_of_profit:'0',currency_type_id:'USD'},false)
    const fraction={n:'2512499999999',d:'2500000000000'}
    s.form.exact_profit_base.sale_unit_price=fraction;s.form.exact_profit_base.purchase_unit_price=fraction
    s.currency_types=[{id:'USD',symbol:'$'}];s.exact_tax_percentage='10'
    assert.equal(s.priceBreakdown('sale_unit_price',false,'EXP'),'1.00 + 0.00 IVA = $ 1.00')
    assert.equal(s.priceBreakdown('purchase_unit_price',false,'20'),'1.00 + 0.00 IVA = $ 1.00')
    s.form.purchase_unit_price='100';s.calculatePercentageOfProfitByPurchase()
    assert.equal(s.priceBreakdown('purchase_unit_price',false,'10'),'100.00 + 10.00 IVA = $ 110.00')
    s.enabled_percentage_of_profit=true;s.form.percentage_of_profit='10';s.calculatePercentageOfProfitByPercentage()
    assert.equal(s.priceBreakdown('sale_unit_price',true,'10'),'100.00 + 10.00 IVA = $ 110.00')
    s.form.sale_unit_price='111';assert.equal(s.priceBreakdown('sale_unit_price',true,'10'),null)
})
test('disabled margin preserves other values and records independent edits across toggles',()=>{
    const s=state({sale_unit_price:'3',purchase_unit_price:'1',percentage_of_profit:'5'},false)
    s.form.purchase_unit_price='2';s.calculatePercentageOfProfitByPurchase()
    assert.equal(s.form.sale_unit_price,'3');assert.equal(s.form.percentage_of_profit,'5')
    assert.equal(s.form.exact_profit_sequence[0].recalculate,false)
    s.enabled_percentage_of_profit=true;s.form.percentage_of_profit='10';s.calculatePercentageOfProfitByPercentage()
    assert.equal(s.form.sale_unit_price,'2.2')
    s.enabled_percentage_of_profit=false;s.form.sale_unit_price='7';s.calculatePercentageOfProfitBySale()
    assert.equal(s.form.percentage_of_profit,'10');assert.equal(s.form.exact_profit_sequence[2].recalculate,false)
})
test('editing a sale price with zero cost resets margin even when disabled',()=>{
    const s=state({sale_unit_price:'3',purchase_unit_price:'0',percentage_of_profit:'15'},false)
    s.form.sale_unit_price='4';s.calculatePercentageOfProfitBySale()
    assert.equal(s.form.percentage_of_profit,0)
    assert.equal(s.form.exact_profit_sequence.length,2)
    assert.equal(s.form.exact_profit_sequence[1].recalculate,false)
})
test('item editor rejects incomplete decimals and lacks a cached base safely',()=>{
    const s=state({sale_unit_price:'1',purchase_unit_price:'1',percentage_of_profit:'0'},true)
    for(const value of ['', '-', '0.0000000000001']) assert.equal(s.profitInput(value),null)
    delete s.form.exact_profit_base;s.prepareExactProfitForm();assert.equal(s.form.exact_profit_unavailable,true)
    assert.match(source.script.content,/record\/\$\{this\.recordId\}\?fresh=1/)
})
