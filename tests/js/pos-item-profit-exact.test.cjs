const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path')
const compiler=require('vue-template-compiler'),parser=require('@babel/parser'),generate=require('@babel/generator').default
const {exactAmount}=require('./helpers/load-module.cjs')(path.resolve('resources/js/helpers/exchange-rate-math.js'))
const source=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/pos/partials/form.vue','utf8'))
const tree=parser.parse(source.script.content,{sourceType:'module'})
const methods=tree.program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration.properties.find(n=>n.key?.name==='methods').value.properties
const computed=tree.program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration.properties.find(n=>n.key?.name==='computed').value.properties
const wanted=['prepareExactProfitForm','profitInput','priceBreakdown','replayProfitEdits','markExactEdited','recordProfitEdit','calculatePercentageOfProfitBySale','calculatePercentageOfProfitByPurchase','calculatePercentageOfProfitByPercentage','submit']
function state(form) {
    const methodsObject=vm.runInNewContext(`({${methods.filter(n=>wanted.includes(n.key?.name)).map(n=>generate(n).code).join(',')}})`,{exactAmount})
    const base=form.exact_profit_base || Object.fromEntries(['sale_unit_price','purchase_unit_price','percentage_of_profit'].map(field=>[field,exactAmount(form[field]).fraction()]))
    return {...methodsObject,form:{...form,exact_profit_base:base,exact_profit_sequence:[]},errors:{},loading_submit:false,
        $message:{error(){}},$http:{post(){throw new Error('invalid data must not be posted')}}}
}
test('POS product margin calculations preserve decimal and large integer prices',()=>{
    assert.deepEqual(compiler.compile(source.template.content).errors,[])
    const s=state({purchase_unit_price:'0.1',sale_unit_price:'1',percentage_of_profit:'7'})
    s.calculatePercentageOfProfitByPercentage();assert.equal(s.form.sale_unit_price,'0.107')
    assert.deepEqual(Array.from(s.form.exact_edited_fields).sort(),['percentage_of_profit','sale_unit_price'])
    s.form.sale_unit_price='0.107';s.calculatePercentageOfProfitBySale();assert.equal(s.form.percentage_of_profit,'7')
    s.form.percentage_of_profit='0';s.calculatePercentageOfProfitByPercentage()
    s.form.purchase_unit_price='9007199254740993';s.calculatePercentageOfProfitByPurchase()
    assert.equal(s.form.sale_unit_price,'9007199254740993')
    assert.ok(s.form.exact_edited_fields.includes('purchase_unit_price'))
    s.form.purchase_unit_price='0';s.calculatePercentageOfProfitBySale();assert.equal(s.form.percentage_of_profit,'0')
    for (const [value, normalized] of [['1.','1'],['.1','0.1'],['1e-3','0.001']]) assert.equal(s.profitInput(value).decimal(),normalized)
})
test('POS tax breakdown uses exact configured rate, affectation and currency',()=>{
    const s=state({purchase_unit_price:'1',sale_unit_price:'1',percentage_of_profit:'0',currency_type_id:'USD'})
    s.currency_types=[{id:'USD',symbol:'$'}];s.exact_tax_percentage='10'
    s.form.sale_unit_price='9007199254740993.125';s.calculatePercentageOfProfitBySale()
    assert.equal(s.priceBreakdown('sale_unit_price',false,'10'),'9007199254740993.13 + 900719925474099.31 IVA = $ 9907919180215092.44')
    s.form.sale_unit_price='0.107';s.calculatePercentageOfProfitBySale()
    assert.equal(s.priceBreakdown('sale_unit_price',true,'10'),'0.10 + 0.01 IVA = $ 0.11')
    assert.equal(s.priceBreakdown('sale_unit_price',false,'20'),'0.11 + 0.00 IVA = $ 0.11')
    s.form.currency_type_id='VES'
    s.form.sale_unit_price='1';s.calculatePercentageOfProfitBySale()
    assert.equal(s.priceBreakdown('sale_unit_price',false,'10'),'1.00 + 0.10 IVA = Bs. 1.10')
    s.form.sale_unit_price='invalid';assert.equal(s.priceBreakdown('sale_unit_price',false,'10'),null)
    assert.equal(s.priceBreakdown('sale_unit_price',false,'unknown'),null)
})
test('POS breakdown rounds original fractions once and hides stale input',()=>{
    const fraction={n:'2512499999999',d:'2500000000000'}
    const s=state({purchase_unit_price:'1.005',sale_unit_price:'1.005',percentage_of_profit:'0',currency_type_id:'VES',
        exact_profit_base:{purchase_unit_price:fraction,sale_unit_price:fraction,percentage_of_profit:{n:'0',d:'1'}}})
    s.currency_types=[];s.exact_tax_percentage='16'
    assert.equal(s.priceBreakdown('sale_unit_price',false,'20'),'1.00 + 0.00 IVA = Bs. 1.00')
    assert.equal(s.priceBreakdown('purchase_unit_price',false,'20'),'1.00 + 0.00 IVA = Bs. 1.00')
    s.form.sale_unit_price='1.006';assert.equal(s.priceBreakdown('sale_unit_price',false,'20'),null)
    s.calculatePercentageOfProfitBySale()
    assert.equal(s.priceBreakdown('sale_unit_price',false,'20'),'1.01 + 0.00 IVA = Bs. 1.01')
})
test('purchase price label follows the purchase IVA flag independently',()=>{
    const get=vm.runInNewContext(`({${generate(computed.find(n=>n.key?.name==='purchaseIncludesIgv')).code}}).purchaseIncludesIgv`)
    assert.equal(get.call({form:{has_igv:true,purchase_has_igv:false}}),false)
    assert.equal(get.call({form:{has_igv:false,purchase_has_igv:true}}),true)
    assert.match(source.template.content,/v-if="purchaseIncludesIgv"/)
})
test('POS product form does not post incomplete or out-of-scale prices',()=>{
    const s=state({purchase_unit_price:'0.1',sale_unit_price:'1',percentage_of_profit:'7'})
    for (const value of ['', '-', '0.0000000000001', '1000000000000000000']) {
        s.form.purchase_unit_price=value;s.calculatePercentageOfProfitByPurchase();s.submit()
        assert.ok(s.errors.purchase_unit_price);assert.equal(s.loading_submit,false)
    }
})

