const {test}=require('node:test')
const assert=require('node:assert/strict')
const fs=require('node:fs')
const vm=require('node:vm')
const compiler=require('vue-template-compiler')
const parser=require('@babel/parser')
const generate=require('@babel/generator').default
const files=[
 'resources/js/views/tenant/documents/invoice.vue',
 'resources/js/views/tenant/documents/invoiceupdate.vue',
 'resources/js/views/tenant/documents/invoice_generate.vue',
 'resources/js/views/tenant/quotations/form.vue',
 'resources/js/views/tenant/quotations/form_edit.vue',
 'resources/js/views/tenant/quotations/partials/options.vue',
 'resources/js/views/tenant/sale_notes/form.vue',
 'modules/Sale/Resources/assets/js/views/technical-services/form.vue',
]
for(const file of files) test(`${file} requires a destination by classification, including credit documents with real payments`,()=>{
 const source=compiler.parseComponent(fs.readFileSync(file,'utf8'))
 const tree=parser.parse(source.script.content,{sourceType:'module'})
 const options=tree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration
 const methods=options.properties.find(node=>node.key.name==='methods').value
 const method=methods.properties.find(node=>node.key?.name==='validatePaymentDestination')
 const validate=vm.runInNewContext(`({${generate(method).code}}).validatePaymentDestination`,{isRecordedReceipt:require('./support/exact-runtime.cjs')('payment-classification').isRecordedReceipt})
 const rows=[{payment_method_type_id:'09',payment_destination_id:null}, {payment_method_type_id:'05',payment_destination_id:null}]
 const methodsList=[{id:'09',collection_kind:'account',is_credit:false},{id:'05',collection_kind:'credit',is_credit:true}]
 const ctx={form:{payments:rows},document:{payments:rows},payment_method_types:methodsList,payment_condition:'02',isCreditPaymentCondition:true}
 assert.equal(validate.call(ctx).error_by_item,1)
 rows[0].payment_destination_id=2
 assert.equal(validate.call(ctx).error_by_item,0)
 rows[0].payment_destination_id=null; rows[0].id=7
 assert.equal(validate.call(ctx).error_by_item,0)
 assert.deepEqual(compiler.compile(source.template.content).errors,[])
})
