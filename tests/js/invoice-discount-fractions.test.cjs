const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm')
const compiler=require('vue-template-compiler'),parser=require('@babel/parser'),generate=require('@babel/generator').default
const load=require('./helpers/load-module.cjs'),math=load(path.resolve('resources/js/helpers/exchange-rate-math.js'))
const component=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/invoice_generate.vue','utf8'))
const tree=parser.parse(component.script.content,{sourceType:'module'})
const properties=tree.program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration.properties
const methods=properties.find(n=>n.key?.name==='methods').value.properties
const names=['itemDiscountExactAmount','itemDiscountDisplayAmount','isBaseDiscount','spreadItemDiscount','applyItemDiscount','filterSeries']
function context() {
    return {...vm.runInNewContext(`({${methods.filter(n=>names.includes(n.key?.name)).map(n=>generate(n).code).join(',')}})`,{...math,_:require('lodash')}),
        percentage_igv:'0.16',discount_types:[{id:'00',base:true}],form:{items:[]},$message:{warning(){}},
        clearGlobalDistributionDiscounts(){},recalculateDiscountedItems(){},calculateTotal(){}}
}
function line(amount,taxed=false) {
    const value=math.exactAmount(amount)
    return {affectation_igv_type_id:taxed?'10':'20',total_value:'100',total:'100',
        discounts:[{id:'own-discount',discount_type_id:'00',amount:value.decimal(),amount_exact:value.fraction()}]}
}
test('invoice scales a periodic discount from its original fraction in either IVA mode',()=>{
    for(const [taxed,target,expected] of [[false,'1','1'],[true,'0.58','0.5']]) {
        const ctx=context(),item=line({n:'1',d:'3'},taxed)
        ctx.spreadItemDiscount(item,target)
        assert.equal(math.exactAmount(item.discounts[0].amount_exact).compare(expected),0)
        assert.equal(ctx.itemDiscountExactAmount(item).compare(target),0)
        assert.equal(item.discounts[0].id,'own-discount')
    }
})
test('invoice retains a positive subprecision discount when scaling instead of adding another row',()=>{
    const ctx=context(),item=line({n:'1',d:'3000000000000'})
    assert.equal(ctx.itemDiscountDisplayAmount(item),'0')
    ctx.spreadItemDiscount(item,{n:'1',d:'6000000000000'})
    assert.equal(item.discounts.length,1);assert.equal(item.discounts[0].id,'own-discount')
    assert.equal(math.exactAmount(item.discounts[0].amount_exact).compare({n:'1',d:'6000000000000'}),0)
})
test('invoice subtracts the exact global discount and caps the own discount without a projection',()=>{
    const ctx=context(),item=line({n:'1',d:'3'})
    item.discounts.push({discount_type_id:'01',from_global_distribution:true,amount_exact:{n:'1',d:'3'}})
    ctx.form.items=[item];ctx.spreadItemDiscount=(_,value)=>{ctx.target=value}
    ctx.applyItemDiscount(0,'0.5');assert.equal(ctx.target.compare({n:'1',d:'6'}),0)
    ctx.applyItemDiscount(0,'200');assert.equal(ctx.target.compare({n:'301',d:'3'}),0)
})
test('invoice total discount sums periodic line values before rendering the result',()=>{
    const ctx=context();ctx.form.items=[line({n:'1',d:'3'}),line({n:'2',d:'3'})];ctx.form.discounts=[]
    const names = ['totalDiscountExact', 'totalDiscount']
    const methods = properties.find(n => n.key?.name === 'computed').value.properties.filter(n => names.includes(n.key?.name))
    const computed = vm.runInNewContext(`({${methods.map(method => generate(method).code).join(',')}})`, math)
    ctx.totalDiscountExact = computed.totalDiscountExact.call(ctx)
    assert.equal(computed.totalDiscount.call(ctx), '1')
})
test('invoice series selection distinguishes bigint establishments and seller series',()=>{
    const ctx=context();ctx.form={establishment_id:'9007199254740993',document_type_id:'01'};ctx.typeUser='seller'
    ctx.config={user:{document_id:'01',serie:'9007199254740993'}};ctx.is_contingency=false
    ctx.all_series=[{id:'9007199254740993',establishment_id:'9007199254740993',document_type_id:'01',contingency:false},
        {id:'9007199254740992',establishment_id:'9007199254740993',document_type_id:'01',contingency:false},
        {id:'9007199254740993',establishment_id:'9007199254740992',document_type_id:'01',contingency:false}]
    ctx.$store={commit(name,value){assert.equal(name,'setSeries');ctx.series=value}}
    ctx.filterSeries();assert.equal(ctx.series.length,1);assert.equal(ctx.form.series_id,'9007199254740993')
    ctx.typeUser='admin';ctx.filterSeries();assert.equal(ctx.series.length,2)
})
