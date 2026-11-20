const {test}=require('node:test'),assert=require('node:assert/strict')
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm')
const parser=require('@babel/parser'),traverse=require('@babel/traverse').default,generate=require('@babel/generator').default
const load=require('./helpers/load-module.cjs')
const cart=load(path.resolve('resources/js/helpers/storefront-cart-amounts.js'))
const tree=parser.parse(fs.readFileSync('modules/Ecommerce/Resources/assets/js/frontend/cart-app.js','utf8'),{sourceType:'module'})
function method(name) {
 let found
 traverse(tree,{ObjectMethod(p){if(p.node.key?.name===name)found=p.node}})
 assert.ok(found,name)
 return vm.runInNewContext(`({${generate(found).code}})[${JSON.stringify(name)}]`,{...cart})
}
function recommendation(name) {
 const source=fs.readFileSync('modules/Ecommerce/Resources/assets/js/frontend/components/FrequentlyBoughtTogether.vue','utf8').split('<script>')[1].split('</script>')[0]
 let found
 traverse(parser.parse(source,{sourceType:'module'}),{ObjectMethod(p){if(p.node.key?.name===name)found=p.node}})
 assert.ok(found,name)
 return vm.runInNewContext(`({${generate(found).code}})[${JSON.stringify(name)}]`,{...cart,
  ...load(path.resolve('resources/js/helpers/exchange-rate-math.js'))})
}
test('recommendations preserve adjacent large keys while retaining ordinary numeric keys and limit',()=>{
 const records=[{id:41},{id:'9007199254740993'},{id:'9007199254740994'},...Array.from({length:8},(_,i)=>({id:i+50}))]
 assert.deepEqual(Array.from(method('cartFbtItemIds').call({records})),[41,'9007199254740993','9007199254740994',50,51,52,53,54])
})
test('invalid and already-rounded numeric keys are excluded rather than aliased to another product',()=>{
 const records=[{id:Number('9007199254740993')},{id:0},{id:-1},{id:'1x'},{id:'01'},{id:'9223372036854775808'}, {id:'9223372036854775807'}]
 assert.deepEqual(Array.from(method('cartFbtItemIds').call({records})),['9223372036854775807'])
})
test('confirmed order labels preserve exact large ids for scalar and object inputs',()=>{
 const format=method('formatOrderNumber')
 for(const id of ['9007199254740993','9007199254740994','9223372036854775807']) {
  assert.equal(format(id),'#'+id);assert.equal(format({id}),'#'+id)
 }
 assert.equal(format(41),'#000041');assert.equal(format({id:41}),'#000041')
 assert.equal(format({id:'9007199254740993',order_code:'WEB-2026-0041'}),'#WEB-2026-0041')
})
test('unknown or irrecoverably rounded order identifiers do not invent a number',()=>{
 const format=method('formatOrderNumber')
 for(const id of [null,undefined,'',0,-1,Number('9007199254740993'),'1x','9223372036854775808']) {
  assert.equal(format(id),'#—');assert.equal(format({id}),'#—')
 }
})
test('restored cart preserves two adjacent text keys and rejects unsafe numeric cache data',()=>{
 const rows=['9007199254740993','9007199254740994'].map(id=>({id,quantity:'0.333333333333',sale_unit_price:'1'}))
 assert.deepEqual(Array.from(cart.cartRestoreRecords(JSON.stringify(rows)),r=>r.id),rows.map(r=>r.id))
 for(const id of [Number('9007199254740993'),'9223372036854775808','01']) {
  const encoded=JSON.stringify([{id,quantity:'1',sale_unit_price:'1'}])
  assert.throws(()=>cart.cartRestoreRecords(encoded),/producto inválido/)
  assert.equal(JSON.parse(encoded)[0].id,id)
 }
 assert.equal(cart.cartRestoreRecords(JSON.stringify([{id:41,quantity:'1',sale_unit_price:'1'}]))[0].id,41)
})
test('the recommendation component keeps exact ids in both single and batch requests',()=>{
 const ids=recommendation('resolvedIds')
 assert.deepEqual(Array.from(ids.call({itemIds:[41,'9007199254740993','9007199254740994'],itemId:null})),[41,'9007199254740993','9007199254740994'])
 assert.deepEqual(Array.from(ids.call({itemIds:[],itemId:'9223372036854775807'})),['9223372036854775807'])
 assert.deepEqual(Array.from(ids.call({itemIds:[],itemId:Number('9007199254740993')})),[])
})
test('recommendation prices display original fractions and exact large amounts at the final boundary',()=>{
 const format=recommendation('formatPrice')
 assert.equal(format('9007199254740993.01'),'9007199254740993.01')
 assert.equal(format('1.005',{values:{sale_unit_price:{n:'10049999999999999',d:'10000000000000000'}}}),'1.00')
 assert.equal(format('0.005'),'0.01')
 assert.equal(format(null),'—')
})
