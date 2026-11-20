const {test}=require('node:test')
const assert=require('node:assert/strict')
const path=require('node:path')
const load=require('./helpers/load-module.cjs')
const {cartQuantity,cartLineAmount,cartSummaryAmounts,cartRestoreRecords}=load(path.resolve('resources/js/helpers/storefront-cart-amounts.js'))
const row=(price,quantity='1',type='20')=>({sale_unit_price:price,cantidad:quantity,currency_type_id:'VES',sale_affectation_igv_type_id:type})
test('cart tax derives rational base and IVA from the configured percentage',()=>{
 for(const [price,percentage,iva] of [['116','16','16'],['108','8','8'],['116.5','16.5','16.5']]) {
  const total=cartSummaryAmounts([row(price,'1','10')],{percentage})
  assert.equal(total.total_taxed,'100.00')
  assert.equal(total.total_igv,iva==='16.5' ? '16.50' : iva+'.00')
  assert.equal(total.total,price.includes('.') ? '116.50' : price+'.00')
 }
})
test('exempt cart rows have no IVA and never inherit a taxable base',()=>{
 const total=cartSummaryAmounts([row('116')],{percentage:'16'})
 assert.equal(total.total_exonerated,'116.00')
 assert.equal(total.total_igv,'0.00')
 assert.equal(total.total_taxed,'0.00')
})
test('cart total is rounded after adding subcent rows rather than their display projections',()=>{
 const a=row('0.005'),b=row('0.005')
 assert.equal(cartLineAmount(a).total.final(2),'0.01')
 assert.equal(cartLineAmount(b).total.final(2),'0.01')
 const total=cartSummaryAmounts([a,b])
 assert.equal(total.total,'0.01')
 assert.equal(total.calculation_snapshot.values.total.n,'1')
 assert.equal(total.calculation_snapshot.values.total.d,'100')
})
test('unchanged catalog prices retain their original third and quantity remains a cart request',()=>{
 const a=row('0.333333333333','3')
 a.calculation_snapshot={values:{sale_unit_price:{n:'1',d:'3'},quantity:{n:'999',d:'1'}}}
 const line=cartLineAmount(a)
 assert.equal(line.total.decimal(),'1')
 assert.equal(line.quantityText,'3')
 assert.equal(a.sale_unit_price,'0.333333333333')
 assert.equal(a.calculation_snapshot.values.quantity.n,'999')
})
test('a campaign price does not resurrect the catalog fraction even if projections coincide',()=>{
 const a=row('0.333333333333','3')
 a.calculation_snapshot={values:{sale_unit_price:{n:'1',d:'3'}}}
 a.discount_campaign_id=7
 assert.equal(cartLineAmount(a).total.decimal(),'0.999999999999')
})
test('cart USD conversion retains eight rate decimals until final display',()=>{
 const a={...row('0.1'),currency_type_id:'USD'}
 const line=cartLineAmount(a,{exchangeRate:'40.12345678'})
 assert.equal(line.total.decimal(),'4.012345678')
 assert.equal(line.total.final(2),'4.01')
 assert.throws(()=>cartLineAmount(a),/tasa/i)
 assert.equal(cartLineAmount(a,{convert:false}).total.decimal(),'0.1')
})
test('fractional requested quantities preserve twelve positions without inventing a recurring fraction',()=>{
 const line=cartLineAmount(row('3','0.333333333333'))
 assert.equal(line.quantityText,'0.333333333333')
 assert.equal(line.total.decimal(),'0.999999999999')
 assert.equal(line.total.final(2),'1.00')
})
test('coupon, campaign and shipping operations keep their fractions and pickup removes shipping',()=>{
 const options={coupon:'0.003',campaign:'0.002',delivery:'0.004',percentage:'16'}
 const total=cartSummaryAmounts([row('0.01')],options)
 assert.equal(total.calculation_snapshot.values.total.n,'9')
 assert.equal(total.calculation_snapshot.values.total.d,'1000')
 assert.equal(total.calculation_snapshot.values.delivery_igv.n,'2')
 assert.equal(total.calculation_snapshot.values.delivery_igv.d,'3625')
 const pickup=cartSummaryAmounts([row('0.01')],{...options,freeShipping:true})
 assert.equal(pickup.calculation_snapshot.values.total.n,'1')
 assert.equal(pickup.calculation_snapshot.values.total.d,'200')
 assert.equal(pickup.delivery,'0.00')
})
test('invalid quantity, range and retired tax classification fail before a cart calculation',()=>{
 for(const value of ['0','-1','0.0000000000001','1000000000000000000','invalid']) assert.throws(()=>cartQuantity(value))
 assert.throws(()=>cartLineAmount(row('1000000000000000000')))
 assert.throws(()=>cartSummaryAmounts([row('1','1','30')]),/afectación/)
 assert.throws(()=>cartSummaryAmounts([row('1')],{percentage:'-1'}))
 assert.throws(()=>cartSummaryAmounts([row('1')],{coupon:'-1'}))
})
test('cart calculations leave source rows and their snapshots unchanged',()=>{
 const a=row('123456789012345678.123456789012','0.000000000001','10')
 const before=JSON.stringify(a)
 const result=cartSummaryAmounts([a])
 assert.equal(result.total,'123456.79')
 assert.equal(JSON.stringify(a),before)
})
test('invalid stored cart input is rejected before a partial cart can replace the original',()=>{
 for(const encoded of ['invalid','{}',JSON.stringify([{id:1,quantity:'0',sale_unit_price:'1'}]),
  JSON.stringify([{id:1,quantity:'1',sale_unit_price:'1'},{id:2,quantity:'0.0000000000001',sale_unit_price:'1'}]),
  JSON.stringify([{id:1,quantity:'1',sale_unit_price:null}])]) assert.throws(()=>cartRestoreRecords(encoded))
 const hidden=cartRestoreRecords(JSON.stringify([{id:1,quantity:'0.333333333333',sale_unit_price:null}]),{allowUnavailablePrices:true})
 assert.equal(hidden[0].cantidad,'0.333333333333')
 assert.equal(hidden[0].sub_total,null)
 assert.equal(hidden[0].sale_unit_price,null)
})
