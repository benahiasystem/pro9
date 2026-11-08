const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const compiler = require('vue-template-compiler')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default
const t = require('@babel/types')
const babel = require('@babel/core')

const math = {}
vm.runInNewContext(babel.transformSync(fs.readFileSync('resources/js/helpers/exchange-rate-math.js','utf8'), {
    configFile:false, babelrc:false, plugins:['@babel/plugin-transform-modules-commonjs']
}).code, {exports:math})
const formatting = {}
vm.runInNewContext(babel.transformSync(fs.readFileSync('resources/js/helpers/format-money-exact.js','utf8'), {
    configFile:false, babelrc:false, plugins:['@babel/plugin-transform-modules-commonjs']
}).code, {exports:formatting, require:()=>math})
const source=compiler.parseComponent(fs.readFileSync('modules/Dashboard/Resources/assets/js/views/partials/new_dashboard/NewDashboard.vue','utf8'))
assert.deepEqual(compiler.compile(source.template.content).errors, [])
const ast=parser.parse(source.script.content,{sourceType:'module'})
const methods=ast.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties.find(node=>node.key?.name==='methods').value
const node=methods.properties.find(item=>item.key?.name==='formatMoney')
const formatMoney=vm.runInNewContext(`(${generate(t.functionExpression(null,node.params,node.body)).code})`,formatting)

test('dashboard money retains decimal precision beyond JavaScript safe integers',()=>{
    assert.equal(formatMoney('9007199254740993.01'),'9,007,199,254,740,993.01')
    assert.equal(formatMoney('-1234.5'),'-1,234.50')
    assert.equal(formatMoney('0.01'),'0.01')
    assert.equal(formatMoney(null),'0.00')
})

function computed(file, name) {
    const component=compiler.parseComponent(fs.readFileSync(file,'utf8'))
    assert.deepEqual(compiler.compile(component.template.content).errors, [])
    const tree=parser.parse(component.script.content,{sourceType:'module'})
    const group=tree.program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration.properties.find(n=>n.key?.name==='computed').value
    const method=group.properties.find(n=>n.key?.name===name)
    return vm.runInNewContext(`(${generate(t.functionExpression(null,method.params,method.body)).code})`,formatting)
}

test('charts show exact decimal strings while keeping numeric plot data',()=>{
    const base='modules/Dashboard/Resources/assets/js/views/partials/new_dashboard/'
    const bars=computed(base+'MonthlyComparisonChart.vue','chartOptions').call({
        salesExact:['9007199254740993.01'], purchasesExact:['0.01'], offset:0, maxVisible:12,
        visibleSales:[9007199254740992], visiblePurchases:[0.01], barRadius:2, yAxisMax:10,
    })
    const tooltip=bars.tooltip.custom({seriesIndex:0,dataPointIndex:0,w:{config:{series:[{name:'Ventas'}]},globals:{labels:['Hoy']}}})
    assert.match(tooltip,/9,007,199,254,740,993\.01/)
    const lines=computed(base+'SalesGrowthChart.vue','chartOptions').call({currentExact:['9007199254740993.01'],previousExact:['0.01'],categories:['ENE']})
    assert.equal(lines.tooltip.y.formatter(9007199254740992,{seriesIndex:0,dataPointIndex:0}),'Bs. 9,007,199,254,740,993.01')
    assert.equal(lines.tooltip.y.formatter(0.01,{seriesIndex:1,dataPointIndex:0}),'Bs. 0.01')
})
