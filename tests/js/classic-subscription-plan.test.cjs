const {test}=require('node:test')
const assert=require('node:assert/strict')
const fs=require('node:fs'),vm=require('node:vm')
const parser=require('@babel/parser'),generate=require('@babel/generator').default,compiler=require('vue-template-compiler')

const source=compiler.parseComponent(fs.readFileSync('modules/Suscription/Resources/assets/js/payments/form.vue','utf8'))
const tree=parser.parse(source.script.content,{sourceType:'module'})
const methods=tree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties.find(node=>node.key.name==='methods').value
const node=methods.properties.find(method=>method.key?.name==='changePlan')
const changePlan=vm.runInNewContext(`({${generate(node).code}}).changePlan`,{_: {find:(rows,where)=>rows.find(row=>row.id===where.id)}})
const load=require('./helpers/load-module.cjs'),path=require('node:path')
const {normalizeExchangeRate}=load(path.resolve('resources/js/helpers/exchange-rate-math.js'))
const {ensureExchangeRateSale}=load(path.resolve('resources/js/helpers/ensure-exchange-rate-sale.js'))
const submitNode=methods.properties.find(method=>method.key?.name==='submit')
const prepareNode=methods.properties.find(method=>method.key?.name==='prepareExchangeRate')
const scope={
    _: {find:(rows,where)=>rows.find(row=>row.id===where.id)},normalizeExchangeRate,ensureExchangeRateSale,
    moment:()=>({format:()=> '2026-11-02'})
}
const submit=vm.runInNewContext(`({${generate(submitNode).code}}).submit`,scope)
const prepare=vm.runInNewContext(`({${generate(prepareNode).code}}).prepareExchangeRate`,scope)

test('classic enrollment copies the selected plan without changing its lines or inventing payments',()=>{
    const item={item_id:1,unit_price:'250.00',total:'250.00',item:{description:'Servicio'}}
    const plan={id:2,currency_type_id:'USD',items:[item],total:'250.00',total_taxed:'215.517241379310',
        total_igv:'34.482758620690',total_taxes:'34.482758620690',total_value:'215.517241379310'}
    const ctx={plans:[plan],config:{currency_type_id:'VES'},currency_types:[{id:'USD',symbol:'$'}],
        defaultStartDate:'2026-11-03',form:{suscription_plan_id:2,items:[],payments:[]},changeStartDate(){}}
    changePlan.call(ctx)
    assert.equal(ctx.form.currency_type_id,'USD')
    assert.equal(ctx.form.total,'250.00')
    assert.equal(ctx.form.total_igv,'34.482758620690')
    assert.equal(ctx.form.items.length,1)
    assert.notEqual(ctx.form.items[0],item)
    ctx.form.items[0].item.description='Otro'
    assert.equal(item.item.description,'Servicio')
    assert.equal(ctx.form.payments.length,0)
    assert.deepEqual(compiler.compile(source.template.content).errors,[])
})

test('classic enrollment obtains a visible valid rate and rejects an invalid manual rate',async()=>{
    const requests=[],errors=[]
    const ctx={showDialog:true,resource:'payments',plans:[{id:2,cat_period_id:1,quantity_period:1}],
        form:{suscription_plan_id:2,exchange_rate_sale:null},customer:{id:1},parent_customer:{id:1},children_customer:{id:2},
        $message:{error:m=>errors.push(m)},$eventHub:{$emit(){}},close(){},
        $http:{get:async url=>{requests.push(url);return {data:{sale:'40'}}},post:async (url,body)=>{requests.push({...body});return {data:{success:true}}}}}
    await submit.call(ctx)
    assert.equal(ctx.form.exchange_rate_sale,'40.00000000')
    assert.equal(requests[0],'/services/exchange/2026-11-02')
    assert.equal(requests[1].exchange_rate_sale,'40.00000000')
    ctx.form.exchange_rate_sale='-1'
    await submit.call(ctx)
    assert.equal(requests.length,2)
    assert.equal(errors.length,1)
    assert.equal(ctx.loading_submit,false)
})

test('late exchange rate lookup does not replace a manually entered rate',async()=>{
    let finish
    const ctx={showDialog:true,form:{exchange_rate_sale:null},$http:{get:()=>new Promise(resolve=>{finish=resolve})}}
    const pending=prepare.call(ctx)
    ctx.form.exchange_rate_sale='50'
    finish({data:{sale:'40'}})
    await pending
    assert.equal(ctx.form.exchange_rate_sale,'50')
    ctx.form.exchange_rate_sale=null
    ctx.$http.get=async()=>({data:{sale:'40'}})
    await prepare.call(ctx)
    assert.equal(ctx.form.exchange_rate_sale,'40.00000000')
})

test('rejected enrollment keeps the draft and displays the server validation',async()=>{
    const errors=[];let closed=false
    const form={suscription_plan_id:2,exchange_rate_sale:'40',items:[{total:'250'}]}
    const ctx={showDialog:true,resource:'payments',plans:[{id:2,cat_period_id:1,quantity_period:1}],form,
        customer:{id:1},parent_customer:{id:1},children_customer:{id:2},$message:{error:m=>errors.push(m)},
        $eventHub:{$emit(){}},close(){closed=true},$http:{post:async()=>{throw {response:{status:422,
            data:{errors:{exchange_rate_sale:['Tasa no disponible.']}}}}}}}
    await submit.call(ctx)
    assert.equal(closed,false)
    assert.equal(ctx.form,form)
    assert.equal(ctx.form.items[0].total,'250')
    assert.equal(ctx.errors.exchange_rate_sale[0],'Tasa no disponible.')
    assert.deepEqual(errors,['Tasa no disponible.'])
})
