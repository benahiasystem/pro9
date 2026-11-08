const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const compiler = require('vue-template-compiler')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default
const vm = require('node:vm')

test('detailed profit identifies currency and avoids float formatting', () => {
  const component = compiler.parseComponent(fs.readFileSync('modules/Report/Resources/assets/js/views/commissions_detail/index.vue', 'utf8'))
  assert.deepEqual(compiler.compile(component.template.content).errors, [])
  assert.match(component.template.content, /row\.currency_type_id/)
  assert.match(component.template.content, /row\.display_profit/)
  assert.doesNotMatch(component.script.content, /\bNumber\(value\)/)
})

test('detailed profit totals displayed rows separately by currency without summing unit gains', () => {
  const component = compiler.parseComponent(fs.readFileSync('modules/Report/Resources/assets/js/components/DataTableReportsComissionDetail.vue','utf8'))
  assert.deepEqual(compiler.compile(component.template.content).errors, [])
  assert.doesNotMatch(component.template.content, /acum_unit_gain/)
  const tree = parser.parse(component.script.content,{sourceType:'module'})
  const methods = tree.program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration.properties.find(n=>n.key.name==='methods').value
  const entry = methods.properties.find(n=>n.key?.name==='getTotals')
  const helpers = require('./helpers/load-module.cjs')(require('node:path').resolve('resources/js/helpers/exchange-rate-math.js'))
  const getTotals = vm.runInNewContext(`({${generate(entry).code}}).getTotals`,helpers)
  const ctx = {}
  getTotals.call(ctx,[
    {currency_type_id:'VES',display_profit:'0.33',unit_gain:'900'},
    {currency_type_id:'VES',display_profit:'0.34',unit_gain:'800'},
    {currency_type_id:'USD',display_profit:'9007199254740993.01',unit_gain:'700'},
    {currency_type_id:'USD',display_profit:'-0.01',unit_gain:'600'},
  ])
  assert.equal(ctx.totals.find(row=>row.currency==='VES').profit,'0.67')
  assert.equal(ctx.totals.find(row=>row.currency==='USD').profit,'9007199254740993.00')
  assert.equal(ctx.totals.length,2)
})
