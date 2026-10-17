const {test}=require('node:test')
const assert=require('node:assert/strict')
const fs=require('node:fs'), vm=require('node:vm'), babel=require('@babel/core')
const exportsObject={}
vm.runInNewContext(babel.transformSync(fs.readFileSync('resources/js/mixins/functions.js','utf8'),{
    configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs'],
}).code,{exports:exportsObject,require:require('./support/exact-runtime.cjs')})
function context(){
    const mixin=exportsObject.pointSystemFunctions
    const ctx={...mixin.data(),form:{total:'1',calculation_snapshot:{values:{total:{n:'1',d:'3'}}},items:[]}}
    for(const [name,method] of Object.entries(mixin.methods))ctx[name]=method.bind(ctx)
    return ctx
}
test('loyalty display precision never changes exact redemption availability',()=>{
    const ctx=context()
    ctx.customer_accumulated_points_exact={n:'1',d:'3'}
    ctx.form.items=[{quantity:'1',calculation_snapshot:{values:{quantity:{n:'1',d:'3'}}},item:{exchanged_for_points:true,quantity_of_points:'1'}}]
    ctx.setTotalExchangePoints()
    assert.equal(ctx.calculate_customer_accumulated_points,'0')
    assert.equal(ctx.hasPointsAvailable(),true)
    assert.equal(ctx.hasExchangePoints(),true)
    ctx.form.items[0].quantity='0.333333333334'
    delete ctx.form.items[0].calculation_snapshot
    ctx.setTotalExchangePoints()
    assert.equal(ctx.hasPointsAvailable(),false)
    ctx.resetCustomerPoints()
    assert.equal(ctx.hasAccumulatedPoints(),false)
})
test('loyalty earned points use exact total, with zero-base protection',()=>{
    const ctx=context()
    ctx.setTotalPointsBySale({enabled_point_system:true,point_system_sale_amount:'3',quantity_of_points:'1',round_points_of_sale:false})
    assert.equal(ctx.total_points_by_sale,'0.111111111111')
    ctx.setTotalPointsBySale({enabled_point_system:true,point_system_sale_amount:'0',quantity_of_points:'1'})
    assert.equal(ctx.total_points_by_sale,'0')
})
test('a late response for the previous customer cannot replace the current points balance',async()=>{
    const ctx=context(), pending=[]
    ctx.$http={get:()=>new Promise(resolve=>pending.push(resolve))}
    const first=ctx.setCustomerAccumulatedPoints(1,true)
    const second=ctx.setCustomerAccumulatedPoints(2,true)
    pending[1]({data:{amount:'2',exact:{n:'2',d:'1'}}})
    await second
    pending[0]({data:{amount:'1',exact:{n:'1',d:'1'}}})
    await first
    assert.equal(ctx.customer_accumulated_points,'2')
})
test('final projections and allocations reject invalid scales without mutating their operands',()=>{
    const {exactAmount,allocateExact}=require('./support/exact-runtime.cjs')('exchange-rate-math')
    const third=exactAmount('1').dividedBy('3')
    for(const scale of [-1,13,2.5,'2',NaN]){
        assert.throws(()=>third.final(scale),/Precisión/)
        assert.throws(()=>allocateExact(third,['1','1'],scale),/Precisión/)
    }
    assert.equal(third.times('3').decimal(),'1')
})
