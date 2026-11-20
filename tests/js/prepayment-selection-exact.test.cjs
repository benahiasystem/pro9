const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),compiler=require('vue-template-compiler'),parser=require('@babel/parser'),generate=require('@babel/generator').default
const root=path.resolve(__dirname,'../..'),load=require(path.join(root,'tests/js/helpers/load-module.cjs')),math=load(path.join(root,'resources/js/helpers/exchange-rate-math.js'))
const future=path.join(root,'resources/js/helpers/exact-prepayments.js'),helpers=fs.existsSync(future)?load(future):{},scope={...math,...helpers,...load(path.join(root,'resources/js/helpers/technical-service-totals.js')),_:require('lodash')}
const files=['resources/js/views/tenant/documents/invoice_generate.vue','resources/js/views/tenant/documents/invoice.vue','resources/js/views/tenant/documents/invoiceupdate.vue','modules/Sale/Resources/assets/js/views/technical-services/form.vue']
const fraction={n:'1',d:'3'},net={n:'25',d:'87'}
function reference(taxed=false){return {id:7,description:'FF01-7',document_type_id:'02',amount:taxed?'0.287356321839':'0.333333333333',total:'0.333333333333',calculation_snapshot:math.exactSnapshot({amount:taxed?net:fraction,total:fraction})}}
function context(selected=reference()){
 const errors=[],ctx={prepayment_documents:[selected],form:{affectation_type_prepayment:20,prepayments:[{document_id:7,amount:'0',total:'0'}],discounts:[],items:[],total_prepayment:'0'},percentage_igv:'0.16',errors,recalculations:0,$set(array,index,row){array.splice(index,1,row)},$message:{error(message){errors.push(message)}},getPrepayment(){return selected},changeTotalPrepayment(){this.recalculations++}}
 return ctx
}
for(const file of files){
 const component=compiler.parseComponent(fs.readFileSync(path.join(root,file),'utf8')),tree=parser.parse(component.script.content,{sourceType:'module'}),methods=tree.program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration.properties.find(n=>n.key.name==='methods').value
 const run=name=>{const method=methods.properties.find(n=>n.key?.name===name);assert.ok(method,name);return vm.runInNewContext('({'+generate(method).code+'})['+JSON.stringify(name)+']',scope)}
 test(file+': selection retains both original fractions without sharing the response snapshot',async()=>{
  const selected=reference(),before=JSON.stringify(selected),ctx=context(selected);await run('changeDocumentPrepayment').call(ctx,0);const row=ctx.form.prepayments[0]
  assert.equal(row.number,'FF01-7');assert.equal(row.document_type_id,'02');assert.equal(row.amount,'0.333333333333');assert.equal(row.total,'0.333333333333');assert.equal(JSON.stringify(row.calculation_snapshot.values.amount),JSON.stringify(fraction));assert.equal(JSON.stringify(row.calculation_snapshot.values.total),JSON.stringify(fraction));assert.notEqual(row.calculation_snapshot,selected.calculation_snapshot);assert.equal(JSON.stringify(selected),before);assert.equal(ctx.recalculations,1)
 })
 test(file+': unchanged taxed selection retains its exact gross rather than multiplying rounded net',()=>{
  const selected=reference(true),ctx=context(selected);ctx.form.affectation_type_prepayment=10;ctx.form.prepayments[0]={document_id:7,amount:selected.amount,total:selected.total,calculation_snapshot:JSON.parse(JSON.stringify(selected.calculation_snapshot))};run('inputAmountPrepayment').call(ctx,0);const row=ctx.form.prepayments[0]
  assert.equal(JSON.stringify(row.calculation_snapshot.values.amount),JSON.stringify(net));assert.equal(JSON.stringify(row.calculation_snapshot.values.total),JSON.stringify(fraction));assert.equal(ctx.recalculations,1)
 })
 if (file.includes('/tenant/documents/')) {
  test(file+': an explicit equivalent decimal replaces the selected advance fraction', () => {
   const selected = reference()
   const ctx = context(selected)
   const entered = '0.3333333333330'
   ctx.form.prepayments[0] = {...selected, document_id: selected.id, amount: entered}
   run('inputAmountPrepayment').call(ctx, 0, entered)
   const row = ctx.form.prepayments[0]
   const typed = {n: '333333333333', d: '1000000000000'}
   assert.equal(row.total, '0.333333333333')
   assert.deepEqual(JSON.parse(JSON.stringify(row.calculation_snapshot.values)), {amount: typed, total: typed})
   assert.deepEqual(ctx.errors, [])
   assert.equal(ctx.recalculations, 1)
   assert.match(compiler.compile(component.template.content).render, /inputAmountPrepayment\(\s*index,\s*\$event\s*\)/)
  })
 }
 test(file+': editing the net replaces only its stale fraction and computes exact tax',()=>{
  const selected=reference(true),ctx=context(selected);ctx.form.affectation_type_prepayment=10;ctx.form.prepayments[0]={document_id:7,amount:'0.1',total:selected.total,calculation_snapshot:JSON.parse(JSON.stringify(selected.calculation_snapshot))};run('inputAmountPrepayment').call(ctx,0);const row=ctx.form.prepayments[0]
  assert.equal(row.amount,'0.1');assert.equal(row.total,'0.116');assert.equal(JSON.stringify(row.calculation_snapshot.values.amount),JSON.stringify({n:'1',d:'10'}));assert.equal(JSON.stringify(row.calculation_snapshot.values.total),JSON.stringify({n:'29',d:'250'}));assert.equal(ctx.errors.length,0);assert.equal(ctx.recalculations,1)
 })
 test(file+': an excess is clamped to the original available fraction',()=>{
  const ctx=context();ctx.form.prepayments[0].amount='1';run('inputAmountPrepayment').call(ctx,0);const row=ctx.form.prepayments[0]
  assert.equal(row.amount,'0.333333333333');assert.equal(JSON.stringify(row.calculation_snapshot.values.amount),JSON.stringify(fraction));assert.equal(JSON.stringify(row.calculation_snapshot.values.total),JSON.stringify(fraction));assert.equal(ctx.errors.length,1);assert.equal(ctx.recalculations,1)
 })
 test(file+': invalid negative input leaves the last calculable totals intact',()=>{
  const ctx=context();ctx.form.prepayments[0]={document_id:7,amount:'-0.1',total:'0.333333333333',calculation_snapshot:math.exactSnapshot({amount:fraction,total:fraction})};run('inputAmountPrepayment').call(ctx,0)
  assert.equal(ctx.form.prepayments[0].total,'0.333333333333');assert.equal(JSON.stringify(ctx.form.prepayments[0].calculation_snapshot.values.total),JSON.stringify(fraction));assert.equal(ctx.errors.length,1);assert.equal(ctx.recalculations,0)
 })
 test(file+': repeated prepayment discount calculation preserves fractions and other adjustments',()=>{
  const ctx=context();ctx.form.prepayments=[{document_id:7,...reference()}];ctx.form.items=[{affectation_igv_type_id:'20',total_base_igv:'1',calculation_snapshot:math.exactSnapshot({total_base_igv:'1'})}];ctx.form.total_exonerated='1';ctx.form.total_taxed='0';ctx.form.discounts=[{discount_type_id:'02',amount:'0.1',amount_exact:{n:'1',d:'10'}}]
  const calculate=run('discountGlobalPrepayment');calculate.call(ctx);const once=JSON.stringify(ctx.form.discounts);calculate.call(ctx);assert.equal(JSON.stringify(ctx.form.discounts),once);assert.equal(ctx.form.total_prepayment,'0.333333333333');assert.equal(ctx.form.discounts.length,2);assert.equal(ctx.form.discounts[0].discount_type_id,'02');assert.equal(JSON.stringify(ctx.form.discounts[1].amount_exact),JSON.stringify(fraction));assert.deepEqual(compiler.compile(component.template.content).errors,[])
 })
}

