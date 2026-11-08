const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const compiler = require('vue-template-compiler')
const babel = require('@babel/core')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default
const t = require('@babel/types')

const math = {}
vm.runInNewContext(babel.transformSync(fs.readFileSync('resources/js/helpers/exchange-rate-math.js','utf8'), {
    configFile:false, babelrc:false, plugins:['@babel/plugin-transform-modules-commonjs']
}).code, {exports:math})
const component=compiler.parseComponent(fs.readFileSync('modules/Report/Resources/assets/js/views/sales_by_brand/index.vue','utf8'))
assert.deepEqual(compiler.compile(component.template.content).errors, [])
const ast=parser.parse(component.script.content,{sourceType:'module'})
const computed=ast.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties.find(node=>node.key?.name==='computed').value
const method=name=>{
    const node=computed.properties.find(property=>property.key?.name===name)
    return vm.runInNewContext(`(${generate(t.functionExpression(null,node.params,node.body)).code})`,math)
}

test('totals use frozen fractions and keep physical units separate',()=>{
    const records=[
        {unit_type_id:'KG',calculation_snapshot:{values:{quantity_sold:{n:'1',d:'3'},total_sale_value:{n:'1',d:'3'},total_profit:{n:'1',d:'6'}}}},
        {unit_type_id:'UND',calculation_snapshot:{values:{quantity_sold:{n:'2',d:'3'},total_sale_value:{n:'2',d:'3'},total_profit:{n:'1',d:'3'}}}},
        {unit_type_id:null,calculation_snapshot:{values:{quantity_sold:{n:'1',d:'1'},total_sale_value:{n:'0',d:'1'},total_profit:{n:'0',d:'1'}}}},
    ]
    const context={records}
    const units=method('quantitiesByUnit').call(context)
    assert.equal(units.KG,'0.333333333333')
    assert.equal(units.UND,'0.666666666667')
    assert.equal(Object.keys(units).length,2)
    assert.equal(method('totalSaleValue').call(context),'1.00')
    assert.equal(method('totalProfit').call(context),'0.50')
    assert.equal(method('hasUnknownUnit').call(context),true)
})
