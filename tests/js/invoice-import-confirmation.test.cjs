const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm')
const compiler=require('vue-template-compiler'),parser=require('@babel/parser'),generate=require('@babel/generator').default
for(const name of ['import.vue','partials/import_second.vue','partials/ImportExcel.vue']) {
    const c=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/'+name,'utf8'))
    const ast=parser.parse(c.script.content,{sourceType:'module'})
    const props=ast.program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration.properties
    const entries=props.find(n=>n.key?.name==='methods').value.properties
    const methods=vm.runInNewContext(`({${entries.filter(n=>['initForm','submit','onSubmit'].includes(n.key?.name)).map(n=>generate(n).code).join(',')}})`)
    test(name+': upload keeps the existing form without new credit or due-date requirements',async()=>{
        let uploads=0;const warnings=[],state={...methods,$refs:{upload:{async submit(){uploads++}}},$message:{error:m=>warnings.push(m)}}
        state.initForm();const submit=state.onSubmit || state.submit
        assert.equal('import_as_credit' in state.form,false);assert.equal('import_due_date' in state.form,false)
        await submit.call(state);assert.equal(uploads,1);assert.equal(warnings.length,0)
        assert.deepEqual(compiler.compile(c.template.content).errors,[])
        assert.equal(c.template.content.includes('import-credit-confirmation'),false)
        assert.equal(c.template.content.includes('import_due_date'),false)
        assert.equal(c.template.content.includes('import_as_credit'),false)
    })
}