const serviceSource=compiler.parseComponent(fs.readFileSync(path.join(root,'modules/Sale/Resources/assets/js/views/technical-services/form.vue'),'utf8'))
const serviceMethods=parser.parse(serviceSource.script.content,{sourceType:'module'}).program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration.properties.find(n=>n.key.name==='methods').value
const serviceScope={...scope,...load(path.join(root,'resources/js/helpers/functions.js'))}
const serviceMethod=name=>vm.runInNewContext('({'+generate(serviceMethods.properties.find(n=>n.key?.name===name)).code+'})['+JSON.stringify(name)+']',serviceScope)
for(const taxed of [false,true])test('actual service calculation retains the advance fraction with '+(taxed?'standalone service cost':'exempt items'),()=>{
 const selected=reference(taxed),line=serviceScope.calculateRowItem({item:{id:1,unit_price:'1',currency_type_id:'VES'},quantity:'1',unit_price:'1',affectation_igv_type_id:'20',discounts:[],charges:[]},'VES','40','0.16')
 const ctx={form:{cost:taxed?'1':'0',items:taxed?[]:[line],discounts:[],charges:[],prepayments:[selected],total_prepayment:'0',affectation_type_prepayment:taxed?10:20},config:{active_allowance_charge:false},percentage_igv:'0.16',enabled_discount_global:false,prepayment_deduction:true,setTotalDefaultPayment(){},setPendingAmount(){},calculateFee(){}}
 ctx.updateCost=serviceMethod('updateCost').bind(ctx)
 const calculate=serviceMethod('calculateTotal');calculate.call(ctx)
 assert.equal(JSON.stringify(ctx.form.discounts[0].amount_exact),JSON.stringify(taxed?net:fraction));assert.equal(ctx.form.total_prepayment,'0.333333333333')
 const once=JSON.stringify(ctx.form.discounts);calculate.call(ctx);assert.equal(JSON.stringify(ctx.form.discounts),once)
 if(taxed)assert.equal(ctx.form.total_taxed,'0.574712643678');else assert.equal(ctx.form.total_exonerated,'0.666666666667')
})
