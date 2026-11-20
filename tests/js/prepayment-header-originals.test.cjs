const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),compiler=require('vue-template-compiler'),parser=require('@babel/parser'),generate=require('@babel/generator').default
const root=path.resolve(__dirname,'../..'),load=require(path.join(root,'tests/js/helpers/load-module.cjs'))
const math=load(path.join(root,'resources/js/helpers/exchange-rate-math.js')),commercial=load(path.join(root,'resources/js/helpers/exact-commercial.js')),document=load(path.join(root,'resources/js/helpers/exact-document.js'))
const files=['resources/js/views/tenant/documents/invoice.vue','resources/js/views/tenant/documents/invoiceupdate.vue','resources/js/views/tenant/documents/invoice_generate.vue']
const scope={...math,...commercial,...document,moment:require('moment'),_:require('lodash')}

test('invoice forms display the advance rather than the discount in the advances row', () => {
    const Vue = require('vue')
    const renderText = node => [node.text, ...(node.children || []).map(renderText)].filter(Boolean).join(' ')
    for (const file of files) {
        const template = compiler.parseComponent(fs.readFileSync(path.join(root, file), 'utf8')).template.content
        const row = [...template.matchAll(/<tr\b[^>]*>[\s\S]*?<\/tr>/g)]
            .map(match => match[0])
            .find(candidate => candidate.includes('ANTICIPOS:'))
        assert.ok(row, file)
        const component = new Vue({
            ...compiler.compileToFunctions(`<table><tbody>${row}</tbody></table>`),
            computed: file.endsWith('invoice_generate.vue')
                ? {hasPositiveInvoicePrepayment: method(file, 'hasPositiveInvoicePrepayment', 'computed')}
                : {},
            data: () => ({
                form: {total_prepayment: '20.000000000001', total_discount: '3.75'},
                currency_type: {symbol: 'Bs.'},
                documentCurrencySymbol: 'Bs.',
            }),
        })
        try {
            const rendered = renderText(component._render()).replace(/\s+/g, ' ')
            assert.match(rendered, /ANTICIPOS:\s+Bs\.\s+20\.000000000001/, file)
            assert.doesNotMatch(rendered, /3\.75/, file)
            component.form.total_prepayment = '0'
            assert.doesNotMatch(renderText(component._render()), /ANTICIPOS/, file)
        } finally {
            component.$destroy()
        }
    }
})

function methods(file,group){const s=compiler.parseComponent(fs.readFileSync(path.join(root,file),'utf8'));return parser.parse(s.script.content,{sourceType:'module'}).program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration.properties.find(n=>n.key?.name===group).value.properties}
function method(file,name,group='methods'){const n=methods(file,group).find(n=>n.key?.name===name);assert.ok(n,name);return vm.runInNewContext('({'+generate(n).code+'})['+JSON.stringify(name)+']',scope)}
function line(total){const values={quantity:math.exactAmount(1),unit_price:math.exactAmount(total),total:math.exactAmount(total),total_value:math.exactAmount(total),total_base_igv:math.exactAmount(total),total_igv:math.exactAmount(0),total_discount:math.exactAmount(0),total_charge:math.exactAmount(0),total_taxes:math.exactAmount(0),total_other_taxes:math.exactAmount(0)};return {...Object.fromEntries(Object.entries(values).map(([k,v])=>[k,v.decimal()])),affectation_igv_type_id:'20',discounts:[],calculation_snapshot:math.exactSnapshot(values)}}
function context(file){const ctx={form:{items:[],discounts:[],charges:[],prepayments:[],total_prepayment:'0'},config:{},currency_types:[{id:'VES'}],$store:{commit(){}},onPrepareAdditionalInformation:v=>v,onPrepareItems:items=>JSON.parse(JSON.stringify(items)),onSetSeries:()=>[],onSetSeriesId:()=>1,reloadDataCustomers:async()=>{},changeDateOfIssue(){},updateChangeDestinationSale(){},prepareDataCustomer(){},clickAddInitGuides(){},setTotalDefaultPayment(){},setPendingAmount(){},calculateFee(){},setTotalPointsBySale(){},enabled_discount_global:false,prepayment_deduction:false,isExportInvoice:false};ctx.calculateTotal=method(file,'calculateTotal').bind(ctx);if(file.endsWith('invoice_generate.vue'))for(const name of ['chargeGlobal','invoiceGlobalChargeExact','setInvoiceGlobalCharge'])ctx[name]=method(file,name).bind(ctx);return ctx}
for(const file of files.slice(0,2))test(file+': real saved-data loader retains a large textual advance before recalculating',async()=>{
 const amount='9007199254740993.01',ctx=context(file),input={document_type_id:'01',currency_type_id:'VES',date_of_issue:'2026-11-12',invoice:{operation_type_id:'0101'},fee:[],guides:[],payments:[{id:9,payment:'0.01'}],items:[line('9007199254741000')],discounts:[],charges:[],total_prepayment:amount,prepayments:[{amount,total:amount,calculation_snapshot:math.exactSnapshot({amount,total:amount})}]},before=JSON.stringify(input)
 await method(file,'onSetFormData').call(ctx,input)
 assert.equal(ctx.form.total,'6.99');assert.equal(ctx.form.total_prepayment,amount);assert.equal(JSON.stringify(input),before);assert.equal(JSON.stringify(ctx.form.payments),JSON.stringify(input.payments))
})
for(const file of files)test(file+': real calculator retains the original advance without a header discount',()=>{
 const ctx=context(file);ctx.form.items=[line('1')];ctx.form.total_prepayment='0.333333333333';ctx.form.prepayments=[{amount:'0.333333333333',total:'0.333333333333',calculation_snapshot:math.exactSnapshot({amount:{n:'1',d:'3'},total:{n:'1',d:'3'}})}]
 ctx.calculateTotal()
 assert.equal(ctx.form.total,'0.666666666667');assert.equal(JSON.stringify(ctx.form.calculation_snapshot.values.total),JSON.stringify({n:'2',d:'3'}))
})

