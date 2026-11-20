const {test}=require('node:test'),assert=require('node:assert/strict')
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm')
const parser=require('@babel/parser'),traverse=require('@babel/traverse').default,generate=require('@babel/generator').default
const load=require('./helpers/load-module.cjs')
const math=load(path.resolve('resources/js/helpers/exchange-rate-math.js'))
const cartMath=load(path.resolve('resources/js/helpers/storefront-cart-amounts.js'))
const tree=parser.parse(fs.readFileSync('modules/Ecommerce/Resources/assets/js/frontend/cart-app.js','utf8'),{sourceType:'module'})
function method(name) {
 let found
 traverse(tree,{ObjectMethod(p){if(p.node.key?.name===name)found=p.node}})
 assert.ok(found,name)
 return vm.runInNewContext(`({${generate(found).code}})[${JSON.stringify(name)}]`,{...math,...cartMath})
}
function context() {
 return {records:[{id:999,description:'Borrador distinto',cantidad:'99',sale_unit_price:'999',currency_type_id:'VES'}],
  summary:{total:'999',total_taxed:'888',total_igv:'777',total_exonerated:'666',delivery:'555'},
  selectedPaymentMethod:'cash',isPickupMode:true,selectedPickupBranch:{name:'Borrador'},
  formatOrderNumber(){return '#CONFIRMADO'},getPaymentMethodLabel:method('getPaymentMethodLabel')}
}
test('confirmation uses the frozen order rather than a changed cart or current price',()=>{
 const order={id:41,total:'0.90',reference_payment:'transferencia',shipping_address:'Retiro confirmado',purchase:{checkout:{calculation:{
  items:[{item_id:7,description:'Producto confirmado',quantity:{n:'3',d:'1'},subtotal_ves:{n:'1',d:'1'},source_currency:'USD'}],
  total_payable:'0.90',shipping_amount:{n:'0',d:'1'},document:{totals:{total_taxed:{n:'0',d:'1'},total_igv:{n:'0',d:'1'},total_exonerated:{n:'9',d:'10'}}}
 }}}}
 const ctx=context(),before=JSON.stringify({order,records:ctx.records,summary:ctx.summary})
 const result=method('buildSuccessOrder').call(ctx,order)
 assert.equal(result.total,'0.90')
 assert.equal(result.items.length,1)
 assert.equal(result.items[0].description,'Producto confirmado')
 assert.equal(result.items[0].cantidad,'3')
 assert.equal(result.items[0].symbol,'Bs.')
 assert.equal(result.items[0].total,'1.00')
 assert.equal(result.total_exonerated,'0.90')
 assert.equal(result.total_igv,'0.00')
 assert.equal(result.delivery,'0.00')
 assert.equal(result.deliveryLabel,'Retiro confirmado')
 assert.equal(result.paymentLabel,'Transferencia bancaria')
 assert.equal(JSON.stringify({order,records:ctx.records,summary:ctx.summary}),before)
})
test('success modal prefers the confirmed large total to an obsolete formatted draft',()=>{
 const ctx={getSelectedReferencePayment(){return 'efectivo'},getPaymentMethodLabel(){return 'Efectivo'},
  getSuccessOrderItemsCount(){return 1},formatMoney:method('formatMoney')}
 method('syncSuccessModalSummary').call(ctx,{total:'9007199254740993.01',items:[{id:7}]},
  {number:'#41',paymentLabel:'Efectivo',items:[],total:'999'})
 assert.equal(ctx.successOrderTotal,'Bs. 9.007.199.254.740.993,01')
 assert.equal(ctx.successItemsCount,1)
})
test('legacy confirmed rows display stored subtotals without recalculating historical prices or tax',()=>{
 const result=method('buildSuccessOrder').call(context(),{id:41,total:'1',items:[
  {description:'Guardado',cantidad:'0.333333333333',sale_unit_price:'999',sub_total:'1',currency_type_id:'VES'}]})
 assert.equal(result.total,'1.00')
 assert.equal(result.items[0].description,'Guardado')
 assert.equal(result.items[0].cantidad,'0.333333333333')
 assert.equal(result.items[0].total,'1.00')
 assert.equal(result.total_igv,null)
})
test('missing confirmed data never borrows the current cart total or invents a zero charge',()=>{
 const result=method('buildSuccessOrder').call(context(),null)
 assert.equal(result.total,null)
 assert.equal(result.items.length,0)
 const ctx={getSelectedReferencePayment(){return 'efectivo'},getPaymentMethodLabel(){return 'Efectivo'},
  getSuccessOrderItemsCount(){return 0},formatMoney(){assert.fail('No confirmed amount exists')}}
 method('syncSuccessModalSummary').call(ctx,{},result)
 assert.equal(ctx.successOrderTotal,'—')
})
test('confirmation represents frozen quantity fractions and rounds original monetary fractions directly',()=>{
 const order={id:41,total:'1.00',purchase:{checkout:{calculation:{total_payable:'1.00',shipping_amount:{n:'0',d:'1'},
  items:[{description:'Fracción congelada',quantity:{n:'1',d:'3'},subtotal_ves:{n:'10049999999999999',d:'10000000000000000'}}],
  document:{totals:{total_taxed:{n:'0',d:'1'},total_igv:{n:'0',d:'1'},total_exonerated:{n:'10049999999999999',d:'10000000000000000'}}}
 }}}}
 const before=JSON.stringify(order),result=method('buildSuccessOrder').call(context(),order)
 assert.equal(result.items[0].cantidad,'0.333333333333')
 assert.equal(result.items[0].total,'1.00')
 assert.equal(result.total_exonerated,'1.00')
 assert.equal(result.total,'1.00')
 assert.equal(JSON.stringify(order),before)
})
