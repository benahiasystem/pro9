const {test}=require('node:test'),assert=require('node:assert/strict')
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),babel=require('@babel/core')
const load=require('./helpers/load-module.cjs')
function browser() {
 const stored=new Map(),window={__storefront_show_prices:true,dispatchEvent(){}}
 const runtime=babel.transformSync(fs.readFileSync('resources/js/storefront-amounts.js','utf8'),{configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']}).code
 vm.runInNewContext(runtime,{window,require:name=>load(path.resolve('resources/js',name+'.js'))})
 const nodes=new Map()
 const ctx={window,localStorage:{getItem:key=>stored.get(key) ?? null,setItem:(key,value)=>stored.set(key,value)},
  document:{getElementById:id=>nodes.get(id)},Event:function(){},console}
 vm.createContext(ctx)
 vm.runInContext(fs.readFileSync('public/porto-ecommerce/assets/js/cart.js','utf8'),ctx)
 ctx.cartRefreshHeader=()=>{}
 ctx.showCartConfirmModal=()=>{}
 return {ctx,window,stored,nodes,math:window.SabStorefrontAmounts}
}
const item=(data={})=>({id:'7',sale_unit_price:'0.333333333333',currency_type_id:'VES',stock:'20',
 campaign_pricing_snapshot:{values:{final_price:{n:'1',d:'3'}}},...data})
test('classic catalog cart stores large price text, fractional quantities and source price fractions',()=>{
 const {ctx,stored}=browser()
 const product=item({sale_unit_price:'9007199254740993.01',campaign_pricing_snapshot:null})
 ctx.cartAddOrUpdateItem(product,{quantity:'0.333333333333'})
 let rows=JSON.parse(stored.get('products_cart'))
 assert.equal(rows[0].quantity,'0.333333333333')
 assert.equal(rows[0].sale_unit_price,'9007199254740993.01')
 ctx.cartAddOrUpdateItem(product,{quantity:'0.333333333333'})
 rows=JSON.parse(stored.get('products_cart'))
 assert.equal(rows[0].quantity,'0.666666666666')
 assert.equal(rows[0].cantidad,'0.666666666666')
 ctx.cartAddOrUpdateItem(item({id:'8'}),{quantity:'3'})
 rows=JSON.parse(stored.get('products_cart'))
 assert.equal(rows[1].campaign_pricing_snapshot.values.final_price.d,'3')
 assert.equal(ctx.cartAmounts().line(rows[1],{convert:false}).total.decimal(),'1')
})
test('stock caps retain fractional availability and enforce actual stock above the old sentinel',()=>{
 const {ctx,stored}=browser()
 const product=item({stock:'0.333333333333'})
 assert.equal(ctx.cartAddOrUpdateItem(product,{quantity:'1'}).quantity,'0.333333333333')
 assert.equal(ctx.cartAddOrUpdateItem(item({id:'8',stock:'10000.25'}),{quantity:'20000'}).quantity,'10000.25')
 assert.equal(ctx.cartAddOrUpdateItem(item({id:'9',stock:undefined}),{quantity:'20000'}).quantity,'20000')
 const before=stored.get('products_cart')
 assert.throws(()=>ctx.cartAddOrUpdateItem(item({id:'10',stock:'0'}),{quantity:'1'}),/agotado/)
 assert.throws(()=>ctx.cartAddOrUpdateItem(item({id:'11'}),{quantity:'0.0000000000001'}))
 assert.equal(stored.get('products_cart'),before)
})
test('social proof anchor and rounded DOM labels never discount the catalog a second time',()=>{
 const {ctx,window}=browser()
 window.__active_campaign={sp_discount_price:true,discount_type:'percentage',discount_value:'10'}
 const product=item({sale_unit_price:'100',original_price:'100',compare_at_price:'110',campaign_pricing_snapshot:null})
 const prepared=ctx.cartPrepareItemForStorage(product)
 assert.equal(prepared.sale_unit_price,'100')
 assert.equal(prepared.original_price,'100')
 assert.equal(prepared.compare_at_price,'110')
 assert.equal(ctx.cartEnrichFromProductCard({closest(){assert.fail('A rounded label cannot become a price')}},product),product)
})
test('modal money display rounds the source fraction directly instead of its twelve place projection',()=>{
 const {ctx,nodes}=browser()
 nodes.set('cart-confirm-price-current',{innerHTML:''})
 nodes.set('cart-confirm-price-old',{innerHTML:'',style:{}})
 ctx.cartConfirmRenderPrices(item({sale_unit_price:'1.005',campaign_pricing_snapshot:{values:{final_price:{n:'10049999999999999',d:'10000000000000000'}}}}))
 assert.match(nodes.get('cart-confirm-price-current').innerHTML,/Bs\. 1\.00/)
 assert.doesNotMatch(nodes.get('cart-confirm-price-current').innerHTML,/1\.01/)
})
test('header totals add original fractions within each currency and do not add VES to USD',()=>{
 const {math}=browser()
 assert.equal(math.total([item({sale_unit_price:'0.005',campaign_pricing_snapshot:null,quantity:'1'}),
  item({sale_unit_price:'0.005',campaign_pricing_snapshot:null,quantity:'1'}),item({currency_type_id:'USD',quantity:'3'})]),'Bs. 0.01 + USD 1.00')
 assert.equal(math.stock({warehouses:[{stock:'0.1'},{stock:'0.2'}]}),'0.3')
 assert.throws(()=>math.stock({warehouses:[{}]}))
})
test('modal quantity controls preserve fractions and zero removes the exact stored item',()=>{
 const {ctx,nodes,stored}=browser()
 ctx.cartAddOrUpdateItem(item(),{quantity:'0.333333333333'})
 const input={value:'0.333333333333',setCustomValidity(){},reportValidity(){assert.fail('valid quantity')}}
 const minus={disabled:false},plus={disabled:false}
 nodes.set('cart-confirm-qty-input',input);nodes.set('cart-confirm-qty-minus',minus);nodes.set('cart-confirm-qty-plus',plus)
 ctx._cartConfirmState.itemId='7';ctx._cartConfirmState.stock='20'
 ctx.cartConfirmSyncQuantityToCart()
 assert.equal(JSON.parse(stored.get('products_cart'))[0].quantity,'0.333333333333')
 assert.equal(minus.disabled,false)
 input.value='0';ctx.cartConfirmSyncQuantityToCart()
 assert.equal(JSON.parse(stored.get('products_cart')).length,0)
})
test('quick view multiplication uses the structured price and a fractional quantity',()=>{
 const {ctx}=browser(),input={value:'0.333333333333',setCustomValidity(){}},label={textContent:''}
 const scope={dataset:{qvProduct:JSON.stringify(item({sale_unit_price:'3',campaign_pricing_snapshot:null})),symbol:'Bs.',unitPrice:'999'},
  querySelector:selector=>selector==='.qv-quantity' ? input : label}
 ctx.qvUpdatePrice(scope)
 assert.equal(input.value,'0.333333333333')
 assert.equal(label.textContent,'Agregar a Carrito · Bs. 1.00')
})
test('quote-only rows carry unavailable prices without inventing a free sale or displaying totals',()=>{
 const {ctx,window,nodes}=browser()
 window.__storefront_show_prices=false
 const row=ctx.cartPrepareItemForStorage(item({sale_unit_price:null,campaign_pricing_snapshot:null}))
 assert.equal(row.sale_unit_price,null)
 assert.equal(row.original_price,null)
 let text
 ctx.jQuery=()=>({text:value=>{text=value}})
 ctx.calculateTotalCart()
 assert.equal(text,'')
 nodes.set('cart-confirm-price-current',{innerHTML:'old'})
 nodes.set('cart-confirm-price-old',{innerHTML:'old',style:{}})
 ctx.cartConfirmRenderPrices(row)
 assert.equal(nodes.get('cart-confirm-price-current').innerHTML,'')
 window.__storefront_show_prices=true
 assert.throws(()=>ctx.cartPrepareItemForStorage(row),/precio no está disponible/)
})