for(const file of files.slice(0,2))test(file+': saved nullable advance is loaded as zero without inventing NaN',async()=>{
 const ctx=context(file),data={document_type_id:'01',currency_type_id:'VES',date_of_issue:'2026-11-12',invoice:{operation_type_id:'0101'},fee:[],guides:[],items:[line('1')],discounts:[],charges:[],total_prepayment:null,payments:[]}
 await method(file,'onSetFormData').call(ctx,data);assert.equal(ctx.form.total,'1');assert.equal(ctx.form.total_prepayment,'0')
})
test('the original four-argument contract keeps decimal-only advances and existing scalar output fields',()=>{
 const result=document.documentTotals([line('1')],[],[],'0.1')
 assert.equal(result.total,'0.9');assert.equal(JSON.stringify(result.calculation_snapshot.values.total),JSON.stringify({n:'9',d:'10'}));assert.equal('total_prepayment' in result,false)
 const none=document.documentTotals([line('1')]);assert.equal(none.total,'1');assert.equal(JSON.stringify(none.calculation_snapshot.values.total_prepayment),JSON.stringify({n:'0',d:'1'}))
})
function reference(value){return {amount:math.exactAmount(value).decimal(),total:math.exactAmount(value).decimal(),calculation_snapshot:math.exactSnapshot({amount:value,total:value})}}
test('object-serialized advance references sum their originals and leave request data intact',()=>{
 const refs={2:reference({n:'1',d:'3'}),7:reference({n:'1',d:'7'})},before=JSON.stringify(refs),result=document.documentTotals([line('1')],[],[],'0.47619047619',refs)
 assert.equal(JSON.stringify(result.calculation_snapshot.values.total),JSON.stringify({n:'11',d:'21'}));assert.equal(JSON.stringify(result.calculation_snapshot.values.total_prepayment),JSON.stringify({n:'10',d:'21'}));assert.equal(JSON.stringify(refs),before)
})
test('repeated header calculation never uses its previous rounded remainder',()=>{
 const ctx=context(files[0]);ctx.form.items=[line('1')];ctx.form.total_prepayment='0.333333333333';ctx.form.prepayments=[reference({n:'1',d:'3'})]
 for(let i=0;i<3;i++){ctx.calculateTotal();assert.equal(JSON.stringify(ctx.form.calculation_snapshot.values.total),JSON.stringify({n:'2',d:'3'}));assert.equal(JSON.stringify(ctx.form.calculation_snapshot.values.total_prepayment),JSON.stringify({n:'1',d:'3'}))}
})
for(const type of ['04','05'])test('advance discount '+type+' prevents a second subtraction while retaining its original header value',()=>{
 const row=reference({n:'1',d:'3'}),result=document.documentTotals([line('1')],[{discount_type_id:type,amount:row.amount,amount_exact:{n:'1',d:'3'}}],[],'0.333333333333',[row])
 assert.equal(JSON.stringify(result.calculation_snapshot.values.total),JSON.stringify({n:'2',d:'3'}));assert.equal(JSON.stringify(result.calculation_snapshot.values.total_prepayment),JSON.stringify({n:'1',d:'3'}))
})
for(const [name,change] of [
 ['missing total',row=>{delete row.total}],['invalid version',row=>{row.calculation_snapshot.version=0}],
 ['missing original',row=>{delete row.calculation_snapshot.values.total}],['negative original',row=>{row.total='-0.333333333333';row.calculation_snapshot.values.total={n:'-1',d:'3'}}],
 ['stale original',row=>{row.total='0.1'}],
])test('an advance with '+name+' is rejected without changing its reference',()=>{
 const row=reference({n:'1',d:'3'});change(row);const before=JSON.stringify(row)
 assert.throws(()=>document.documentTotals([line('1')],[],[],'0.333333333333',[row]),/anticipo/);assert.equal(JSON.stringify(row),before)
})
