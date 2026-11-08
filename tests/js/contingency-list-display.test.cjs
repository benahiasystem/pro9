const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const compiler = require('vue-template-compiler')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default
const babel = require('@babel/core')

test('the contingency list renders status without an undefined helper and parses its dates explicitly', () => {
    const component = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/contingencies/index.vue','utf8'))
    assert.doesNotMatch(component.template.content, /\btooltip\s*\(/)
    assert.doesNotMatch(component.template.content, /total_free|total_unaffected/)
    assert.match(component.template.content, /row\.state_type_description/)
    assert.deepEqual(compiler.compile(component.template.content).errors, [])
    const tree = parser.parse(component.script.content,{sourceType:'module'})
    const properties = tree.program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration.properties
    const methods = properties.find(node => node.key.name === 'methods').value
    const entry = methods.properties.find(node => node.key?.name === 'formatDate')
    const format = vm.runInNewContext(`({${generate(entry).code}}).formatDate`,{moment:require('moment')})
    assert.equal(format('06-11-2026'),'06-11-2026')
    assert.equal(format('2026-11-06'),'06-11-2026')
    assert.equal(format('31-02-2026'),null)
    const math = {}
    vm.runInNewContext(babel.transformSync(fs.readFileSync('resources/js/helpers/exchange-rate-math.js','utf8'), {
        configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']
    }).code,{exports:math})
    const formatting = {}
    vm.runInNewContext(babel.transformSync(fs.readFileSync('resources/js/helpers/format-money-exact.js','utf8'), {
        configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']
    }).code,{exports:formatting,require:()=>math})
    const totalEntry = methods.properties.find(node => node.key?.name === 'formatTotal')
    const total = vm.runInNewContext(`({${generate(totalEntry).code}}).formatTotal`,{...math,...formatting})
    const row={total:'0',calculation_snapshot:{values:{total:{n:'900719925474099301',d:'100'}}}}
    assert.equal(total(row,'total'),'9,007,199,254,740,993.01')
    assert.equal(total({total_taxed:'86.206896551724'},'total_taxed'),'86.21')
    assert.equal(total({total_igv:'13.793103448276'},'total_igv'),'13.79')
    const loadEntry = methods.properties.find(node => node.key?.name === 'loadColumnVisibility')
    const localStorage = {getItem:()=>JSON.stringify({total_free:{visible:true},total_exonerated:{visible:true}})}
    const load = vm.runInNewContext(`({${generate(loadEntry).code}}).loadColumnVisibility`,{localStorage})
    const context = {columns:{total_exonerated:{visible:false},total_exportation:{visible:false}}}
    load.call(context)
    assert.equal(context.columns.total_exonerated.visible,true)
    assert.equal(context.columns.total_exportation.visible,false)
    assert.equal('total_free' in context.columns,false)
    localStorage.getItem=()=>'{malformed'
    assert.doesNotThrow(()=>load.call(context))
})
