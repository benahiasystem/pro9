const {test}=require('node:test')
const assert=require('node:assert/strict')
const fs=require('node:fs')
const compiler=require('vue-template-compiler')

test('both product report layouts identify monetary currency and frozen unit',()=>{
    for (const file of ['index.vue','index_extra.vue']) {
        const component=compiler.parseComponent(fs.readFileSync(`modules/Report/Resources/assets/js/views/items/${file}`,'utf8'))
        assert.deepEqual(compiler.compile(component.template.content).errors,[])
        assert.match(component.template.content,/row\.unit_type_id/)
        assert.match(component.template.content,/row\.currency_type_id/)
        assert.match(component.template.content,/sin ajustes globales/)
    }
})
