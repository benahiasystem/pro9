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

const source = compiler.parseComponent(fs.readFileSync('modules/Report/Resources/assets/js/components/DataTableReportsDocuments.vue', 'utf8'))
assert.deepEqual(compiler.compile(source.template.content).errors, [])
const ast = parser.parse(source.script.content, {sourceType:'module'})
const methods = ast.program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration.properties.find(node => node.key?.name === 'methods').value
function method(name) {
    const node = methods.properties.find(item => item.key?.name === name)
    return vm.runInNewContext(`(${generate(t.functionExpression(null,node.params,node.body,false,node.async)).code})`, {
        moment: () => ({format: () => '2026-10-30'}), exactAmount:math.exactAmount
    })
}

test('document sales report starts with an invoice type', () => {
    const context = {resource:'reports/sales', applyConversionToPen:false}
    method('initForm').call(context)
    assert.equal(context.form.document_type_id, '01')
})

test('sales totals sum exact fractions by currency and exclude rejected credit notes', () => {
    const context={resource:'reports/sales', salesTotalColumnKeys:['total'], initTotals() {this.totals={}}}
    method('getTotals').call(context,[
        {currency_type_id:'VES',document_type_id:'01',state_type_id:'01',report_total_fractions:{total:{n:'1',d:'3'}}},
        {currency_type_id:'VES',document_type_id:'01',state_type_id:'01',report_total_fractions:{total:{n:'1',d:'3'}}},
        {currency_type_id:'VES',document_type_id:'01',state_type_id:'01',report_total_fractions:{total:{n:'1',d:'3'}}},
        {currency_type_id:'VES',document_type_id:'07',state_type_id:'09',total:'9'},
        {currency_type_id:'USD',document_type_id:'01',state_type_id:'01',total:'4.75'},
        {currency_type_id:'USD',document_type_id:'07',state_type_id:'01',total:'1.25'},
    ])
    assert.equal(context.totals.acum_total,'1.00')
    assert.equal(context.totals.acum_total_usd,'3.50')
    assert.equal(method('salesFooterValue').call(context,'total','VES'),'1.00')
})

test('a rejected search clears old results, reports the error and releases the button', async () => {
    const messages=[]
    const context={resource:'reports/sales', records:[{id:1}], loading_submit:false,
        getQueryParameters:() => '', initTotals() {this.totals='reset'},
        $http:{get:() => Promise.reject({response:{data:{message:'Seleccione un tipo de documento.'}}})},
        $message:{error:value => messages.push(value)}}
    context.getRecords=method('getRecords').bind(context)
    await method('getRecordsByFilter').call(context)
    assert.equal(context.loading_submit,false)
    assert.equal(context.records.length,0)
    assert.equal(context.totals,'reset')
    assert.deepEqual(messages,['Seleccione un tipo de documento.'])
})
