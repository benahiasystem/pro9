const {test}=require('node:test')
const assert=require('node:assert/strict')
const fs=require('node:fs'),vm=require('node:vm'),babel=require('@babel/core'),compiler=require('vue-template-compiler')
const exactRuntime=require('./support/exact-runtime.cjs')
const parsed=compiler.parseComponent(fs.readFileSync('modules/Finance/Resources/assets/js/views/transfer_between_accounts/options.vue','utf8'))
assert.deepEqual(compiler.compile(parsed.template.content).errors,[])
const output={}
vm.runInNewContext(babel.transformSync(parsed.script.content,{configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']}).code,
    {exports:output,require:name=>exactRuntime(name)||(name.includes('vuex') ? {mapState:()=>({}),mapActions:()=>({})} : {})})
const component=output.default
function context(overrides={}) {
    const ctx={...component.data(),config:{currency_type_id:'VES'},exchange_rate_sale:'3.00000000',fullLoad:true,
        tempFrom:[{id:'B:1',currency_type_id:'VES'}],tempTo:[{id:'B:2',currency_type_id:'VES'}],
        $message:{error:()=>{}},$emit:()=>{},...overrides}
    for(const [name,method] of Object.entries(component.methods)) ctx[name]=method.bind(ctx)
    ctx.transfer_amount={from:'B:1',to:'B:2',amount_transform_temp:'1',amount_transform:'1',...(overrides.transfer_amount||{})}
    return ctx
}
test('transfer sends configured cash currency and decimal text without mutating the editable request',async()=>{
    let sent
    const ctx=context({$http:{post:async(url,body)=>{sent={url,body};return {data:{cash:[]}}}},
        transfer_amount:{from:'C:1',to:'B:2',amount_transform_temp:'0.123456789012',amount_transform:'1'}})
    ctx.clickClose=()=>{}
    await ctx.transferAmount()
    assert.equal(sent.body.data.cash_currency_type_id,'VES')
    assert.equal(sent.body.data.amount_transform_temp,'0.123456789012')
    assert.equal(typeof sent.body.data.amount_transform,'string')
    assert.equal(ctx.transfer_amount.cash_currency_type_id,undefined)
    assert.equal(ctx.loading,false)
})
test('transfer inputs retain textual values beyond safe integer magnitude and all twelve decimals',()=>{
    const ctx=context({transfer_amount:{amount_transform_temp:'123456789012345678.123456789012'}})
    ctx.maxTransfer='999999999999999999.999999999999'
    ctx.convertTotal()
    assert.equal(ctx.transfer_amount.amount_transform,'123456789012345678.123456789012')
    assert.equal(ctx.canTransfer(),true)
    assert.equal((parsed.template.content.match(/<el-input\b/g)||[]).length,2)
    assert.equal(parsed.template.content.includes('<el-input-number'),false)
})
test('transfer conversion settles a rational quotient only at the destination cents boundary',()=>{
    const ctx=context({tempTo:[{id:'B:2',currency_type_id:'USD'}],transfer_amount:{amount_transform_temp:'500'}})
    ctx.convertTotal()
    assert.equal(ctx.transfer_amount.amount_transform_temp,'500')
    assert.equal(ctx.transfer_amount.amount_transform,'166.67')
    assert.equal(ctx.canTransfer(),true)
    ctx.tempFrom[0].currency_type_id='USD';ctx.tempTo[0].currency_type_id='VES'
    ctx.exchange_rate_sale='40.12345678';ctx.transfer_amount.amount_transform_temp='0.1'
    ctx.convertTotal()
    assert.equal(ctx.transfer_amount.amount_transform,'4.01')
})
test('bank transfer limit uses the original balance fraction instead of parseFloat or its projection',()=>{
    const ctx=context({banks:[{id:'B:1',balance:'999999999999999999',calculation_snapshot:{values:{balance:{n:'1',d:'10000000000000'}}}}],
        transfer_amount:{amount_transform_temp:'0.000000000001'}})
    ctx.setMaxAmount()
    assert.equal(ctx.canTransfer(),false)
    assert.match(ctx.amountError,/saldo disponible/)
    ctx.banks[0].calculation_snapshot.values.balance={n:'123456789012345678123456789012',d:'1000000000000'}
    ctx.transfer_amount.amount_transform_temp='123456789012345678.123456789012'
    ctx.setMaxAmount()
    assert.equal(ctx.canTransfer(),true)
})
test('zero bank balance replaces a previous positive transfer limit',()=>{
    const ctx=context({banks:[{id:'B:1',balance:'0'}]})
    ctx.setMaxAmount()
    assert.equal(ctx.canTransfer(),false)
    assert.equal(ctx.maxTransfer.compare('0'),0)
})
test('invalid transfer amounts cannot become rounded valid debits or reach HTTP',async()=>{
    for(const value of ['1.0000000000001','0.0000000000001','1000000000000000000','-1','0','invalid','',0.1]) {
        let calls=0
        const ctx=context({transfer_amount:{amount_transform_temp:value},$http:{post:async()=>{calls++;return {data:{cash:[]}}}}})
        ctx.convertTotal()
        assert.equal(ctx.canTransfer(),false,String(value))
        await ctx.transferAmount()
        assert.equal(calls,0,String(value))
    }
})
test('a converted transfer outside the numeric range stays invalid without clipping its source',()=>{
    const ctx=context({tempFrom:[{id:'B:1',currency_type_id:'USD'}],tempTo:[{id:'B:2',currency_type_id:'VES'}],
        transfer_amount:{amount_transform_temp:'999999999999999999.999999999999'}})
    ctx.convertTotal()
    assert.match(ctx.amountError,/rango permitido/)
    assert.equal(ctx.canTransfer(),false)
    assert.equal(ctx.transfer_amount.amount_transform_temp,'999999999999999999.999999999999')
})
test('empty selections or equal accounts cannot submit a transfer',()=>{
    const ctx=context()
    ctx.transfer_amount.to=ctx.transfer_amount.from
    assert.equal(ctx.canTransfer(),false)
    ctx.transfer_amount.to=null
    assert.equal(ctx.canTransfer(),false)
})
test('server transfer validation is shown and releases the loading state',async()=>{
    let message
    const ctx=context({$http:{post:async()=>{throw {response:{data:{errors:{'data.amount_transform_temp':['Hasta doce decimales.']}}}}}},
        $message:{error:value=>{message=value}}})
    await ctx.transferAmount()
    assert.equal(message,'Hasta doce decimales.')
    assert.equal(ctx.loading,false)
})
test('clearing a transfer restores editable decimal text and removes stale errors',()=>{
    const ctx=context()
    ctx.amountError='Error anterior';ctx.clearForm()
    assert.equal(ctx.transfer_amount.amount_transform,'0')
    assert.equal(ctx.transfer_amount.amount_transform_temp,'0')
    assert.equal(ctx.amountError,null)
    assert.equal(ctx.canTransfer(),false)
})
