const {test}=require('node:test')
const assert=require('node:assert/strict')
const fs=require('node:fs'),vm=require('node:vm'),path=require('node:path')
const parser=require('@babel/parser'),traverse=require('@babel/traverse').default,generate=require('@babel/generator').default
const load=require('./helpers/load-module.cjs')
const math=load(path.resolve('resources/js/helpers/exchange-rate-math.js'))
const {exactAmount}=math
const cartMath=load(path.resolve('resources/js/helpers/storefront-cart-amounts.js'))
const source=parser.parse(fs.readFileSync('modules/Ecommerce/Resources/assets/js/frontend/cart-app.js','utf8'),{sourceType:'module'})
function method(name,globals={}) {
 let found
 traverse(source,{ObjectMethod(p){if(p.node.key?.name===name)found=p.node}})
 assert.ok(found,name)
 return vm.runInNewContext(`({${generate(found).code}})[${JSON.stringify(name)}]`,{...math,...cartMath,window:{addEventListener(){},removeEventListener(){},dispatchEvent(){}},Event:function(){},...globals})
}
test('cart line multiplication preserves decimal prices beyond binary safe integers',()=>{
 const row={sale_unit_price:'9007199254740993.01',cantidad:'1',currency_type_id:'VES'}
 method('updateRowSubtotal').call({exchange_rate_sale:'1.00000000'},row)
 assert.equal(row.sub_total,'9007199254740993.01')
})
test('restoring a fractional cart quantity does not truncate it to zero',()=>{
 const ctx={records:[],initForm(){},restoreGuestCheckoutState(){},bindCheckoutIntentBootHandler(){},flushPendingCheckoutIntentChoice(){}}
 const restore=method('created',{localStorage:{getItem:()=>JSON.stringify([{id:1,quantity:'0.333333333333',sale_unit_price:'3'}])},dismissCheckoutIntentBootOverlay(){},document:{body:{style:{}}}})
 restore.call(ctx)
 assert.equal(exactAmount(ctx.records[0].cantidad).compare('0.333333333333'),0)
 assert.equal(ctx.records[0].sub_total,'1.00')
})
test('quotation modal total does not add binary fractions',()=>{
 const value=method('quotationModalTotal').call({quotationLines:[{total:'0.1'},{total:'0.2'}]})
 assert.equal(exactAmount(value).compare('0.3'),0)
})
test('cart summary preserves all digits before the final money display',()=>{
 const ctx={records:[{sale_affectation_igv_type_id:'20',sub_total:'9007199254740993.01'},{sale_affectation_igv_type_id:'20',sub_total:'0.10'}],
  summary:{},appliedCoupon:null,appliedCampaignDiscount:'0',deliveryZone:null,isPickupMode:true,form_document:{},payment_cash:{},
  scheduleCheckoutPreview(){},optionDocument(){},applyDocumentDefaults(){}}
 method('calculateSummary',{$:()=>({data(){}})}).call(ctx)
 assert.equal(ctx.summary.total,'9007199254740993.11')
})

