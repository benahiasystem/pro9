const {test}=require('node:test')
const assert=require('node:assert/strict')
const fs=require('node:fs'),vm=require('node:vm'),path=require('node:path')
const parser=require('@babel/parser'),traverse=require('@babel/traverse').default,generate=require('@babel/generator').default
const load=require('./helpers/load-module.cjs')
const math=load(path.resolve('resources/js/helpers/exchange-rate-math.js'))
const cartMath=load(path.resolve('resources/js/helpers/storefront-cart-amounts.js'))
const source=parser.parse(fs.readFileSync('modules/Ecommerce/Resources/assets/js/frontend/cart-app.js','utf8'),{sourceType:'module'})
function method(name,globals={}) {
 let found
 traverse(source,{ObjectMethod(p){if(p.node.key?.name===name)found=p.node}})
 assert.ok(found,name)
 return vm.runInNewContext(`({${generate(found).code}})[${JSON.stringify(name)}]`,{...math,...cartMath,clearTimeout(){},window:{addEventListener(){},removeEventListener(){},dispatchEvent(){}},Event:function(){},...globals})
}
test('checkout reuses server fiscal lines and totals without repeating browser IVA or discounts',async()=>{
 const canonical={items:[{cantidad:'3',precio_unitario:'0.333333333333',calculation_snapshot:{values:{unit_price:{n:'1',d:'3'}}}}],
  totales:{total_venta:'1',total_igv:'0.074074074074'},descuentos:[],cargos:[],codigo_tipo_moneda:'VES'}
 const ctx={checkoutPreviewSequence:4,checkoutPreviewState:'loading',
  form_document:{codigo_tipo_documento:'01',serie_documento:'OLD',totales:{total_venta:'999'}},
  async refreshCheckoutPreview(sequence){assert.equal(sequence,5);this.checkoutPreview={document:canonical};this.checkoutPreviewState='ready'}}
 const doc=await method('getDocument').call(ctx)
 assert.equal(doc.serie_documento,'F001')
 assert.equal(doc.totales.total_venta,'1')
 assert.equal(doc.items[0].calculation_snapshot.values.unit_price.d,'3')
 assert.equal(doc.codigo_tipo_moneda,'VES')
 assert.equal(doc.descuentos.length,0)
 doc.items[0].cantidad='999'
 assert.equal(canonical.items[0].cantidad,'3')
 assert.equal(ctx.form_document.totales.total_venta,'999')
})
test('checkout refuses incomplete, failed and superseded document previews',async()=>{
 for(const reason of ['missing','failed','superseded']) {
  const ctx={checkoutPreviewSequence:1,form_document:{},checkoutPreviewError:'El carrito cambió',
   async refreshCheckoutPreview(){this.checkoutPreview={document:reason==='missing' ? null : {items:[]}}
    this.checkoutPreviewState=reason==='failed' ? 'error' : 'ready'
    if(reason==='superseded')this.checkoutPreviewSequence++}}
  await assert.rejects(method('getDocument').call(ctx),/carrito cambió/)
 }
})
test('payment payload keeps the confirmed large amount and exact cents as decimal text',async()=>{
 const ctx={summary:{total:'0.01'},checkoutPreview:{total:'9007199254740993.01'},form_document:{},isLoggedIn:true,
  records:[],isPickupMode:true,refreshSetDataCustomer(){},buildShippingAddress(){return 'Retiro'},
  buildBackendPaymentCustomer(){return {nombre:'QA'}},async getDocument(){return {}},
  syncPurchaseCustomerData(value){return value},getSelectedReferencePayment(){return 'efectivo'}}
 const payload=await method('getFormPaymentCash').call(ctx)
 assert.equal(payload.precio_culqi,'9007199254740993.01')
 assert.equal(payload.precio,'900719925474099301.00')
 assert.equal(payload.total_discount,'0')
})
test('gateway amount guard checks JSON number roundtrip in addition to safe cents',()=>{
 const notices=[],ctx={checkoutPreviewState:'ready',checkoutPreview:{},summary:{},showSwalMessage:(...args)=>notices.push(args)}
 const check=method('gatewayPreviewMatches')
 for(const [value,accepted] of [['38.00',true],['0.10',true],['90071992547409.91',false],
  ['90071992547409.93',false],['90071992547409.90',true],['9007199254740993.01',false],['0.00',false]]) {
  ctx.checkoutPreview.total=ctx.summary.total=value
  assert.equal(check.call(ctx),accepted,value)
 }
 assert.equal(notices.length,4)
})
test('Mercado Pago initialization preserves its existing decimal text contract',()=>{
 const total='90071992547409.91'
 const init=method('getMpBrickInitData').call({summary:{total},form_document:{datos_del_cliente_o_receptor:{correo_electronico:'qa@example.test'}},refreshSetDataCustomer(){}})
 assert.equal(init.amount,total)
 const settings=method('buildMpBrickSettings').call({getStorePrimaryColor(){return '#000000'}},init)
 assert.equal(settings.initialization.amount,total)
 assert.equal(settings.initialization.payer.email,'qa@example.test')
})
function campaignContext() {
 const ctx={campaignValidationSequence:0,records:[{id:'9007199254740993',cantidad:'3',sale_unit_price:'0.333333333333',currency_type_id:'VES',sale_affectation_igv_type_id:'20'},
  {id:'9007199254740992',cantidad:'1',sale_unit_price:'1',currency_type_id:'VES',sale_affectation_igv_type_id:'20'}],
  getHeaderConfig(){return {}},calculateSummary(){this.summary=cartMath.cartSummaryAmounts(this.records,{campaign:this.appliedCampaignDiscount})},saveCartToLocalStorage(){this.saved=true}}
 ctx.updateRowSubtotal=method('updateRowSubtotal')
 return ctx
}
const campaignResponse=()=>({data:{success:true,discount:'0.10',calculation_snapshot:{values:{discount:{n:'1',d:'10'}}},items:[
 {item_id:'9007199254740993',campaign_id:7,campaign:'QA',base_unit_price:'0.333333333333',final_unit_price:'0.3',percentage:'10',
  calculation_snapshot:{values:{base_unit_price:{n:'1',d:'3'},final_unit_price:{n:'3',d:'10'}}}},
 {item_id:'9007199254740992',campaign_id:null,base_unit_price:'1',final_unit_price:'1',percentage:'0',calculation_snapshot:{values:{final_unit_price:{n:'1',d:'1'}}}}
]}})
test('campaign updates keep textual IDs, source fractions and avoid charging mixed carts twice',async()=>{
 let payload
 const ctx=campaignContext()
 await method('validateDiscountCampaigns',{axios:{post:async(url,data)=>{payload=data;return campaignResponse()}}}).call(ctx)
 assert.equal(payload.items[0].quantity,'3')
 assert.equal(payload.items[0].subtotal,'0.999999999999')
 assert.equal(ctx.records[0].sale_unit_price,'0.3')
 assert.equal(ctx.records[1].sale_unit_price,'1')
 assert.equal(ctx.records[0].sub_total,'0.90')
 assert.equal(ctx.appliedCampaignDiscount,'0')
 assert.equal(ctx.summary.total,'1.90')
 assert.equal(ctx.saved,true)
})
test('stale campaign prices never overwrite a different quantity or newer response',async()=>{
 for(const change of ['quantity','request']) {
  const ctx=campaignContext();let finish
  const run=method('validateDiscountCampaigns',{axios:{post:()=>new Promise(resolve=>{finish=resolve})}})
  const pending=run.call(ctx)
  if(change==='quantity')ctx.records[0].cantidad='4'
  else ctx.campaignValidationSequence++
  finish(campaignResponse());await pending
  assert.equal(ctx.records[0].sale_unit_price,'0.333333333333')
  assert.equal(ctx.saved,undefined)
 }
})
test('campaign price snapshots retain recurring values for fractional cart quantities',()=>{
 const row={cantidad:'3',sale_unit_price:'0.333333333333',currency_type_id:'VES',discount_campaign_id:1,
  campaign_discount_embedded:true,campaign_pricing_snapshot:{values:{final_unit_price:{n:'1',d:'3'}}}}
 assert.equal(cartMath.cartLineAmount(row).total.decimal(),'1')
 row.sale_unit_price='0.4'
 assert.equal(cartMath.cartLineAmount(row).total.decimal(),'1.2')
})
