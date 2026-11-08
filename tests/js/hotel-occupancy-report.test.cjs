const {test}=require('node:test')
const assert=require('node:assert/strict')
const fs=require('node:fs'),vm=require('node:vm')
const compiler=require('vue-template-compiler'),parser=require('@babel/parser')
const generate=require('@babel/generator').default,t=require('@babel/types')
function method(path,name){
 const component=compiler.parseComponent(fs.readFileSync(path,'utf8'))
 assert.deepEqual(compiler.compile(component.template.content).errors,[])
 const ast=parser.parse(component.script.content,{sourceType:'module'})
 const properties=ast.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties.find(node=>node.key?.name==='methods').value.properties
 const entry=properties.find(node=>node.key?.name===name)
 return vm.runInNewContext(`(${generate(t.functionExpression(null,entry.params,entry.body)).code})`)
}
test('hotel pending products are identified without inventing a monetary balance',()=>{
 const pending=method('modules/Report/Resources/assets/js/views/report_hotels/index.vue','checkProductDebts')
 assert.equal(pending([{payment_status:'PAID'},{payment_status:'PENDING',item:{total:'100',currency_type_id:'USD'}}]),'Hay productos pendientes')
 assert.equal(pending([{payment_status:'PAID'}]),'')
})
test('hotel product detail labels the recorded currency and missing currency',()=>{
 const total=method('modules/Report/Resources/assets/js/views/report_hotels/modal_item_info.vue','getTotal')
 assert.equal(total({item:{total:'0.333333333333',currency_type_id:'USD'}}),'USD 0.333333333333')
 assert.equal(total({item:{total:'10'}}),'Moneda no identificada 10')
})