test('quotation display is rounded directly from line fractions and the modal sums their snapshots',()=>{
 const records=[{id:1,cantidad:'1',sale_unit_price:'0.005',currency_type_id:'VES'},
  {id:2,cantidad:'1',sale_unit_price:'0.005',currency_type_id:'VES'}]
 const lines=method('quotationLines').call({records})
 assert.equal(lines[0].total,'0.01')
 assert.equal(lines[0].calculation_snapshot.values.total.n,'1')
 assert.equal(lines[0].calculation_snapshot.values.total.d,'200')
 assert.equal(method('quotationModalTotal').call({quotationLines:lines}),'0.01')
})
test('cart cache roundtrip stores the requested fraction instead of an obsolete quantity',()=>{
 let stored
 const a={id:1,quantity:'1',cantidad:'0.333333333333',sale_unit_price:'3'}
 method('saveCartToLocalStorage',{localStorage:{setItem:(key,value)=>{assert.equal(key,'products_cart');stored=value}}}).call({records:[a]})
 const cached=JSON.parse(stored)
 assert.equal(cached[0].quantity,'0.333333333333')
 const ctx={records:[],initForm(){},restoreGuestCheckoutState(){},bindCheckoutIntentBootHandler(){},flushPendingCheckoutIntentChoice(){}}
 method('created',{localStorage:{getItem:()=>stored},dismissCheckoutIntentBootOverlay(){},document:{body:{style:{}}}}).call(ctx)
 assert.equal(ctx.records[0].cantidad,'0.333333333333')
 assert.equal(ctx.records[0].sub_total,'1.00')
})
test('quantity buttons retain fractions and never replace a positive fraction with an integer',()=>{
 const a={id:1,cantidad:'0.333333333333',sale_unit_price:'3',currency_type_id:'VES'}
 const ctx={onQuantityChange(){},showSwalMessage(){assert.fail('La cantidad es válida.')}}
 method('incrementQuantity').call(ctx,a)
 assert.equal(a.cantidad,'1.333333333333')
 method('decrementQuantity').call(ctx,a)
 assert.equal(a.cantidad,'0.333333333333')
 method('decrementQuantity').call(ctx,a)
 assert.equal(a.cantidad,'0.333333333333')
})
test('money display preserves large values with the existing Venezuela punctuation',()=>{
 const money=method('formatMoney')
 assert.equal(money('9007199254740993.01'),'Bs. 9.007.199.254.740.993,01')
 assert.equal(money({n:'1',d:'3'}),'Bs. 0,33')
 assert.equal(money({n:'10049999999999999',d:'10000000000000000'}),'Bs. 1,00')
})
test('unchanged cart recalculation reuses its fraction snapshot and the quantity binding stays textual',()=>{
 const a={id:1,cantidad:'3',sale_unit_price:'0.333333333333',currency_type_id:'VES',
  calculation_snapshot:{values:{sale_unit_price:{n:'1',d:'3'}}}}
 const update=method('updateRowSubtotal')
 update.call({},a)
 const snapshot=a.cart_calculation_snapshot
 update.call({},a)
 assert.equal(a.cart_calculation_snapshot,snapshot)
 assert.equal(a.sub_total,'1.00')
 assert.equal(snapshot.values.total.n,'1')
 assert.equal(snapshot.values.total.d,'1')
 const blade=fs.readFileSync('modules/Ecommerce/Resources/views/cart/detail.blade.php','utf8')
 assert.match(blade,/type="text" inputmode="decimal" v-model="row\.cantidad"/)
 assert.doesNotMatch(blade,/v-model\.number="row\.cantidad"/)
})
test('external cart updates refresh checkout quantities once and invalid data blocks purchase',async()=>{
 let stored=JSON.stringify([{id:1,quantity:'0.333333333333',sale_unit_price:'3'}])
 const ctx={records:[],lastCartStorage:null,cartCalculationError:'',showCartPrices:true,checkoutPreviewSequence:2,
  updateRowSubtotal:method('updateRowSubtotal'),calculateSummary(){this.recalculations=(this.recalculations ?? 0)+1}}
 const sync=method('syncCartFromStorage',{localStorage:{getItem:()=>stored}})
 sync.call(ctx)
 assert.equal(ctx.records[0].cantidad,'0.333333333333')
 assert.equal(ctx.records[0].sub_total,'1.00')
 sync.call(ctx)
 assert.equal(ctx.recalculations,1)
 stored=JSON.stringify([{id:1,quantity:'0.0000000000001',sale_unit_price:'3'}])
 sync.call(ctx)
 assert.ok(ctx.cartCalculationError)
 assert.equal(ctx.checkoutPreviewState,'error')
 assert.equal(ctx.checkoutPreview,null)
 assert.equal(ctx.records[0].cantidad,'0.333333333333')
 await assert.rejects(method('getDocument').call(ctx))
})
test('restoring a broken saved cart leaves the cache intact and keeps Vue initialization usable',()=>{
 const ctx={records:[],initForm(){this.initialized=true},restoreGuestCheckoutState(){},bindCheckoutIntentBootHandler(){},flushPendingCheckoutIntentChoice(){}}
 method('created',{localStorage:{getItem:()=>'{broken',setItem(){assert.fail('Do not erase the user cache')}},dismissCheckoutIntentBootOverlay(){},document:{body:{style:{}}}}).call(ctx)
 assert.equal(ctx.records.length,0)
 assert.ok(ctx.cartCalculationError)
 assert.equal(ctx.initialized,true)
})
