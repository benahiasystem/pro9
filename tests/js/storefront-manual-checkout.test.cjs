const {test}=require('node:test')
const assert=require('node:assert/strict')
const fs=require('node:fs'), vm=require('node:vm')
const parser=require('@babel/parser'), traverse=require('@babel/traverse').default
const generate=require('@babel/generator').default
const load=require('./helpers/load-module.cjs')
const {exactAmount}=load(require('node:path').resolve('resources/js/helpers/exchange-rate-math.js'))

function loadMethod(name,globals) {
    const tree=parser.parse(fs.readFileSync('modules/Ecommerce/Resources/assets/js/frontend/cart-app.js','utf8'),{sourceType:'module'})
    let method
    traverse(tree,{ObjectMethod(path) { if (path.node.key?.name===name) method=path.node }})
    assert.ok(method)
    return vm.runInNewContext(`({${generate(method).code}})[${JSON.stringify(name)}]`,globals)
}

test('el pedido usa el total confirmado del servidor y permite cancelar',async()=>{
    for (const confirmed of [false,true]) {
        const calls=[], dialogs=[]
        const globals={window:{__routes:{}},localStorage:{getItem:()=>JSON.stringify([{id:1}])},
            axios:{post:async (url,payload)=>{calls.push({url,payload:{...payload}});return {data:url.includes('preview')
                ? {success:true,subtotal:'9007199254740993.02',discount:'0.01',shipping:'0.00',rounding_adjustment:'0.00',total:'9007199254740993.01'}
                : {success:true,order:{id:1}}}}},
            swal:async options=>{dialogs.push(options);return {value:confirmed}},console}
        const run=loadMethod('paymentCash',globals)
        const ctx={allowPurchase:true,showPaymentLoading(){},hidePaymentLoading(){},getHeaderConfig(){return {}},
            getFormPaymentCash:async()=>({precio_culqi:1,items:[{id:1}]}),saveContactDataUser(){},showPurchaseSuccess(){}}
        await run.call(ctx)
        assert.equal(calls.length,confirmed ? 2 : 1)
        assert.equal(calls[0].url,'/ecommerce/checkout/preview')
        assert.match(dialogs[0].text,/9007199254740993\.01/)
        if (confirmed) assert.equal(calls[1].payload.precio_culqi,'9007199254740993.01')
    }
})

test('la pasarela se bloquea si el total visible y el importe enviado difieren',()=>{
    const run=loadMethod('gatewayPreviewMatches',{exactAmount})
    const notices=[]
    const ctx={checkoutPreviewState:'ready',checkoutPreview:{total:'38.00'},summary:{total:'37.99'},
        showSwalMessage:(...args)=>notices.push(args)}
    assert.equal(run.call(ctx),false)
    ctx.summary.total='38.00'; assert.equal(run.call(ctx),true)
    ctx.checkoutPreview.total='9007199254740993.01';ctx.summary.total=ctx.checkoutPreview.total
    assert.equal(run.call(ctx),false)
    ctx.checkoutPreviewState='loading'; assert.equal(run.call(ctx),false)
    assert.equal(notices.length,3)
})

test('una respuesta antigua no sustituye el resumen ni sus errores',async()=>{
    const pending=[]
    const run=loadMethod('refreshCheckoutPreview',{axios:{post:()=>new Promise((resolve,reject)=>pending.push({resolve,reject}))}})
    const ctx={records:[{id:1,cantidad:'1'}],isLoggedIn:false,appliedCoupon:null,isPickupMode:true,
        checkoutPreviewSequence:1,getHeaderConfig(){return {}}}
    const first=run.call(ctx,1)
    ctx.checkoutPreviewSequence=2; ctx.records[0].cantidad='2'
    const second=run.call(ctx,2)
    pending[1].resolve({data:{success:true,total:'2.00'}}); await second
    pending[0].resolve({data:{success:true,total:'1.00'}}); await first
    assert.equal(ctx.checkoutPreview.total,'2.00')
    assert.equal(ctx.checkoutPreviewState,'ready')
    ctx.checkoutPreviewSequence=3
    const third=run.call(ctx,3)
    pending[2].reject({response:{data:{message:'Seleccione zona de entrega.'}}}); await third
    assert.equal(ctx.checkoutPreview,null)
    assert.equal(ctx.checkoutPreviewState,'error')
    assert.equal(ctx.checkoutPreviewError,'Seleccione zona de entrega.')
})

test('el cupón se valida con productos del servidor y descarta un carrito modificado',async()=>{
    for (const changed of [false,true]) {
        let finish, payload
        const run=loadMethod('applyCoupon',{axios:{post:(url,data)=>{
            assert.equal(url,'/ecommerce/checkout/preview'); payload=data
            return new Promise(resolve=>{finish=resolve})
        }}})
        const ctx={couponField:' qa5 ',couponLoading:false,couponRequestId:0,records:[{id:4,cantidad:'3',sub_total:'0.01'}],
            isLoggedIn:false,getHeaderConfig(){return {}},calculateSummary(){this.recalculated=true}}
        const pending=run.call(ctx)
        assert.equal(payload.discount_coupon_code,'QA5')
        assert.equal(payload.items[0].cantidad,'3')
        assert.equal(payload.subtotal,undefined)
        if (changed) ctx.records[0].cantidad='4'
        finish({data:{success:true,discount:'1.80',coupon:{id:8,code:'QA5',free_shipping:false}}})
        await pending
        if (changed) {
            assert.equal(ctx.appliedCoupon,undefined)
            assert.match(ctx.couponMessage,/carrito cambió/i)
        } else {
            assert.equal(ctx.appliedCoupon.discount,'1.80')
            assert.equal(ctx.appliedCoupon.code,'QA5')
            assert.equal(ctx.recalculated,true)
        }
        assert.equal(ctx.couponLoading,false)
    }
})
