const {test}=require('node:test')
const assert=require('node:assert/strict')
const fs=require('node:fs'),vm=require('node:vm'),babel=require('@babel/core'),compiler=require('vue-template-compiler')
function load(file){
    const parsed=compiler.parseComponent(fs.readFileSync(file,'utf8'))
    assert.deepEqual(compiler.compile(parsed.template.content).errors,[])
    const exported={}
    vm.runInNewContext(babel.transformSync(parsed.script.content,{configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']}).code,
        {exports:exported,require:name=>require('./support/exact-runtime.cjs')(name)||{}})
    return exported.default
}
const component=load('resources/js/views/system/payments/index.vue')
test('central payment records keep their original decimal text for editing',async()=>{
    const record={id:1,amount:'123.123456789012'}
    const ctx={resource:'payment-orders',filters:{},$http:{get:async()=>({data:{data:[record],pays:[]}})}}
    await component.methods.getRecords.call(ctx)
    assert.equal(ctx.records[0].amount,'123.123456789012')
})
test('central display rounds from the fraction without changing the editable value',()=>{
    const record={amount:'0.495',calculation_snapshot:{values:{amount:{n:'4949999999999999999',d:'10000000000000000000'}}}}
    assert.equal(component.methods.money(record,'amount'),'0.49')
    assert.equal(record.amount,'0.495')
})
test('tenant account templates use the same exact amount projection',()=>{
    for(const file of ['resources/js/views/tenant/account/configuration.vue','resources/js/views/tenant/account/payment_index.vue']) load(file)
})
