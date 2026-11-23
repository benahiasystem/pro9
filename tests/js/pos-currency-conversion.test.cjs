const {test}=require('node:test'),assert=require('node:assert/strict'),path=require('node:path')
const load=require('./helpers/load-module.cjs')
const fs=require('node:fs'),vm=require('node:vm'),compiler=require('vue-template-compiler'),parser=require('@babel/parser'),generate=require('@babel/generator').default
const {calculateRowItem}=load(path.resolve('resources/js/helpers/functions.js'))
const {preparePosCurrencyConversion}=load(path.resolve('resources/js/helpers/pos-currency-conversion.js'))

function draft() {
 const item=calculateRowItem({item:{id:1,unit_price:'100',currency_type_id:'USD',has_igv:false},affectation_igv_type_id:'20',quantity:'1',discounts:[],charges:[]},'USD','3')
 return {currency_type_id:'USD',exchange_rate_sale:'3',items:[item],payments:[{currency_type_id:'USD',original_amount:'1',payment:'1',exchange_rate:'4'}],discounts:[],charges:[],total_prepayment:'0'}
}
test('convierte partida, cobro y cabecera sin alterar los operandos originales',()=>{
 const form=draft(),before=JSON.stringify(form),result=preparePosCurrencyConversion(form,'VES','0.16')
 assert.equal(JSON.stringify(form),before);assert.equal(result.currency_type_id,'VES');assert.equal(result.items[0].total,'300')
 assert.equal(result.payments[0].original_amount,'1');assert.equal(result.payments[0].payment,'4.00');assert.equal(result.payments[0].exchange_rate,'4')
 assert.equal(result.total,'300')
})

for(const name of ['index','fast']) test(`${name}: restaura USD en VES de forma atómica y bloquea si falta moneda`,()=>{
 const source=compiler.parseComponent(fs.readFileSync(`resources/js/views/tenant/pos/${name}.vue`,'utf8'))
 const ast=parser.parse(source.script.content,{sourceType:'module'}),component=ast.program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration
 const methods=component.properties.find(n=>n.key?.name==='methods').value.properties
 const computed=component.properties.find(n=>n.key?.name==='computed').value.properties
 const extracted=['initCurrencyType','changeCurrencyType','isPaymentCurrencyBlocked'].map(key=>generate(methods.find(n=>n.key?.name===key)).code)
 const checks=['posCurrencyUnavailable','posPaymentBlocked'].map(key=>generate(computed.find(n=>n.key?.name===key)).code)
 const fn=vm.runInNewContext(`({${[...extracted,...checks].join(',')}})`,{window:{salesDocumentsVes:true},isValidPosRate:()=>true,ensurePosRate:()=>true,preparePosCurrencyConversion})
 const ctx={...fn,form:draft(),currency_types:[{id:'USD'}],posRateUnavailable:false,draftRestored:true,setFormPosLocalStorage(){this.saved=true},$message:{error(){}}}
 Object.defineProperty(ctx,'posCurrencyUnavailable',{get(){return fn.posCurrencyUnavailable.call(ctx)}})
 Object.defineProperty(ctx,'posPaymentBlocked',{get(){return fn.posPaymentBlocked.call(ctx)}})
 const before=JSON.stringify(ctx.form);ctx.initCurrencyType();assert.equal(JSON.stringify(ctx.form),before);assert.equal(ctx.isPaymentCurrencyBlocked(),true)
 ctx.currency_types.push({id:'VES'});ctx.setFormPosLocalStorage=()=>{throw Error('almacenamiento bloqueado')}
 ctx.initCurrencyType();assert.equal(JSON.stringify(ctx.form),before);assert.equal(ctx.isPaymentCurrencyBlocked(),true)
 ctx.setFormPosLocalStorage=()=>{ctx.saved=true};ctx.initCurrencyType();assert.equal(ctx.form.currency_type_id,'VES');assert.equal(ctx.form.items[0].total,'300')
 assert.equal(ctx.form.payments[0].payment,'4.00');assert.equal(ctx.isPaymentCurrencyBlocked(),false);assert.equal(ctx.saved,true)
})

for(const name of ['index','fast']) test(`${name}: la tasa tardía solo actualiza el borrador solicitado`,async()=>{
 const source=compiler.parseComponent(fs.readFileSync(`resources/js/views/tenant/pos/${name}.vue`,'utf8'))
 const ast=parser.parse(source.script.content,{sourceType:'module'}),component=ast.program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration
 const method=component.properties.find(n=>n.key?.name==='methods').value.properties.find(n=>n.key?.name==='changeExchangeRate')
 let release,calls=0
 const run=vm.runInNewContext(`({${generate(method).code}}).changeExchangeRate`,{refreshPosRate:()=>new Promise(resolve=>{release=resolve}),isValidPosRate:()=>true})
 const ctx={form:{},draftRestored:true,initCurrencyType(){calls++}}
 const pending=run.call(ctx);ctx.form={};release('3');await pending;assert.equal(calls,0)
 const current=run.call(ctx);release('3');await current;assert.equal(calls,1)
 ctx.draftRestored=false;const early=run.call(ctx);release('3');await early;assert.equal(calls,1)
})

