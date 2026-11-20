const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const compiler = require('vue-template-compiler')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default
const {exactAmount} = require('./helpers/load-module.cjs')(require('node:path').resolve('resources/js/helpers/exchange-rate-math.js'))
const {updatePrepaymentInput} = require('./helpers/load-module.cjs')(require('node:path').resolve('resources/js/helpers/exact-prepayments.js'))

test('contingency prepayments preserve decimal precision and enforce the exact available amount', () => {
    const component = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/invoice.vue', 'utf8'))
    const tree = parser.parse(component.script.content, {sourceType:'module'})
    const methods = tree.program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration.properties.find(node => node.key.name === 'methods').value
    const entry = methods.properties.find(node => node.key?.name === 'inputAmountPrepayment')
    const run = vm.runInNewContext(`({${generate(entry).code}}).inputAmountPrepayment`, {exactAmount, updatePrepaymentInput})
    let messages = 0, recalculations = 0
    const context = {form:{prepayments:[{amount:'0.1'}], affectation_type_prepayment:10}, percentage_igv:'0.16',
        getPrepayment:()=>({amount:'1'}), $set:(array,index,row)=>array.splice(index,1,row), $message:{error:()=>messages++}, changeTotalPrepayment:()=>recalculations++}
    run.call(context, 0)
    assert.equal(context.form.prepayments[0].total, '0.116')
    context.form.prepayments[0].amount = '9007199254740993.02'
    context.getPrepayment = () => ({amount:'9007199254740993.01'})
    context.form.affectation_type_prepayment = 20
    run.call(context, 0)
    assert.equal(context.form.prepayments[0].amount, '9007199254740993.01')
    assert.equal(context.form.prepayments[0].total, '9007199254740993.01')
    assert.equal(messages, 1)
    assert.equal(recalculations, 2)
    assert.deepEqual(compiler.compile(component.template.content).errors, [])
})
