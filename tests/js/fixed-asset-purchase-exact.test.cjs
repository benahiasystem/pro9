const {test}=require('node:test')
const assert=require('node:assert/strict')
const fs=require('node:fs')
const vm=require('node:vm')
const compiler=require('vue-template-compiler')
const parser=require('@babel/parser')
const generate=require('@babel/generator').default
const t=require('@babel/types')
const runtime=require('./support/exact-runtime.cjs')
const math=runtime('exchange-rate-math')
const commercial=runtime('exact-commercial')
const {calculateRowItem}=require('./helpers/load-module.cjs')(require('node:path').resolve('resources/js/helpers/functions.js'))
function methods(path) {
    const component=compiler.parseComponent(fs.readFileSync(path,'utf8'))
    assert.deepEqual(compiler.compile(component.template.content).errors, [])
    const ast=parser.parse(component.script.content,{sourceType:'module'})
    const list=ast.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties.find(node=>node.key?.name==='methods').value.properties
    return name=>{
        const method=list.find(node=>node.key?.name===name)
        return vm.runInNewContext(`(${generate(t.functionExpression(null,method.params,method.body,false,method.async)).code})`,{
            ...math, commercialTotals:commercial.commercialTotals, calculateRowItem,
            normalizeExchangeRate:x=>x, _: {find:(items,filter)=>items.find(item=>item.id===filter.id)}
        })
    }
}
const form=methods('modules/Purchase/Resources/assets/js/views/fixed_asset_purchases/form.vue')
const item=methods('modules/Purchase/Resources/assets/js/views/fixed_asset_purchases/partials/item.vue')
test('fixed asset form sums exact line fractions and preserves total perception',()=>{
    const fraction={n:'1',d:'3'}
    const values={total_value:fraction,total_base_igv:fraction,total:fraction}
    const line={affectation_igv_type_id:'20',calculation_snapshot:{values}}
    const context={form:{items:[line,line,line],discounts:[],charges:[],total_prepayment:'0',total_perception:'0.1'}}
    context.inputTotalPerception=form('inputTotalPerception').bind(context)
    form('calculateTotal').call(context)
    assert.equal(context.form.total,'1')
    assert.equal(context.form.total_exonerated,'1')
    assert.equal(context.total_amount,'1.1')
})
test('loading an existing purchase keeps fractional quantity and selecting an asset keeps its original price',async()=>{
    const context={id:1,resource:'fixed-asset/purchases',calculateTotal(){},$http:{get:async()=>({data:{data:{fa_purchase:{items:[{quantity:'0.333333333333'}],exchange_rate_sale:'1'}}}})}}
    await form('isUpdate').call(context)
    assert.equal(context.form.items[0].quantity,'0.333333333333')
    const selected={id:1,purchase_unit_price:'123.123456789012',purchase_affectation_igv_type_id:'20'}
    const ctx={items:[selected],form:{fixed_asset_item_id:1}}
    item('changeItem').call(ctx)
    assert.equal(ctx.form.unit_price,'123.123456789012')
    assert.notEqual(ctx.form.item,selected)
})
test('fixed asset form preserves tax and discounts when switching currencies',()=>{
    const inputs=[['116','10'],['50','20']].map(([price,tax],index)=>({
        item:{id:index+1,unit_price:price,currency_type_id:'VES'},quantity:'1',unit_price:price,
        affectation_igv_type_id:tax,discounts:index ? [] : [{discount_type_id:'00',percentage:'10'}],charges:[]
    }))
    const ctx={form:{items:inputs.map(row=>calculateRowItem(row,'VES','10','0.16')),currency_type_id:'VES',
        exchange_rate_sale:'10',discounts:[{discount_type_id:'02',amount:'5'}],charges:[],total_prepayment:'0',total_perception:'0'},
        currency_type:{id:'VES'},currency_types:[{id:'VES'},{id:'USD'}],percentage_igv:'0.16'}
    ctx.inputTotalPerception=form('inputTotalPerception').bind(ctx)
    ctx.calculateTotal=form('calculateTotal').bind(ctx)
    ctx.calculateTotal()
    assert.equal(ctx.form.total,'148.6')
    assert.equal(ctx.form.total_igv,'13.6')
    ctx.form.currency_type_id='USD'
    form('changeCurrencyType').call(ctx)
    assert.equal(ctx.form.total,'14.86')
    assert.equal(ctx.form.total_igv,'1.36')
    assert.equal(ctx.form.items[0].price_input.currency_type_id,'VES')
    assert.equal(ctx.form.discounts[0].amount,'0.5')
    ctx.form.currency_type_id='VES'
    form('changeCurrencyType').call(ctx)
    assert.equal(ctx.form.total,'148.6')
    assert.equal(ctx.form.discounts[0].amount,'5')
})
test('currency conversion leaves the draft untouched when an advance cannot be represented',()=>{
    const messages=[]
    const row=calculateRowItem({item:{id:1,unit_price:'10',currency_type_id:'VES'},quantity:'1',unit_price:'10',
        affectation_igv_type_id:'20',discounts:[],charges:[]},'VES','3','0.16')
    const ctx={form:{items:[row],currency_type_id:'USD',exchange_rate_sale:'3',discounts:[],charges:[],
        total_prepayment:'1',total_perception:'0'},currency_type:{id:'VES'},currency_types:[{id:'VES'},{id:'USD'}],
        percentage_igv:'0.16',$message:{error:text=>messages.push(text)}}
    form('changeCurrencyType').call(ctx)
    assert.equal(ctx.form.currency_type_id,'VES')
    assert.equal(ctx.form.items[0],row)
    assert.equal(ctx.form.total_prepayment,'1')
    assert.match(messages[0],/sin pérdida de precisión/)
})
test('currency conversion rejects historical lines without a source price',()=>{
    const errors=[]
    const row={id:9,quantity:'1',unit_price:'10',item:{id:1},affectation_igv_type_id:'20',calculation_snapshot:{values:{}}}
    const ctx={form:{items:[row],currency_type_id:'USD',exchange_rate_sale:'10',discounts:[],charges:[],total_prepayment:'0'},
        currency_type:{id:'VES'},currency_types:[{id:'VES'},{id:'USD'}],percentage_igv:'0.16',$message:{error:text=>errors.push(text)}}
    form('changeCurrencyType').call(ctx)
    assert.equal(ctx.form.currency_type_id,'VES')
    assert.equal(ctx.form.items[0],row)
    assert.match(errors[0],/no conserva el precio/)
})
