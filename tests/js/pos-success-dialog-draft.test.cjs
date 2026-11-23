const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const vue = require('vue-template-compiler')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default

const component = vue.parseComponent(fs.readFileSync('resources/js/views/tenant/pos/partials/options.vue', 'utf8'))
const ast = parser.parse(component.script.content, {sourceType:'module'})
const methods = ast.program.body.find(n => n.type === 'ExportDefaultDeclaration').declaration.properties.find(n => n.key?.name === 'methods').value
function method(name) {
    return vm.runInNewContext(`({${generate(methods.properties.find(n => n.key?.name === name)).code}})[${JSON.stringify(name)}]`)
}
for (const trigger of ['button', 'tab']) test(`cerrar comprobante con ${trigger} conserva la venta siguiente`, async () => {
    const draft = {customer_id: 7, items:[{quantity:'2', price:'10'}]}, before = JSON.stringify(draft), events = []
    const ctx = {initForm:async()=>{}, $emit:(...args)=>events.push(args),
        $eventHub:{$emit:()=>assert.fail('No debe cancelar la venta nueva')}}
    ctx.clickNewSale = () => method('clickNewSale').call(ctx)
    if (trigger === 'tab') method('keyupCustomerEmail').call(ctx, {keyCode:9})
    else await ctx.clickNewSale()
    await new Promise(resolve => setImmediate(resolve))
    assert.equal(JSON.stringify(draft), before)
    assert.equal(events.length, 1); assert.equal(events[0][0], 'update:showDialog'); assert.equal(events[0][1], false)
})
test('otra tecla no cierra el diálogo', () => {
    let closed = 0
    method('keyupCustomerEmail').call({clickNewSale(){closed++}}, {keyCode:13})
    assert.equal(closed, 0)
})
