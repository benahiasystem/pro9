const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),compiler=require('vue-template-compiler'),parser=require('@babel/parser'),generate=require('@babel/generator').default
const root=path.resolve(__dirname,'../..'),load=require(path.join(root,'tests/js/helpers/load-module.cjs'))
const math=load(path.join(root,'resources/js/helpers/exchange-rate-math.js')),format=load(path.join(root,'resources/js/helpers/format-money-exact.js'))
let amounts={};const helper=path.join(root,'resources/js/helpers/payment-link-amounts.js');if(fs.existsSync(helper))amounts=load(helper)
const form='modules/Payment/Resources/assets/js/views/payment_links/form.vue',publicForm='modules/Payment/Resources/assets/js/views/payment_links/public/index.vue'
function method(file,name,group='methods'){
 const script=compiler.parseComponent(fs.readFileSync(path.join(root,file),'utf8')).script.content
 const nodes=parser.parse(script,{sourceType:'module'}).program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration.properties
 const node=nodes.find(n=>n.key?.name===group).value.properties.find(n=>n.key?.name===name);assert.ok(node,name)
 return vm.runInNewContext('({'+generate(node).code+'})['+JSON.stringify(name)+']',{...math,...format,...amounts,_:require('lodash')})
}
function formContext(rows){const ctx={form:{total:'0',documents:rows,with_document:true},errors:{},$message:{error(){}},$set:(o,k,v)=>o[k]=v};ctx.calculateTotal=method(form,'calculateTotal').bind(ctx);return ctx}
test('actual form sums every stored settlement cent above the float exact range',()=>{
 const rows=[{payment:'9007199254740993.01',pending:'9007199254740993.01'},{payment:'0.02',pending:'0.02'}],before=JSON.stringify(rows),ctx=formContext(rows)
 ctx.calculateTotal();assert.equal(ctx.form.total,'9007199254740993.03');assert.equal(JSON.stringify(rows),before)
})
test('actual form totals the same per-document cent settlements that the server stores',()=>{
 const ctx=formContext([{payment:'0.005',pending:'1'},{payment:'0.005',pending:'1'}]);ctx.calculateTotal();assert.equal(ctx.form.total,'0.02')
})
test('actual total getter presents a large text amount without converting to Number',()=>{
 assert.equal(method(form,'formattedTotal','computed').call({form:{total:'9007199254740993.03'}}),'9,007,199,254,740,993.03')
})
test('editing a negative payment never changes its sign into a positive charge',()=>{
 const row={payment:'-1',pending:'100'},ctx=formContext([row]);method(form,'changeDocumentPayment').call(ctx,row);assert.equal(row.payment,'-1')
})
test('invalid payment text is blocked before the actual submit method sends HTTP',()=>{
 let posts=0,errors=0;const ctx=formContext([{payment:'1.2.3',pending:'10'}]);ctx.$http={post(){posts++;return Promise.resolve({data:{success:false}})}};ctx.$message.error=()=>errors++
 ctx.$eventHub={$emit(){}};ctx.close=()=>{};method(form,'submit').call(ctx);assert.equal(posts,0);assert.equal(errors,1)
})
test('actual public form converts large safe cents exactly at the SDK boundary',()=>{
 const ctx={total:'90071992547409.87',payment_link:{number_full:'PG-1'},customer:{},description:'Prueba',form:{},amount_error:null}
 method(publicForm,'initForm').call(ctx);assert.equal(ctx.form.amount,9007199254740987);assert.equal(ctx.form.currency,'VES');assert.equal(ctx.amount_error,null)
})
test('actual public form does not send an unsafe SDK integer for a valid large stored total',()=>{
 const ctx={total:'9007199254740993.03',payment_link:{number_full:'PG-1'},customer:{},description:'Prueba',form:{},amount_error:null}
 method(publicForm,'initForm').call(ctx);assert.ok(ctx.amount_error);assert.equal(ctx.form.amount,undefined)
})
test('actual public display keeps large settlement text and two decimal places',()=>{
 assert.equal(method(publicForm,'formatNumber').call({},'9007199254740993.03'),'9007199254740993.03')
})

for(const value of ['-1','0','0.001','NaN','1.2.3',null,true,{n:'1',d:'3'},'1000000000000000000','0.1234567890123'])test('a malformed or nonpositive link amount cannot become a charge: '+JSON.stringify(value),()=>{
 assert.throws(()=>amounts.paymentLinkAmount(value));assert.throws(()=>amounts.paymentLinkMinorUnits(value))
})
test('SDK conversion accepts the largest safe minor-unit integer and rejects its next cent',()=>{
 assert.equal(amounts.paymentLinkMinorUnits('90071992547409.91'),Number.MAX_SAFE_INTEGER)
 assert.throws(()=>amounts.paymentLinkMinorUnits('90071992547409.92'),/límite/)
})
test('actual submit sends exact decimal strings in a copy of the current form',async()=>{
 let payload;const rows=[{document_id:'9007199254740993',payment:'9007199254740993.01',pending:'9007199254740993.03'},{document_id:2,payment:'0.02',pending:'1'}],before=JSON.stringify(rows),ctx=formContext(rows)
 ctx.$http={post(url,data){payload=data;return Promise.resolve({data:{success:false,message:'Doble controlado'}})}};ctx.$eventHub={$emit(){}};ctx.close=()=>{}
 method(form,'submit').call(ctx);await Promise.resolve();assert.equal(payload.total,'9007199254740993.03');assert.equal(payload.documents[0].payment,'9007199254740993.01');assert.equal(payload.documents[0].document_id,'9007199254740993');assert.equal(JSON.stringify(rows),before);assert.notEqual(payload,ctx.form)
})
test('actual submit rejects a one-cent excess beyond the float exact range without HTTP',()=>{
 let posts=0;const ctx=formContext([{payment:'9007199254740993.04',pending:'9007199254740993.03'}]);ctx.$http={post(){posts++}};method(form,'submit').call(ctx);assert.equal(posts,0)
})
