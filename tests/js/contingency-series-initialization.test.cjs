const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const compiler = require('vue-template-compiler')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default

test('the document form uses the route contingency flag to select matching series', () => {
    const component = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/invoice_generate.vue', 'utf8'))
    const tree = parser.parse(component.script.content, {sourceType:'module'})
    const properties = tree.program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration.properties
    const data = vm.runInNewContext(`({${generate(properties.find(node => node.key?.name === 'data')).code}}).data`, {headers_token:{}})
    const methods = properties.find(node => node.key?.name === 'methods').value
    const filter = vm.runInNewContext(`({${generate(methods.properties.find(node => node.key?.name === 'filterSeries')).code}}).filterSeries`)
    for (const initial of [0, 1]) {
        const state = data.call({initialContingency:initial})
        assert.equal(state.is_contingency, Boolean(initial))
        const context = {is_contingency:state.is_contingency, typeUser:'admin', config:{user:{}},
            form:{establishment_id:1, document_type_id:'01'}, series:[],
            all_series:[{id:1,establishment_id:1,document_type_id:'01',contingency:false},
                {id:2,establishment_id:1,document_type_id:'01',contingency:true}]}
        context.$store={commit:(_, rows)=>{ context.series=rows }}
        filter.call(context)
        assert.equal(context.form.series_id, initial ? 2 : 1)
        assert.equal(context.series.length,1)
    }
    assert.match(fs.readFileSync('resources/views/tenant/documents/form.blade.php','utf8'), /:initial-contingency="{{ json_encode\(\$is_contingency\) }}"/)
    assert.deepEqual(compiler.compile(component.template.content).errors, [])
})