test('el panel rápido espera restauración y moneda válida antes del primer montaje',()=>{
 const source=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/pos/fast.vue','utf8'))
 const ast=parser.parse(source.script.content,{sourceType:'module'}),component=ast.program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration
 const watch=component.properties.find(n=>n.key?.name==='watch').value.properties.find(n=>n.key?.name==='posPaymentBlocked')
 const run=vm.runInNewContext(`({${generate(watch).code}}).posPaymentBlocked`)
 const state={draftRestored:false,paymentPanelActivated:false}
 run.call(state,false);assert.equal(state.paymentPanelActivated,false)
 state.draftRestored=true;run.call(state,true);assert.equal(state.paymentPanelActivated,false)
 run.call(state,false);assert.equal(state.paymentPanelActivated,true)
 run.call(state,true);assert.equal(state.paymentPanelActivated,true)
 assert.match(source.template.content,/v-if="draftRestored && paymentPanelActivated"/)
 assert.match(source.template.content,/v-show="!posPaymentBlocked"/)
})
test('rechaza ajustes, cobros registrados y moneda incierta sin modificar el borrador',()=>{
 for(const change of [f=>{f.discounts=[{amount:'1'}]},f=>{f.items[0].discounts=[{is_amount:true,amount:'1'}]},f=>{f.payments[0].id=4},f=>{f.total_prepayment='0.001'},f=>{f.items[0].price_input={amount:'1',currency_type_id:'EUR'}},
  f=>{f.payments[0].calculation_snapshot={version:1,values:{payment:{n:'1',d:'1'}}}},
  f=>{f.calculation_snapshot={version:1,values:{total_prepayment:{n:'1',d:'30000000000000'}}}},
  f=>{f.calculation_snapshot={version:1,values:{}}}]){
  const form=draft();change(form);const before=JSON.stringify(form)
  assert.throws(()=>preparePosCurrencyConversion(form,'VES','0.16'));assert.equal(JSON.stringify(form),before)
 }
})
test('recalcular exige un cobro cruzado verificable a su tasa de recepción',()=>{
 const form=draft();form.currency_type_id='VES';form.items=[calculateRowItem({item:{id:1,unit_price:'100',currency_type_id:'USD',has_igv:false},affectation_igv_type_id:'20',quantity:'1'},'VES','3')]
 form.payments=[{currency_type_id:'USD',original_amount:'1',payment:'4.00',exchange_rate:'4'}]
 assert.equal(preparePosCurrencyConversion(form,'VES','0.16',true).payments[0].payment,'4.00')
 for(const change of [r=>{delete r.exchange_rate},r=>{delete r.original_amount},r=>{r.payment='3'},r=>{r.calculation_snapshot={version:1,values:{payment:{n:'3',d:'1'}}}},r=>{r.calculation_snapshot={version:1,values:{payment:{n:'3999',d:'1000'}}}}]){
  const copy=JSON.parse(JSON.stringify(form));change(copy.payments[0]);const before=JSON.stringify(copy)
  assert.throws(()=>preparePosCurrencyConversion(copy,'VES','0.16',true));assert.equal(JSON.stringify(copy),before)
 }
})
test('un pago aplicado en VES no se interpreta como monto recibido en USD',()=>{
 const form=draft();form.currency_type_id='VES';form.exchange_rate_sale='30'
 form.items=[calculateRowItem({item:{id:1,unit_price:'1',currency_type_id:'USD',has_igv:false},affectation_igv_type_id:'20',quantity:'1'},'VES','30')]
 for(const receipt of [{currency_type_id:'USD',payment:'30'}, {currency_type_id:'USD',payment:'30',original_amount:'1'}, {currency_type_id:'USD',payment:'29',original_amount:'1',exchange_rate:'30'}]){
  form.payments=[receipt];const before=JSON.stringify(form)
  assert.throws(()=>preparePosCurrencyConversion(form,'USD','0.16'));assert.equal(JSON.stringify(form),before)
 }
 form.payments=[{currency_type_id:'USD',payment:'30',original_amount:'1',exchange_rate:'30'}]
 assert.equal(preparePosCurrencyConversion(form,'USD','0.16').payments[0].payment,'1.00')
})
test('no convierte un importe recibido mayor que el aplicado como si fuese el mismo cobro',()=>{
 for(const extra of [{original_amount:'10'}, {tendered_amount:'10'}]){
  const form=draft();form.payments=[{currency_type_id:'USD',payment:'1',exchange_rate:'30',...extra}]
  const before=JSON.stringify(form);assert.throws(()=>preparePosCurrencyConversion(form,'VES','0.16'));assert.equal(JSON.stringify(form),before)
 }
})
