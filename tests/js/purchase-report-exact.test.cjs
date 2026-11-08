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
const component=compiler.parseComponent(fs.readFileSync('modules/Report/Resources/assets/js/components/DataTableReports.vue','utf8'))
assert.deepEqual(compiler.compile(component.template.content).errors, [])
const purchase=compiler.parseComponent(fs.readFileSync('modules/Report/Resources/assets/js/views/purchases/index.vue','utf8'))
assert.deepEqual(compiler.compile(purchase.template.content).errors, [])
assert.match(purchase.template.content, /:colspanFootPurchase="6"/)
const ast=parser.parse(component.script.content,{sourceType:'module'})
const methods=ast.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties.find(node=>node.key?.name==='methods').value
const getTotals=methods.properties.find(property=>property.key?.name==='getTotals')
const run=vm.runInNewContext(`(${generate(t.functionExpression(null,getTotals.params,getTotals.body)).code})`,math)

test('purchase page totals sum the projected rows by currency and ignore voids',()=>{
    const make=(currency,state,n,d,display)=>({currency_type_id:currency,state_type_id:state,total:display,total_taxed:display,
        calculation_snapshot:{values:{total:{n,d},total_taxed:{n,d},total_igv:{n:'0',d:'1'}}}})
    const context={resource:'reports/purchases',totals:{},initTotals(){this.totals={}}}
    run.call(context,[make('USD','01','1','3','0.34'),make('USD','01','1','3','0.33'),make('USD','01','1','3','0.33'),make('USD','11','50','1','50.00'),make('VES','01','1','10','0.10')])
    assert.equal(context.totals.acum_total_usd,'1.00')
    assert.equal(context.totals.acum_total_taxed_usd,'1.00')
    assert.equal(context.totals.acum_total,'0.10')
    assert.equal(context.totals.acum_total_perception_usd,'0.00')
})

test('fixed asset purchase totals include perception and separate both currencies',()=>{
    const fixed=compiler.parseComponent(fs.readFileSync('modules/Report/Resources/assets/js/views/fixed-asset-purchases/index.vue','utf8'))
    assert.deepEqual(compiler.compile(fixed.template.content).errors, [])
    assert.match(fixed.template.content, /:colspan-foot-purchase="7"/)
    const context={resource:'reports/fixed-asset-purchases',totals:{},initTotals(){this.totals={}}}
    run.call(context,[
        {currency_type_id:'USD',state_type_id:'01',total:'0.44',total_perception:'0.10'},
        {currency_type_id:'USD',state_type_id:'01',total:'0.43',total_perception:'0.10'},
        {currency_type_id:'VES',state_type_id:'01',total:'4.25',total_perception:'0.25'},
        {currency_type_id:'USD',state_type_id:'09',total:'90.00',total_perception:'1.00'},
    ])
    assert.equal(context.totals.acum_total_usd,'0.87')
    assert.equal(context.totals.acum_total_perception_usd,'0.20')
    assert.equal(context.totals.acum_total,'4.25')
    assert.equal(context.totals.acum_total_perception,'0.25')
})
