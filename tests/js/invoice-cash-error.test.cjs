const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs'), vm = require('node:vm')
const compiler = require('vue-template-compiler'), parser = require('@babel/parser')
const traverse = require('@babel/traverse').default, generate = require('@babel/generator').default

test('the invoice form displays the cash-payment error for flat and wrapped responses', () => {
    const component=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/invoice_generate.vue','utf8'))
    const tree=parser.parse(component.script.content,{sourceType:'module'})
    const properties=tree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties
    const methods=properties.find(node=>node.key?.name==='methods').value.properties
    const first=vm.runInNewContext(`({${generate(methods.find(node=>node.key?.name==='firstValidationError')).code}}).firstValidationError`)
    let handler
    traverse(tree,{CallExpression(path){
        const node=path.node
        if (node.callee?.property?.name==='catch' && node.arguments[0] && generate(node.arguments[0]).code.includes('firstLotError')) handler=node.arguments[0]
    }})
    assert.ok(handler)
    const callback=vm.runInNewContext(`(function(error) ${generate(handler.body).code})`)
    const message='Una factura al contado requiere cobrar el total.'
    for (const data of [{payments:[message]}, {errors:{payments:[message]}}, {message:{payments:[message]}}]) {
        const messages=[]
        const state={firstValidationError:first, $message:{error:message=>messages.push(message)}}
        callback.call(state,{response:{status:422,data}})
        assert.equal(messages[0],message)
        assert.equal(state.errors.payments[0],message)
    }
    assert.deepEqual(compiler.compile(component.template.content).errors,[])
})