test('recalculating with an unedited fractional margin uses its original rational value',()=>{
    const s=state({purchase_unit_price:'1000000000000',sale_unit_price:'1',percentage_of_profit:'0.333333333333',
        exact_profit_base:{purchase_unit_price:{n:'1',d:'1'},sale_unit_price:{n:'1',d:'1'},percentage_of_profit:{n:'1',d:'3'}}})
    s.calculatePercentageOfProfitByPurchase()
    assert.equal(s.form.sale_unit_price,'1003333333333.333333333333')
})
test('successive price edits retain intermediate rational results',()=>{
    const s=state({purchase_unit_price:'3',sale_unit_price:'1',percentage_of_profit:'-66.666666666667',
        exact_profit_base:{purchase_unit_price:{n:'3',d:'1'},sale_unit_price:{n:'1',d:'1'},percentage_of_profit:{n:'-200',d:'3'}}})
    s.form.sale_unit_price='2';s.calculatePercentageOfProfitBySale()
    assert.equal(s.form.percentage_of_profit,'-33.333333333333')
    s.form.purchase_unit_price='1000000000000';s.calculatePercentageOfProfitByPurchase()
    assert.equal(s.form.sale_unit_price,'666666666666.666666666667')
    assert.equal(s.form.exact_profit_sequence.length,2)
})
test('cached details without exact operands do not submit a price edit',()=>{
    const s=state({purchase_unit_price:'1',sale_unit_price:'1',percentage_of_profit:'0'})
    delete s.form.exact_profit_base;delete s.form.exact_profit_sequence
    s.prepareExactProfitForm();s.submit()
    assert.equal(s.form.exact_profit_unavailable,true);assert.equal(s.loading_submit,false)
})
