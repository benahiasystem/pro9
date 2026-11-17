const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const compiler = require('vue-template-compiler')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default

const component = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/invoice_generate.vue', 'utf8'))
const tree = parser.parse(component.script.content, {sourceType: 'module'})
const properties = tree.program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration.properties

function loadMember(name, members = properties, globals = {}) {
    const member = members.find(node => node.key?.name === name)
    return vm.runInNewContext(`({${generate(member).code}}).${name}`, globals)
}

const filter = loadMember('filterSeries', properties.find(node => node.key?.name === 'methods').value.properties)

test('the document form uses the route contingency flag to select matching series', () => {
    const data = loadMember('data', properties, {headers_token: {}})
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

test('web invoicing hides the series selector when only the blank option is authorized', () => {
    const visible = loadMember('hasNamedSeries', properties.find(node => node.key?.name === 'computed').value.properties)
    const template = component.template.content
    assert.match(template, /v-if="hasNamedSeries"\s*>\s*<label[^>]*>Serie<\/label>\s*<el-select/)
    assert.match(template, /v-if="hasNamedSeries \|\| errors.series_id"/)

    const blank = {id: 1, establishment_id: 1, document_type_id: '01', contingency: false, number: ''}
    const named = {...blank, id: 2, number: 'FF01'}
    const otherBranch = {...named, id: 3, establishment_id: 2}
    const ctx = {typeUser: 'admin', config: {user: {}}, is_contingency: false,
        form: {establishment_id: 1, document_type_id: '01'}}
    ctx.$store = {commit: (_, rows) => { ctx.series = rows }}

    for (const [allowed, id, showSelector] of [
        [[blank], 1, false],
        [[blank, named], 1, true],
        [[named], 2, true],
        [[], null, false],
    ]) {
        ctx.all_series = [...allowed, otherBranch]
        filter.call(ctx)
        assert.equal(ctx.form.series_id, id)
        assert.equal(visible.call(ctx), showSelector)
    }
})
